<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberImportRow extends Model
{
    protected $fillable = [
        'member_import_id', 'row_number', 'source_data', 'normalized_data', 'status',
        'duplicate_action', 'matched_member_id', 'imported_member_id', 'error',
        'rollback_snapshot', 'post_import_checksum',
    ];

    protected function casts(): array
    {
        return [
            'source_data' => 'array',
            'normalized_data' => 'array',
            'rollback_snapshot' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(MemberImport::class, 'member_import_id');
    }

    public function matchedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'matched_member_id');
    }

    public function importedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'imported_member_id');
    }
}
