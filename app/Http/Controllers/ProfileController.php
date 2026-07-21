<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

final class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('church', 'campus', 'roles.permissions'),
            'activityLogs' => $request->user()->activityLogs()->latest()->limit(5)->get(),
            'activeSessions' => DB::table('sessions')->where('user_id', $request->user()->id)->latest('last_activity')->limit(3)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Profile', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
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
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_url'] = $path;
        }

        unset($validated['avatar']);

        $user->update($validated);

        $activityLogger->log('Profile', 'profile_updated', 'User updated their profile.', $user, request: $request);

        return back()->with('status', 'Profile updated.');
    }

    public function password(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'password_changed_at' => now(),
        ]);

        $activityLogger->log('Profile', 'password_changed', 'User changed their password.', $request->user(), request: $request);

        return back()->with('password_status', 'Password updated.');
    }

    public function impersonate(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->session()->put('profile_preview_user_id', $request->user()->id);

        $activityLogger->log('Profile', 'profile_preview_started', 'User opened profile impersonation preview.', $request->user(), request: $request);

        return back()->with('status', 'Profile preview mode started for '.$request->user()->name.'.');
    }
}
