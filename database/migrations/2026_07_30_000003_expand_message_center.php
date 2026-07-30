<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_threads', function (Blueprint $table): void {
            $table->string('type', 32)->default('private')->after('subject')->index();
            $table->string('status', 24)->default('active')->after('type')->index();
            $table->string('permission_scope', 32)->default('church')->after('status');
            $table->string('linked_type', 64)->nullable()->after('permission_scope');
            $table->unsignedBigInteger('linked_id')->nullable()->after('linked_type');
            $table->string('linked_label')->nullable()->after('linked_id');
            $table->boolean('replies_restricted')->default(false)->after('linked_label');
            $table->json('metadata')->nullable()->after('replies_restricted');
            $table->foreignId('closed_by')->nullable()->after('last_message_at')->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->timestamp('retention_until')->nullable()->after('closed_at');
            $table->softDeletes();
            $table->index(['linked_type', 'linked_id']);
        });

        Schema::table('message_thread_user', function (Blueprint $table): void {
            $table->string('participant_role', 24)->default('member')->after('user_id');
            $table->string('notification_level', 24)->default('all')->after('archived_at');
            $table->timestamp('joined_at')->nullable()->after('notification_level');
            $table->timestamp('left_at')->nullable()->after('joined_at');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->longText('body_html')->nullable()->after('body');
            $table->string('status', 24)->default('sent')->after('is_internal_note')->index();
            $table->timestamp('scheduled_at')->nullable()->after('status')->index();
            $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            $table->timestamp('edited_at')->nullable()->after('sent_at');
            $table->foreignId('forwarded_from_id')->nullable()->after('edited_at')->constrained('messages')->nullOnDelete();
            $table->json('metadata')->nullable()->after('forwarded_from_id');
            $table->softDeletes();
        });

        Schema::create('message_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->string('recipient_type', 32);
            $table->unsignedBigInteger('recipient_id');
            $table->string('label');
            $table->unsignedInteger('resolved_count')->default(0);
            $table->timestamps();
            $table->unique(['message_thread_id', 'recipient_type', 'recipient_id'], 'message_recipient_unique');
            $table->index(['recipient_type', 'recipient_id']);
        });

        Schema::create('message_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_thread_id')->nullable()->constrained('message_threads')->cascadeOnDelete();
            $table->string('subject', 160)->nullable();
            $table->longText('body')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('recipients')->nullable();
            $table->string('conversation_type', 32)->default('private');
            $table->string('linked_type', 64)->nullable();
            $table->unsignedBigInteger('linked_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'user_id', 'updated_at']);
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->cascadeOnDelete();
            $table->foreignId('message_draft_id')->nullable()->constrained('message_drafts')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->boolean('is_image')->default(false);
            $table->timestamps();
            $table->index(['church_id', 'created_at']);
        });

        Schema::create('message_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_thread_id')->nullable()->constrained('message_threads')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['message_thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_audit_events');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_drafts');
        Schema::dropIfExists('message_recipients');

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['forwarded_from_id']);
            $table->dropColumn(['body_html', 'status', 'scheduled_at', 'sent_at', 'edited_at', 'forwarded_from_id', 'metadata', 'deleted_at']);
        });

        Schema::table('message_thread_user', function (Blueprint $table): void {
            $table->dropColumn(['participant_role', 'notification_level', 'joined_at', 'left_at']);
        });

        Schema::table('message_threads', function (Blueprint $table): void {
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['type', 'status', 'permission_scope', 'linked_type', 'linked_id', 'linked_label', 'replies_restricted', 'metadata', 'closed_by', 'closed_at', 'retention_until', 'deleted_at']);
        });
    }
};
