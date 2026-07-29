<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('system_updates', 'rolled_back_at')) {
            Schema::table('system_updates', function (Blueprint $table): void {
                $table->timestamp('rolled_back_at')->nullable()->after('failed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_updates', 'rolled_back_at')) {
            Schema::table('system_updates', function (Blueprint $table): void {
                $table->dropColumn('rolled_back_at');
            });
        }
    }
};
