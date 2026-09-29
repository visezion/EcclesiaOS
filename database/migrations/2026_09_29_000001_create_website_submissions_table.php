<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 120);
            $table->string('email', 180)->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('message');
            $table->string('status', 30)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('private_notes')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_submissions');
    }
};
