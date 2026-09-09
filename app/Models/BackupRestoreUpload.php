<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;

final class BackupRestoreUpload extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'uuid', 'church_id', 'user_id', 'filename', 'total_size', 'chunk_size', 'total_chunks', 'received_chunks',
        'expected_checksum', 'path', 'status', 'progress', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['received_chunks' => 'array', 'total_size' => 'integer', 'expires_at' => 'datetime'];
    }
}
