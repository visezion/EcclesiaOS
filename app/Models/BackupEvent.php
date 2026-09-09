<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BackupEvent extends Model
{
    protected $fillable = ['church_id', 'backup_record_id', 'restore_request_id', 'user_id', 'level', 'stage', 'message', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(BackupRecord::class, 'backup_record_id');
    }
}
