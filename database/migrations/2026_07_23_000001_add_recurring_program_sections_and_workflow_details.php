<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('approvals', 'action')) {
                $table->string('action')->nullable()->after('approvable_id')->index();
            }
            if (! Schema::hasColumn('approvals', 'payload')) {
                $table->json('payload')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('approvals', 'submitted_at')) {
                $table->dateTime('submitted_at')->nullable()->after('payload');
            }
            if (! Schema::hasColumn('approvals', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('approved_at');
            }
        });

        Schema::create('event_recurrence_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('frequency')->index();
            $table->unsignedTinyInteger('interval')->default(1);
            $table->json('days_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('starts_on')->index();
            $table->date('ends_on')->nullable()->index();
            $table->unsignedSmallInteger('max_occurrences')->nullable();
            $table->time('starts_at');
            $table->time('ends_at')->nullable();
            $table->string('timezone')->default(config('app.timezone'));
            $table->string('meeting_type')->default('physical')->index();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->json('meeting_links')->nullable();
            $table->string('status')->default('pending_approval')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'event_id', 'status']);
        });

        Schema::table('event_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_sessions', 'recurrence_rule_id')) {
                $table->foreignId('recurrence_rule_id')->nullable()->after('event_id')->constrained('event_recurrence_rules')->nullOnDelete();
            }
        });

        Schema::create('program_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('section_type')->default('custom')->index();
            $table->unsignedSmallInteger('position')->default(1)->index();
            $table->time('planned_start_time')->nullable();
            $table->unsignedSmallInteger('planned_duration_minutes')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['program_id', 'event_id', 'position']);
        });

        Schema::create('program_section_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_title');
            $table->text('responsibility_notes')->nullable();
            $table->dateTime('call_time')->nullable();
            $table->string('status')->default('pending_approval')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('declined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['program_section_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_section_assignments');
        Schema::dropIfExists('program_sections');
        Schema::table('event_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('event_sessions', 'recurrence_rule_id')) {
                $table->dropConstrainedForeignId('recurrence_rule_id');
            }
        });
        Schema::dropIfExists('event_recurrence_rules');
        Schema::table('approvals', function (Blueprint $table): void {
            foreach (['rejected_at', 'submitted_at', 'payload', 'action'] as $column) {
                if (Schema::hasColumn('approvals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
