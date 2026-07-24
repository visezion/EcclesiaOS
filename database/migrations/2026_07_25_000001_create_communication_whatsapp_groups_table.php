<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_whatsapp_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ministry_id')->nullable()->constrained('ministries')->nullOnDelete();
            $table->string('provider')->default('zender')->index();
            $table->string('provider_group_id');
            $table->string('name');
            $table->string('target_scope')->default('unassigned')->index();
            $table->unsignedInteger('participant_count')->nullable();
            $table->string('invite_link')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'provider', 'provider_group_id'], 'comm_wa_groups_provider_unique');
        });

        Schema::table('communication_deliveries', function (Blueprint $table): void {
            $table->foreignId('communication_whatsapp_group_id')
                ->nullable()
                ->after('member_id')
                ->constrained('communication_whatsapp_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('communication_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('communication_whatsapp_group_id');
        });

        Schema::dropIfExists('communication_whatsapp_groups');
    }
};
