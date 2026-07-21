<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UserManagementController extends Controller
{
    public function show(Request $request, User $user): View
    {
        $this->authorize('view', $user);

        $user->load('church', 'campus', 'roles.permissions');

        return view('profile.edit', [
            'user' => $user,
            'activityLogs' => ActivityLog::query()
                ->where(fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->orWhere(fn ($subjectQuery) => $subjectQuery
                        ->where('subject_type', $user->getMorphClass())
                        ->where('subject_id', $user->id)))
                ->latest()
                ->limit(5)
                ->get(),
            'activeSessions' => DB::table('sessions')->where('user_id', $user->id)->latest('last_activity')->limit(3)->get(),
            'roles' => $this->visibleRoles($request)->get(),
            'churches' => $this->visibleChurches($request)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'isAdminProfile' => true,
            'profileUpdateRoute' => route('users.update', $user),
            'profileUpdateMethod' => 'PUT',
            'passwordUpdateRoute' => route('users.password', $user),
            'passwordRequiresCurrent' => false,
            'impersonateRoute' => route('users.impersonate', $user),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Users Management', 'url' => route('users.index')],
                ['label' => $user->name, 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $this->validated($request);
        $validated = $this->scopeAssignment($request, $validated);
        $validated = $this->applySystemDefaults($validated);

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
            'users.*' => ['required', 'string'],
        ]);

        $userIds = OpaqueId::decodeMany($validated['users'], User::class);
        if ($userIds === []) {
            throw ValidationException::withMessages(['users' => 'Select at least one valid user.']);
        }

        $users = User::query()
            ->whereIn('id', $userIds)
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

    public function resetPassword(Request $request, User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        $activityLogger->log('Access Control', 'admin_password_reset', 'Administrator reset a user password.', $user, [
            'email' => $user->email,
        ], $request);

        return back()->with('password_status', 'Password updated for '.$user->name.'.');
    }

    public function impersonate(Request $request, User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('view', $user);

        $administrator = $request->user();

        if ($administrator === null || $administrator->is($user)) {
            return back()->with('status', 'You are already viewing this account.');
        }

        $activityLogger->log('Access Control', 'user_impersonation_started', 'Administrator impersonated a user account.', $user, [
            'administrator_id' => $administrator->id,
            'administrator_email' => $administrator->email,
            'target_email' => $user->email,
        ], $request);

        $request->session()->put('impersonator_id', $administrator->id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('status', 'You are now impersonating '.$user->name.'.');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $administratorId = $request->session()->pull('impersonator_id');

        if (! $administratorId) {
            return redirect()->route('dashboard');
        }

        $administrator = User::query()->findOrFail($administratorId);

        Auth::login($administrator);
        $request->session()->regenerate();

        return redirect()->route('users.index')->with('status', 'Returned to administrator account.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
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
        if ($request->hasFile('avatar')) {
            $validated['avatar_url'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);

        $actor = $request->user();

        if (! $actor?->isSuperAdministrator() && ! $actor?->hasPermission('manage roles')) {
            $defaultRoleId = Role::query()->where('name', 'Viewer')->value('id');
            $validated['roles'] = $defaultRoleId ? [(int) $defaultRoleId] : [];
        }

        if (! empty($validated['roles'])) {
            $assignableRoleIds = $this->visibleRoles($request)->pluck('id')->all();
            $validated['roles'] = collect($validated['roles'])
                ->map(fn ($roleId): int => (int) $roleId)
                ->intersect($assignableRoleIds)
                ->values()
                ->all();
        }

        if ($actor?->isSuperAdministrator()) {
            return $validated;
        }

        $validated['church_id'] = $actor?->church_id;

        if ($actor?->campus_id !== null) {
            $validated['campus_id'] = $actor->campus_id;
        } elseif (! empty($validated['campus_id'])) {
            abort_unless($this->visibleCampuses($request)->where('id', $validated['campus_id'])->exists(), 403);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applySystemDefaults(array $validated): array
    {
        $church = Church::query()->first();
        $settings = $church?->settings ?? [];

        $validated['timezone'] = $validated['timezone'] ?? ($settings['timezone'] ?? $church?->timezone ?? config('church.timezone'));
        $validated['church_id'] = $validated['church_id'] ?? ($settings['headquarters_church_id'] ?? $church?->id);
        $validated['campus_id'] = $validated['campus_id'] ?? ($settings['default_campus_id'] ?? null);

        if (empty($validated['roles'])) {
            $defaultRole = Role::query()
                ->where('name', $settings['default_user_role'] ?? 'Viewer')
                ->first()
                ?? Role::query()->where('name', 'Viewer')->first()
                ?? Role::query()->orderBy('name')->first();

            $validated['roles'] = $defaultRole ? [$defaultRole->id] : [];
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

    private function visibleChurches(Request $request): Builder
    {
        $query = Church::query()->orderBy('name');
        $actor = $request->user();

        if ($actor?->isSuperAdministrator()) {
            return $query;
        }

        return $query->where('id', $actor?->church_id);
    }

    private function visibleCampuses(Request $request): Builder
    {
        $query = Campus::query()->orderBy('name');
        $actor = $request->user();

        if ($actor?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $actor?->church_id);

        if ($actor?->campus_id !== null) {
            $query->where('id', $actor->campus_id);
        }

        return $query;
    }

    private function visibleRoles(Request $request): Builder
    {
        $query = Role::query()->orderBy('name');
        $actor = $request->user();

        if ($actor?->isSuperAdministrator()) {
            return $query;
        }

        if ($actor?->hasPermission('manage roles')) {
            return $query->where('name', '!=', 'Super Administrator');
        }

        return $query->where('name', 'Viewer');
    }
}
