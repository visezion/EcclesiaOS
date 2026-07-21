<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RolePermissionController extends Controller
{
    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? $validated['name'].' application role',
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        $activityLogger->log('Access Control', 'role_created', 'Administrator created a role.', $role, [
            'role' => $role->name,
            'permissions' => $validated['permissions'] ?? [],
        ], $request);

        return redirect()->route('roles.index')->with('status', 'Role created.');
    }

    public function clone(Request $request, Role $role, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $clone = Role::query()->create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? 'Cloned from '.$role->name,
        ]);

        $clone->permissions()->sync($role->permissions()->pluck('permissions.id')->all());

        $activityLogger->log('Access Control', 'role_cloned', 'Administrator cloned a role.', $clone, [
            'source_role' => $role->name,
            'role' => $clone->name,
        ], $request);

        return redirect()->route('roles.index')->with('status', 'Role cloned.');
    }

    public function update(Request $request, Role $role, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $permissionIds = $role->name === 'Super Administrator'
            ? Permission::query()->pluck('id')->all()
            : ($validated['permissions'] ?? []);

        $role->permissions()->sync($permissionIds);

        $activityLogger->log('Access Control', 'role_permissions_updated', 'Administrator updated role permissions.', $role, [
            'role' => $role->name,
            'permissions' => $permissionIds,
        ], $request);

        return back()->with('status', 'Role permissions updated.');
    }

    public function reset(Request $request, Role $role, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorize('update', $role);

        $permissionNames = config('access.roles.'.$role->name, []);

        if ($permissionNames === ['*']) {
            $permissionIds = Permission::query()->pluck('id')->all();
        } else {
            $permissionIds = Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all();
        }

        $role->permissions()->sync($permissionIds);

        $activityLogger->log('Access Control', 'role_permissions_reset', 'Administrator reset role permissions.', $role, [
            'role' => $role->name,
            'permissions' => $permissionIds,
        ], $request);

        return back()->with('status', 'Role permissions reset to default.');
    }

    public function report(): StreamedResponse
    {
        $this->authorize('viewAny', Role::class);

        $filename = 'permission-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Role', 'Type', 'Users Assigned', 'Permission']);

            Role::query()
                ->with(['permissions' => fn ($query) => $query->orderBy('name')])
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->each(function (Role $role) use ($handle): void {
                    $type = $role->name === 'Super Administrator' ? 'System' : 'Custom';

                    if ($role->permissions->isEmpty()) {
                        fputcsv($handle, [$role->name, $type, $role->users_count, 'No permissions']);

                        return;
                    }

                    $role->permissions->each(fn ($permission) => fputcsv($handle, [
                        $role->name,
                        $type,
                        $role->users_count,
                        $permission->name,
                    ]));
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
