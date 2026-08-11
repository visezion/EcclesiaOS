<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_sections', function (Blueprint $table): void {
            $table->foreignId('event_session_id')->nullable()->after('event_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->string('resource_reference', 500)->nullable()->after('description');
            $table->string('attachment_path')->nullable()->after('resource_reference');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->index(['event_session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('program_sections', function (Blueprint $table): void {
            $table->dropForeign(['event_session_id']);
            $table->dropIndex(['event_session_id', 'position']);
            $table->dropColumn(['event_session_id', 'resource_reference', 'attachment_path', 'attachment_name']);
        });
    }
};
