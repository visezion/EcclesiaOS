<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view finance' => 'View scoped finance reports and giving records without changing records.',
        'record finance entries' => 'Record scoped donations, income, and expenses without full finance administration.',
        'view ministry finance' => 'View limited ministry contribution records without campus or organization finance totals.',
        'record ministry contributions' => 'Record ministry or department contribution records without full finance access.',
    ];

    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            if (! Schema::hasColumn('donations', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('member_id')->constrained('users')->nullOnDelete();
            }
        });

        foreach (self::PERMISSIONS as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->attachPermissions('Super Administrator', array_keys(self::PERMISSIONS));
        $this->attachPermissions('Finance Officer', ['view finance', 'record finance entries']);
        $this->attachPermissions('Senior Pastor', ['view finance']);
        $this->attachPermissions('Branch Pastor', ['view finance']);
        $this->attachPermissions('Ministry Leader', ['view ministry finance', 'record ministry contributions']);
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('donations', function (Blueprint $table): void {
            if (Schema::hasColumn('donations', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });
    }

    private function attachPermissions(string $roleName, array $permissionNames): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if ($roleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
