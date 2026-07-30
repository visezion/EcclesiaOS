<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'send messages',
        'create message groups',
        'manage message participants',
        'forward messages',
        'export message history',
        'view sensitive messages',
        'administer messages',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => 'Allows user to '.$name, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $permissions = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id', 'name');
        foreach (config('access.roles') as $roleName => $rolePermissions) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            $names = $rolePermissions === ['*'] ? self::PERMISSIONS : array_intersect(self::PERMISSIONS, $rolePermissions);
            foreach ($names as $name) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissions[$name], 'role_id' => $roleId],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
