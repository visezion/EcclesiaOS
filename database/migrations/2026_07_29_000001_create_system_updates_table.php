<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_updates', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 40);
            $table->string('tag', 80)->unique();
            $table->string('name')->nullable();
            $table->string('status', 30)->default('detected')->index();
            $table->string('current_version', 40);
            $table->string('release_url')->nullable();
            $table->string('asset_name')->nullable();
            $table->text('asset_api_url')->nullable();
            $table->text('asset_download_url')->nullable();
            $table->string('asset_digest', 80)->nullable();
            $table->unsignedBigInteger('asset_size')->nullable();
            $table->longText('changelog')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->longText('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
