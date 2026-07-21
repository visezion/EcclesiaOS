<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AccessControlController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $currentUser = $request->user();
        $users = User::query()
            ->with(['church', 'campus', 'roles'])
            ->when(! $currentUser->isSuperAdministrator(), fn ($query) => $query->where('church_id', $currentUser->church_id))
            ->latest()
            ->get();

        return view('settings.access-control', [
            'users' => $users,
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'churches' => Church::query()->orderBy('name')->get(),
            'campuses' => Campus::query()->orderBy('name')->get(),
            'activityLogs' => ActivityLog::query()->with('user')->latest()->limit(15)->get(),
            'brandingChurch' => Church::query()->first(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => null],
            ],
        ]);
    }
}
