<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BackupSchedule extends Model
{
    protected $fillable = [
        'church_id', 'enabled', 'backup_type', 'frequency', 'days', 'run_at', 'timezone', 'destination_ids',
        'keep_daily', 'keep_weekly', 'keep_monthly', 'keep_yearly', 'last_started_at', 'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'days' => 'array',
            'destination_ids' => 'array',
            'last_started_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
