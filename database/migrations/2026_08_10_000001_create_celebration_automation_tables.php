<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celebration_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->boolean('birthdays_enabled')->default(true);
            $table->boolean('anniversaries_enabled')->default(true);
            $table->json('celebrant_channels')->nullable();
            $table->time('send_time')->default('09:00:00');
            $table->string('birthday_subject')->default('Happy Birthday, {{celebrantName}}!');
            $table->text('birthday_message')->nullable();
            $table->text('birthday_group_message')->nullable();
            $table->string('anniversary_subject')->default('Happy Wedding Anniversary, {{celebrantName}}!');
            $table->text('anniversary_message')->nullable();
            $table->text('anniversary_group_message')->nullable();
            $table->json('design')->nullable();
            $table->timestamps();
        });

        Schema::create('celebration_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('celebration_setting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('occasion_type')->index();
            $table->date('occasion_date')->index();
            $table->unsignedSmallInteger('years')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('queued')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'occasion_type', 'member_id', 'family_id', 'occasion_date'], 'celebration_dispatch_identity');
        });

        Schema::table('families', function (Blueprint $table): void {
            $table->string('celebration_photo_path')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn('celebration_photo_path');
        });
        Schema::dropIfExists('celebration_dispatches');
        Schema::dropIfExists('celebration_settings');
    }
};
