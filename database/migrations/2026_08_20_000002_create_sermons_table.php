<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sermons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('speaker')->nullable();
            $table->string('scripture')->nullable();
            $table->text('summary')->nullable();
            $table->date('preached_at')->nullable()->index();
            $table->string('video_url')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('status')->default('published')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'slug']);
            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sermons');
    }
};
