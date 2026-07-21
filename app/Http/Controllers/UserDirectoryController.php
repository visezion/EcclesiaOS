<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserDirectoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with(['church', 'campus', 'roles'])->latest()->get();
        $roles = Role::query()->orderBy('name')->get();
        $campuses = Campus::query()->orderBy('name')->get();

        return view('admin.users', [
            'users' => $users,
            'roles' => $roles,
            'churches' => Church::query()->orderBy('name')->get(),
            'campuses' => $campuses,
            'roleDistribution' => Role::query()->withCount('users')->orderByDesc('users_count')->get(),
            'campusDistribution' => Campus::query()->withCount('users')->orderByDesc('users_count')->get(),
            'recentActivity' => ActivityLog::query()->with('user')->where('module', 'Access Control')->latest()->limit(6)->get(),
            'stats' => [
                'total' => $users->count(),
                'active' => $users->where('status', 'active')->count(),
                'pending' => $users->where('status', 'inactive')->count(),
                'locked' => $users->where('status', 'suspended')->count(),
                'campuses' => $campuses->count(),
                'mfa' => $users->where('mfa_enabled', true)->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Users Management', 'url' => null],
            ],
        ]);
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $filename = 'users-directory-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Name',
                'Email',
                'Phone',
                'Role',
                'Church',
                'Campus',
                'Status',
                'MFA Enabled',
                'Last Login',
            ]);

            User::query()
                ->with(['church', 'campus', 'roles'])
                ->orderBy('name')
                ->lazy(100)
                ->each(function (User $user) use ($handle): void {
                    $church = $user->relationLoaded('church') ? $user->getRelation('church') : null;
                    $campus = $user->relationLoaded('campus') ? $user->getRelation('campus') : null;
                    $lastLogin = $user->getAttribute('last_login_at');

                    fputcsv($handle, [
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->roles->pluck('name')->join(', '),
                        $church instanceof Church ? $church->name : null,
                        $campus instanceof Campus ? $campus->name : null,
                        $user->status,
                        $user->mfa_enabled ? 'Yes' : 'No',
                        $lastLogin instanceof CarbonInterface ? $lastLogin->toDateTimeString() : null,
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
