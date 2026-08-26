<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION = 'use ai copilot';

    private const LEGACY_ACCESS_PERMISSIONS = [
        'manage members',
        'manage attendance',
        'view finance',
        'manage finance',
        'view ministry finance',
        'view leadership reports',
        'view reports',
        'manage events',
        'manage prayer',
        'manage volunteers',
        'manage ministries',
        'manage assets',
        'manage facilities',
        'manage communications',
        'manage counselling',
        'manage financial assistance',
        'manage support',
        'manage workflows',
        'manage bookstore',
    ];

    public function up(): void
    {
        $now = now();
        $slug = Str::slug(self::PERMISSION);
        $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'slug' => $slug,
                'description' => 'Allows user to access and use the permission-aware AI Copilot.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('permissions')->where('id', $permissionId)->update([
                'name' => self::PERMISSION,
                'description' => 'Allows user to access and use the permission-aware AI Copilot.',
                'updated_at' => $now,
            ]);
        }

        $legacyPermissionIds = DB::table('permissions')
            ->whereIn('name', self::LEGACY_ACCESS_PERMISSIONS)
            ->pluck('id');
        $roleIds = DB::table('permission_role')
            ->whereIn('permission_id', $legacyPermissionIds)
            ->pluck('role_id')
            ->merge(DB::table('roles')->where('name', 'Super Administrator')->pluck('id'))
            ->unique();

        $roleIds->each(function (int $roleId) use ($now, $permissionId): void {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        // Keep the permission and administrator assignments during rollback so
        // a deployment rollback cannot unexpectedly remove production access.
    }
};
