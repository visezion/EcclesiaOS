<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->default('stripe')->index();
            $table->string('provider_session_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('reference')->unique();
            $table->string('status', 32)->default('initiated')->index();
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('donor_name')->nullable();
            $table->string('donor_email')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'status', 'created_at'], 'gateway_transactions_church_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_transactions');
    }
};
