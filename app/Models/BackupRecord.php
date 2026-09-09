<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BackupRecord extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'uuid', 'church_id', 'created_by', 'type', 'source', 'status', 'stage', 'progress', 'disk', 'path',
        'filename', 'size_bytes', 'checksum', 'encrypted', 'manifest', 'destination_ids', 'copies', 'file_count',
        'record_count', 'started_at', 'completed_at', 'verified_at', 'failed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
            'manifest' => 'array',
            'destination_ids' => 'array',
            'copies' => 'array',
            'size_bytes' => 'integer',
            'file_count' => 'integer',
            'record_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BackupEvent::class);
    }
}
