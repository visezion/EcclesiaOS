<?php

declare(strict_types=1);

namespace App\Services\MemberImport;

use App\Models\MemberImport;
use Illuminate\Support\Facades\DB;

final class MemberImportStager
{
    public function __construct(private readonly MemberImportMapper $mapper) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $mapping
     */
    public function stage(MemberImport $import, array $rows, array $mapping, string $duplicateAction = 'skip'): void
    {
        $import->rows()->delete();
        foreach (array_chunk($rows, 500) as $chunkOffset => $chunk) {
            $inserts = [];
            foreach ($chunk as $index => $row) {
                $inserts[] = [
                    'member_import_id' => $import->id,
                    'row_number' => ($chunkOffset * 500) + $index + 2,
                    'source_data' => json_encode($row, JSON_THROW_ON_ERROR),
                    'status' => 'staged',
                    'duplicate_action' => $duplicateAction,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('member_import_rows')->insert($inserts);
        }
        $this->analyze($import, $mapping, $duplicateAction);
    }

    /**
     * @param  array<string, string>  $mapping
     */
    public function analyze(MemberImport $import, array $mapping, string $duplicateAction): void
    {
        $import->rows()->orderBy('id')->lazyById(200)
            ->each(fn ($row) => $this->mapper->analyze($import, $row, $mapping, $duplicateAction));
        $import->update(['status' => 'ready']);
    }
}
