<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('event_type', 80)->nullable();
            $table->string('venue', 160)->nullable();
            $table->json('agenda')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_templates');
    }
};
