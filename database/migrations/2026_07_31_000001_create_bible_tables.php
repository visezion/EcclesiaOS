<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('abbreviation', 20);
            $table->string('language', 80)->default('English');
            $table->text('description')->nullable();
            $table->string('copyright', 255)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['church_id', 'abbreviation']);
        });

        Schema::create('bible_verses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bible_translation_id')->constrained('bible_translations')->cascadeOnDelete();
            $table->string('book', 80);
            $table->string('book_slug', 80);
            $table->string('testament', 20)->default('new');
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');
            $table->timestamps();
            $table->unique(['bible_translation_id', 'book_slug', 'chapter', 'verse'], 'bible_verse_reference_unique');
            $table->index(['bible_translation_id', 'book_slug', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_verses');
        Schema::dropIfExists('bible_translations');
    }
};
