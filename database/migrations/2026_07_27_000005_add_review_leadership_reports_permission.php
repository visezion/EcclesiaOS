<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION = 'review leadership reports';

    private const ROLES = [
        'Super Administrator',
        'Church Administrator',
        'Senior Pastor',
        'Branch Pastor',
    ];

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => Str::slug(self::PERMISSION)],
            [
                'name' => self::PERMISSION,
                'description' => 'Allows user to review leadership reports and manage review escalation defaults',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', self::ROLES)
            ->pluck('id')
            ->each(function (int $roleId) use ($permissionId): void {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
