<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->text('push_token')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn('push_token');
        });
    }
};
