<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('priority')->default('medium')->index();
            $table->string('status')->default('pending')->index();
            $table->string('next_action')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'campus_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_tasks');
    }
};
