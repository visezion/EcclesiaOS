<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class RoleDirectoryController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles', [
            'roles' => Role::query()->with(['permissions', 'users'])->withCount('users')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'modules' => collect(config('navigation'))->flatMap(fn (array $item): array => $item['children'] ?? [$item])->whereNotNull('permission')->values(),
            'stats' => [
                'roles' => Role::query()->count(),
                'custom' => Role::query()->where('name', '!=', 'Super Administrator')->count(),
                'assigned' => DB::table('role_user')->count(),
                'permissions' => Permission::query()->count(),
                'restricted' => User::query()->where('status', '!=', 'active')->count(),
                'updated' => Role::query()->max('updated_at'),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Roles & Permissions', 'url' => null],
            ],
        ]);
    }
}
