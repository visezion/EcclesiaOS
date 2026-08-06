<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('communication_template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
            $table->string('event_type');
            $table->string('name');
            $table->string('category')->index();
            $table->boolean('enabled')->default(true)->index();
            $table->json('channels');
            $table->string('audience')->default('event_recipients');
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->boolean('critical')->default(false);
            $table->dateTime('last_run_at')->nullable();
            $table->string('last_status')->nullable();
            $table->unsignedInteger('last_recipient_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_automation_rules');
    }
};
