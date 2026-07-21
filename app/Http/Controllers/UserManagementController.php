<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Throwable;

final class UserManagementController extends Controller
{
    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $this->validated($request);
        $validated = $this->scopeAssignment($request, $validated);

        $user = User::query()->create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        $user->roles()->sync($validated['roles'] ?? []);

        $emailStatus = '';
        if ($request->boolean('send_invitation')) {
            $emailStatus = $this->sendInvitationEmail($user)
                ? ' Invitation email sent.'
                : ' Invitation email could not be sent; check mail settings.';
        }

        $activityLogger->log('Access Control', 'user_created', 'Administrator created a user account.', $user, [
            'email' => $user->email,
            'roles' => $validated['roles'] ?? [],
        ], $request);

        return back()->with('status', 'User created.'.$emailStatus);
    }

    public function update(Request $request, User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $this->validated($request, $user);
        $validated = $this->scopeAssignment($request, $validated);

        $user->update(collect($validated)->except(['password', 'roles'])->all());

        if (! empty($validated['password'])) {
            $user->update([
                'password' => Hash::make($validated['password']),
                'password_changed_at' => now(),
            ]);
        }

        if ($request->user()->can('assignRoles', $user)) {
            $user->roles()->sync($validated['roles'] ?? []);
        }

        $activityLogger->log('Access Control', 'user_updated', 'Administrator updated a user account.', $user, [
            'email' => $user->email,
            'roles' => $validated['roles'] ?? [],
        ], $request);

        return back()->with('status', 'User updated.');
    }

    public function bulk(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'suspend', 'enable_mfa', 'disable_mfa'])],
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        $users = User::query()
            ->whereIn('id', $validated['users'])
            ->get()
            ->filter(fn (User $user): bool => $request->user()?->can('update', $user) ?? false);

        $updates = match ($validated['action']) {
            'activate' => ['status' => 'active'],
            'deactivate' => ['status' => 'inactive'],
            'suspend' => ['status' => 'suspended'],
            'enable_mfa' => ['mfa_enabled' => true],
            'disable_mfa' => ['mfa_enabled' => false],
        };

        $users->each->update($updates);

        $activityLogger->log('Access Control', 'bulk_user_update', 'Administrator applied a bulk user action.', null, [
            'action' => $validated['action'],
            'user_ids' => $users->map(fn (User $user): int => $user->id)->all(),
        ], $request);

        return back()->with('status', $this->bulkStatusMessage($validated['action'], $users));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'church_id' => ['nullable', 'exists:churches,id'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Rules\Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
        ]);
    }

    private function scopeAssignment(Request $request, array $validated): array
    {
        if ($request->user()?->isSuperAdministrator()) {
            return $validated;
        }

        $validated['church_id'] = $request->user()?->church_id;

        if ($request->user()?->campus_id !== null) {
            $validated['campus_id'] = $request->user()->campus_id;
        }

        return $validated;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function bulkStatusMessage(string $action, Collection $users): string
    {
        $label = str_replace('_', ' ', $action);

        return number_format($users->count()).' user accounts updated: '.$label.'.';
    }

    private function sendInvitationEmail(User $user): bool
    {
        try {
            Mail::raw(
                "Hello {$user->name},\n\nYour KingdomHub account has been created. You can sign in with this email address and the temporary password provided by your administrator.\n\n".route('login'),
                fn ($message) => $message
                    ->to($user->email, $user->name)
                    ->subject('Your KingdomHub invitation'),
            );

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
