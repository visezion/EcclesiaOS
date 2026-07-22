<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('upcoming')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'campus_id', 'status']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('program_id')->nullable()->after('campus_id')->constrained()->nullOnDelete();
            $table->text('description')->nullable()->after('title');
            $table->string('event_type')->nullable()->after('description')->index();
        });

        Schema::create('event_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('session_date')->index();
            $table->time('starts_at');
            $table->time('ends_at')->nullable();
            $table->string('timezone')->default(config('app.timezone'));
            $table->string('meeting_type')->default('physical')->index();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->json('meeting_links')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'campus_id', 'session_date']);
        });

        Schema::create('meeting_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'provider']);
        });

        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->json('methods')->nullable();
            $table->string('verification_policy')->default('any_one');
            $table->boolean('require_authenticated')->default(true);
            $table->boolean('allow_guests')->default(false);
            $table->decimal('geo_latitude', 10, 7)->nullable();
            $table->decimal('geo_longitude', 10, 7)->nullable();
            $table->unsignedInteger('geo_radius_meters')->default(100);
            $table->unsignedInteger('expected_attendance')->default(0);
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('attendance_session_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            $table->string('final_method')->nullable()->after('status');
            $table->json('verification_summary')->nullable()->after('final_method');
            $table->unique(['attendance_session_id', 'member_id'], 'attendance_records_session_member_unique');
        });

        Schema::create('attendance_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method')->index();
            $table->string('provider')->nullable()->index();
            $table->string('status')->default('success')->index();
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['attendance_session_id', 'member_id', 'method'], 'attendance_verifications_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_verifications');
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropUnique('attendance_records_session_member_unique');
            $table->dropConstrainedForeignId('attendance_session_id');
            $table->dropColumn(['final_method', 'verification_summary']);
        });
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('meeting_integrations');
        Schema::dropIfExists('event_sessions');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn(['description', 'event_type']);
        });
        Schema::dropIfExists('programs');
    }
};
