<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_assistance_requests', function (Blueprint $table): void {
            $table->foreignId('fund_id')
                ->nullable()
                ->after('disbursement_reference')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('finance_transaction_id')
                ->nullable()
                ->unique()
                ->after('fund_id')
                ->constrained('finance_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_assistance_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('finance_transaction_id');
            $table->dropConstrainedForeignId('fund_id');
        });
    }
};
