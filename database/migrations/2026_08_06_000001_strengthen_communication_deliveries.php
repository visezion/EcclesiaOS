<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_deliveries', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
            $table->string('category')->nullable()->after('event_type')->index();
            $table->longText('body')->nullable()->after('body_excerpt');
            $table->boolean('critical')->default(false)->after('category');
            $table->dateTime('available_at')->nullable()->after('critical')->index();
            $table->json('metadata')->nullable()->after('available_at');
        });
    }

    public function down(): void
    {
        Schema::table('communication_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['category', 'body', 'critical', 'available_at', 'metadata']);
        });
    }
};
