<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberHistoryEntry extends Model
{
    protected $fillable = [
        'church_id', 'member_id', 'member_import_id', 'event_type', 'status',
        'occurred_at', 'source_reference', 'description', 'metadata',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
