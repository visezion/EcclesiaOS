<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_scenes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_session_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('scene_type')->default('camera')->index();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(1)->index();
            $table->string('status')->default('ready')->index();
            $table->string('media_url')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_live')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_session_id', 'position']);
        });

        Schema::create('meeting_studio_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_session_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('livekit')->index();
            $table->foreignId('live_scene_id')->nullable()->constrained('meeting_scenes')->nullOnDelete();
            $table->foreignId('preview_scene_id')->nullable()->constrained('meeting_scenes')->nullOnDelete();
            $table->json('lower_third')->nullable();
            $table->json('scripture')->nullable();
            $table->boolean('chat_visible')->default(true);
            $table->boolean('qna_enabled')->default(true);
            $table->boolean('poll_visible')->default(true);
            $table->timestamp('countdown_ends_at')->nullable();
            $table->string('ticker_text')->nullable();
            $table->string('stream_status')->default('preview')->index();
            $table->json('audio_mixer')->nullable();
            $table->json('destinations')->nullable();
            $table->json('quick_actions')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_session_id', 'provider']);
        });

        Schema::create('meeting_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_session_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->boolean('is_open')->default(true)->index();
            $table->boolean('show_results')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_session_id', 'is_open']);
        });

        Schema::create('meeting_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_poll_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('position')->default(1)->index();
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();
        });

        Schema::create('meeting_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_identity')->nullable();
            $table->timestamps();
            $table->unique(['meeting_poll_id', 'user_id']);
            $table->unique(['meeting_poll_id', 'guest_identity']);
        });

        Schema::create('meeting_qna_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_identity')->nullable();
            $table->string('author_name');
            $table->text('body');
            $table->string('status')->default('open')->index();
            $table->unsignedInteger('votes_count')->default(0);
            $table->boolean('is_pinned')->default(false)->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_qna_items');
        Schema::dropIfExists('meeting_poll_votes');
        Schema::dropIfExists('meeting_poll_options');
        Schema::dropIfExists('meeting_polls');
        Schema::dropIfExists('meeting_studio_states');
        Schema::dropIfExists('meeting_scenes');
    }
};
