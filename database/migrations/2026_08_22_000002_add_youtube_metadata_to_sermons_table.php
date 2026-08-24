<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermons', function (Blueprint $table): void {
            $table->string('youtube_video_id')->nullable()->after('slug');
            $table->string('youtube_live_status')->nullable()->after('youtube_video_id');
            $table->timestamp('youtube_published_at')->nullable()->after('youtube_live_status');
            $table->index(['church_id', 'youtube_live_status']);
            $table->unique(['church_id', 'youtube_video_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sermons', function (Blueprint $table): void {
            $table->dropUnique(['church_id', 'youtube_video_id']);
            $table->dropIndex(['church_id', 'youtube_live_status']);
            $table->dropColumn(['youtube_video_id', 'youtube_live_status', 'youtube_published_at']);
        });
    }
};
