<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leadership_report_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ministry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->string('description', 500)->nullable();
            $table->string('report_type')->index();
            $table->string('priority')->default('normal');
            $table->text('summary')->nullable();
            $table->json('metrics')->nullable();
            $table->json('action_items')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_report_templates');
    }
};
