<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_app_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('client_id');
            $table->text('client_secret');
            $table->string('redirect_uri')->nullable();
            $table->timestamps();
            $table->unique('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_app_credentials');
    }
};
