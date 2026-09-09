<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BackupRestoreRequest extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'uuid', 'church_id', 'backup_record_id', 'restore_upload_id', 'requested_by', 'mode', 'status', 'stage',
        'progress', 'validation', 'safety_backup_id', 'validated_at', 'started_at', 'completed_at', 'failed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'validation' => 'array', 'validated_at' => 'datetime', 'started_at' => 'datetime',
            'completed_at' => 'datetime', 'failed_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(BackupRecord::class, 'backup_record_id');
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(BackupRestoreUpload::class, 'restore_upload_id');
    }
}
