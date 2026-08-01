<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_reading_plan_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bible_reading_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('title', 180);
            $table->text('passages');
            $table->text('reflection')->nullable();
            $table->timestamps();
            $table->unique(['bible_reading_plan_id', 'day_number'], 'bible_plan_day_unique');
        });

        Schema::table('bible_reading_plan_user', function (Blueprint $table): void {
            $table->timestamp('last_read_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bible_reading_plan_user', function (Blueprint $table): void {
            $table->dropColumn('last_read_at');
        });
        Schema::dropIfExists('bible_reading_plan_days');
    }
};
