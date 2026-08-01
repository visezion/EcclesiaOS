<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_translation_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 120);
            $table->string('book', 80);
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('preview')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('bible_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_translation_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 120);
            $table->string('title', 180);
            $table->longText('body');
            $table->json('tags')->nullable();
            $table->string('note_type', 40)->default('personal');
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('bible_highlights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_translation_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 120);
            $table->text('snippet');
            $table->string('color', 20)->default('yellow');
            $table->string('meaning', 120)->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('bible_reading_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('category', 80)->default('Topical');
            $table->unsignedSmallInteger('duration_days');
            $table->boolean('is_recommended')->default(false);
            $table->timestamps();
        });
        Schema::create('bible_reading_plan_user', function (Blueprint $table): void {
            $table->foreignId('bible_reading_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_day')->default(1);
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->primary(['bible_reading_plan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_reading_plan_user');
        Schema::dropIfExists('bible_reading_plans');
        Schema::dropIfExists('bible_highlights');
        Schema::dropIfExists('bible_notes');
        Schema::dropIfExists('bible_bookmarks');
    }
};
