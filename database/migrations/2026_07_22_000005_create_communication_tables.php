<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category')->index();
            $table->string('trigger_event')->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('channels');
            $table->string('language', 20)->default('en')->index();
            $table->string('status')->default('active')->index();
            $table->string('approval_state')->default('approved')->index();
            $table->json('variables')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('communication_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('segment_name')->nullable();
            $table->json('audience_filters')->nullable();
            $table->json('channels');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('send_mode')->default('immediate')->index();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('communication_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_campaign_id')->constrained('communication_campaigns')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('preferences')->nullable();
            $table->string('status')->default('queued')->index();
            $table->timestamps();
            $table->unique(['communication_campaign_id', 'member_id']);
        });

        Schema::create('communication_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('communication_campaign_id')->nullable()->constrained('communication_campaigns')->nullOnDelete();
            $table->foreignId('communication_template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->index();
            $table->string('provider')->index();
            $table->string('recipient_name');
            $table->string('recipient_contact')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_excerpt')->nullable();
            $table->string('event_type')->nullable()->index();
            $table->string('status')->default('queued')->index();
            $table->string('retry_status')->default('none')->index();
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('response_code')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('sent_at')->nullable()->index();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->index();
            $table->string('provider');
            $table->boolean('enabled')->default(false)->index();
            $table->string('sender_identity')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(100);
            $table->string('retry_policy')->default('exponential');
            $table->string('webhook_secret_hash')->nullable();
            $table->dateTime('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'channel']);
        });

        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('channels');
            $table->json('categories');
            $table->string('digest_mode')->default('instant')->index();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('language', 20)->default('en');
            $table->boolean('critical_alerts')->default(true);
            $table->dateTime('opted_out_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['church_id', 'user_id']);
            $table->unique(['church_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('communication_provider_settings');
        Schema::dropIfExists('communication_deliveries');
        Schema::dropIfExists('communication_recipients');
        Schema::dropIfExists('communication_campaigns');
        Schema::dropIfExists('communication_templates');
    }
};
