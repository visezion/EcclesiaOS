<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_copilot_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->json('messages');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->index(['church_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_copilot_conversations');
    }
};
