<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('youtube_connections', function (Blueprint $table): void {
            $table->timestamp('last_sync_started_at')->nullable()->after('last_synced_at');
            $table->timestamp('last_sync_completed_at')->nullable()->after('last_sync_started_at');
            $table->unsignedInteger('last_sync_total')->nullable()->after('last_sync_completed_at');
            $table->unsignedInteger('last_sync_imported')->nullable()->after('last_sync_total');
            $table->unsignedInteger('last_sync_updated')->nullable()->after('last_sync_imported');
            $table->unsignedInteger('last_sync_duration_ms')->nullable()->after('last_sync_updated');
            $table->timestamp('last_connection_tested_at')->nullable()->after('last_sync_duration_ms');
            $table->string('last_connection_test_status')->nullable()->after('last_connection_tested_at');
            $table->text('last_connection_test_message')->nullable()->after('last_connection_test_status');
        });
    }

    public function down(): void
    {
        Schema::table('youtube_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'last_sync_started_at', 'last_sync_completed_at', 'last_sync_total', 'last_sync_imported',
                'last_sync_updated', 'last_sync_duration_ms', 'last_connection_tested_at',
                'last_connection_test_status', 'last_connection_test_message',
            ]);
        });
    }
};
