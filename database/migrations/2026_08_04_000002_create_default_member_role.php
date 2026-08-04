<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = ['use messages', 'use bible'];

    public function up(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'Member'],
            ['slug' => 'member', 'description' => 'Default access for church members using the self-service portal.'],
        );

        $permissionIds = collect(self::PERMISSIONS)
            ->map(fn (string $name): int => Permission::query()->firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'description' => 'Allows user to '.$name],
            )->id);

        $role->permissions()->syncWithoutDetaching($permissionIds);
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'Member')->first();

        if (! $role) {
            return;
        }

        $permissionIds = Permission::query()->whereIn('name', self::PERMISSIONS)->pluck('id');
        $role->permissions()->detach($permissionIds);
    }
};
