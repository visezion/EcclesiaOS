<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->foreignId('ministry_id')->nullable()->after('fund_id')->constrained()->nullOnDelete();
            $table->string('giving_source')->default('member')->index()->after('method');
            $table->string('giving_frequency')->default('one_time')->index()->after('giving_source');
            $table->text('notes')->nullable()->after('reference');
        });

        Schema::create('finance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ministry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('category')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method')->nullable();
            $table->string('frequency')->default('one_time')->index();
            $table->dateTime('occurred_at')->index();
            $table->string('reference')->nullable()->index();
            $table->string('vendor_or_source')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('posted')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'campus_id', 'type', 'occurred_at']);
            $table->index(['ministry_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ministry_id');
            $table->dropColumn(['giving_source', 'giving_frequency', 'notes']);
        });
    }
};
