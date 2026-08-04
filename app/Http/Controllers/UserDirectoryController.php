<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Support\Csv;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserDirectoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $scopedUsers = $this->scopeUsers(User::query(), $request);
        $filteredUsers = clone $scopedUsers;
        $this->applyFilters($filteredUsers, $request);
        $users = $filteredUsers->with(['church', 'campus', 'roles'])->latest()->paginate(20)->withQueryString();
        $roles = Role::query()->orderBy('name')->get();
        $campuses = $this->scopeCampuses(Campus::query(), $request)->orderBy('name')->get();
        $totalUsers = (clone $scopedUsers)->count();

        return view('admin.users', [
            'users' => $users,
            'roles' => $roles,
            'churches' => $this->scopeChurches(Church::query(), $request)->orderBy('name')->get(),
            'campuses' => $campuses,
            'roleDistribution' => Role::query()->withCount(['users' => fn (Builder $query) => $this->scopeUsers($query, $request)])->orderByDesc('users_count')->get(),
            'campusDistribution' => $this->scopeCampuses(Campus::query(), $request)->withCount(['users' => fn (Builder $query) => $this->scopeUsers($query, $request)])->orderByDesc('users_count')->get(),
            'recentActivity' => ActivityLog::query()->with('user')->where('module', 'Access Control')->latest()->limit(6)->get(),
            'stats' => [
                'total' => $totalUsers,
                'active' => (clone $scopedUsers)->where('status', 'active')->count(),
                'pending' => (clone $scopedUsers)->where('status', 'inactive')->count(),
                'locked' => (clone $scopedUsers)->where('status', 'suspended')->count(),
                'campuses' => $campuses->count(),
                'mfa' => (clone $scopedUsers)->where('mfa_enabled', true)->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Users Management', 'url' => null],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $filename = 'users-directory-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            Csv::write($handle, [
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

            $query = $this->scopeUsers(User::query(), $request);
            $this->applyFilters($query, $request);

            $query
                ->with(['church', 'campus', 'roles'])
                ->orderBy('name')
                ->lazy(100)
                ->each(function (User $user) use ($handle): void {
                    $church = $user->relationLoaded('church') ? $user->getRelation('church') : null;
                    $campus = $user->relationLoaded('campus') ? $user->getRelation('campus') : null;
                    $lastLogin = $user->getAttribute('last_login_at');

                    Csv::write($handle, [
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

    private function scopeUsers(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);
        $query->whereDoesntHave('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'Super Administrator'));

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function scopeCampuses(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where('id', $user->campus_id);
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim($request->string('q')->toString());
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereKey($request->integer('role_id')));
        }

        if ($request->filled('campus_id')) {
            $query->where('campus_id', $request->integer('campus_id'));
        }

        if (in_array($request->string('status')->toString(), ['active', 'inactive', 'suspended'], true)) {
            $query->where('status', $request->string('status')->toString());
        }
    }

    private function scopeChurches(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        return $query->where('id', $user?->church_id);
    }
}
