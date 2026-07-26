<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ministryLeaderRoleId = DB::table('roles')->where('name', 'Ministry Leader')->value('id');

        if ($ministryLeaderRoleId === null) {
            return;
        }

        $broadFinancePermissionIds = DB::table('permissions')
            ->whereIn('name', ['manage finance', 'view finance', 'record finance entries'])
            ->pluck('id');

        DB::table('permission_role')
            ->where('role_id', $ministryLeaderRoleId)
            ->whereIn('permission_id', $broadFinancePermissionIds)
            ->delete();
    }

    public function down(): void
    {
        // This normalization intentionally does not restore broad finance access.
    }
};
