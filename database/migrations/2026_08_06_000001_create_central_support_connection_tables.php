<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->uuid('central_id')->nullable()->unique()->after('reference');
            $table->string('sync_status', 24)->default('local')->index()->after('central_id');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->timestamp('synced_at')->nullable()->after('sync_error');
        });

        Schema::create('central_support_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('support_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('grant_token_hash', 64)->unique();
            $table->string('login_token_hash', 64)->nullable()->unique();
            $table->string('central_agent_id')->nullable()->index();
            $table->string('agent_name')->nullable();
            $table->string('agent_email')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('exchanged_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('central_support_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 80)->index();
            $table->json('payload');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('central_support_inbound_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 80)->index();
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_support_inbound_events');
        Schema::dropIfExists('central_support_sync_events');
        Schema::dropIfExists('central_support_sessions');

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropUnique(['central_id']);
            $table->dropColumn(['central_id', 'sync_status', 'sync_error', 'synced_at']);
        });
    }
};
