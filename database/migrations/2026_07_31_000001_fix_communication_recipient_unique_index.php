<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communication_recipients')) {
            return;
        }

        $hasUniqueIndex = collect(Schema::getIndexes('communication_recipients'))
            ->contains(function (array $index): bool {
                return (bool) ($index['unique'] ?? false)
                    && array_values($index['columns'] ?? []) === ['communication_campaign_id', 'member_id'];
            });

        if (! $hasUniqueIndex) {
            Schema::table('communication_recipients', function (Blueprint $table): void {
                $table->unique(
                    ['communication_campaign_id', 'member_id'],
                    'communication_recipients_campaign_member_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('communication_recipients')) {
            return;
        }

        $hasNamedIndex = collect(Schema::getIndexes('communication_recipients'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'communication_recipients_campaign_member_unique');

        if ($hasNamedIndex) {
            Schema::table('communication_recipients', function (Blueprint $table): void {
                $table->dropUnique('communication_recipients_campaign_member_unique');
            });
        }
    }
};
