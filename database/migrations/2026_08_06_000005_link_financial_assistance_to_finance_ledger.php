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
                ->constrained('funds', 'id', 'fin_assist_fund_fk')
                ->nullOnDelete();
            $table->foreignId('finance_transaction_id')
                ->nullable()
                ->unique('fin_assist_finance_tx_unique')
                ->after('fund_id')
                ->constrained('finance_transactions', 'id', 'fin_assist_finance_tx_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_assistance_requests', function (Blueprint $table): void {
            $table->dropForeign('fin_assist_finance_tx_fk');
            $table->dropUnique('fin_assist_finance_tx_unique');
            $table->dropForeign('fin_assist_fund_fk');
            $table->dropColumn(['finance_transaction_id', 'fund_id']);
        });
    }
};
