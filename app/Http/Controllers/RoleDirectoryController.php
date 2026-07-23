<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RoleDirectoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Role::class);
        $visibleUsers = $this->visibleUsers($request);
        $visibleUserIds = (clone $visibleUsers)->pluck('id');

        return view('admin.roles', [
            'roles' => Role::query()
                ->with(['permissions', 'users' => fn ($query) => $query->whereIn('users.id', $visibleUserIds)])
                ->withCount(['users' => fn ($query) => $query->whereIn('users.id', $visibleUserIds)])
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'modules' => collect(config('navigation'))->flatMap(fn (array $item): array => $item['children'] ?? [$item])->whereNotNull('permission')->values(),
            'stats' => [
                'roles' => Role::query()->count(),
                'custom' => Role::query()->where('name', '!=', 'Super Administrator')->count(),
                'assigned' => DB::table('role_user')->whereIn('user_id', $visibleUserIds)->count(),
                'permissions' => Permission::query()->count(),
                'restricted' => (clone $visibleUsers)->where('status', '!=', 'active')->count(),
                'updated' => Role::query()->max('updated_at'),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Roles & Permissions', 'url' => null],
            ],
        ]);
    }

    /**
     * @return Builder<User>
     */
    private function visibleUsers(Request $request): Builder
    {
        $query = User::query();
        $user = $request->user();

        if ($user && ! $user->isSuperAdministrator()) {
            $query->where('church_id', $user->church_id);

            if ($user->campus_id) {
                $query->where('campus_id', $user->campus_id);
            }
        }

        return $query;
    }
}
