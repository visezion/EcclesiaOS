<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('email')->index();
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->nullableMorphs('subject');
            $table->string('ip_address', 45)->nullable()->after('description');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropMorphs('subject');
            $table->dropColumn(['ip_address', 'user_agent']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['status', 'last_login_at', 'password_changed_at']);
        });
    }
};
