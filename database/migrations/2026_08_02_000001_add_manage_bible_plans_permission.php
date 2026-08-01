<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION = 'manage bible plans';

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['slug' => Str::slug(self::PERMISSION)],
            ['name' => self::PERMISSION, 'description' => 'Allows user to create and manage church Bible reading plans', 'updated_at' => $now, 'created_at' => $now],
        );

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        $roleIds = DB::table('roles')->whereIn('name', ['Super Administrator', 'Church Administrator'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $roleId],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
