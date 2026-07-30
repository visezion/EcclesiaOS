<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_thread_user', function (Blueprint $table): void {
            $table->timestamp('starred_at')->nullable()->after('last_read_at');
            $table->timestamp('archived_at')->nullable()->after('starred_at');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->boolean('is_internal_note')->default(false)->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn('is_internal_note');
        });

        Schema::table('message_thread_user', function (Blueprint $table): void {
            $table->dropColumn(['starred_at', 'archived_at']);
        });
    }
};
