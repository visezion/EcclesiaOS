<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class BrandingController extends Controller
{
    public function updateSidebarBackground(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'sidebar_background' => ['required', 'file', 'mimes:png', 'max:2048'],
        ]);

        $church = $this->brandingChurch();
        $oldPath = data_get($church->settings, 'sidebar_background');
        $path = $validated['sidebar_background']->storeAs('branding', 'sidebar-background-'.now()->format('YmdHis').'.png', 'public');

        $settings = $church->settings ?? [];
        $settings['sidebar_background'] = $path;
        $church->forceFill(['settings' => $settings])->save();

        if (is_string($oldPath) && str_starts_with($oldPath, 'branding/') && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $activityLogger->log('Settings', 'sidebar_background_updated', 'Administrator updated the sidebar background image.', $church, [
            'resource' => 'Branding',
            'risk' => 'low',
            'status' => 'success',
        ], $request);

        return back()->with('status', 'Sidebar background updated.');
    }

    public function resetSidebarBackground(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        $church = $this->brandingChurch();
        $oldPath = data_get($church->settings, 'sidebar_background');
        $settings = $church->settings ?? [];
        unset($settings['sidebar_background']);
        $church->forceFill(['settings' => $settings])->save();

        if (is_string($oldPath) && str_starts_with($oldPath, 'branding/') && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $activityLogger->log('Settings', 'sidebar_background_reset', 'Administrator reset the sidebar background image.', $church, [
            'resource' => 'Branding',
            'risk' => 'low',
            'status' => 'success',
        ], $request);

        return back()->with('status', 'Sidebar background reset.');
    }

    private function brandingChurch(): Church
    {
        return Church::query()->firstOrCreate(
            ['slug' => 'kingdom-life-global-church'],
            [
                'name' => config('church.name'),
                'timezone' => config('church.timezone'),
                'currency' => config('church.currency'),
                'email' => config('church.contact_email'),
                'phone' => config('church.contact_phone'),
                'address' => config('church.address'),
                'settings' => [],
            ],
        );
    }
}
