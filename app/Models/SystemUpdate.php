<?php

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SystemUpdate extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'version',
        'tag',
        'name',
        'status',
        'current_version',
        'release_url',
        'asset_name',
        'asset_api_url',
        'asset_download_url',
        'asset_digest',
        'asset_size',
        'changelog',
        'approved_by',
        'detected_at',
        'approved_at',
        'started_at',
        'installed_at',
        'failed_at',
        'rolled_back_at',
        'error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'asset_size' => 'integer',
            'detected_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'installed_at' => 'datetime',
            'failed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->approver();
    }

    public function isAvailable(): bool
    {
        return version_compare($this->version, ltrim((string) config('updater.current_version'), 'vV'), '>')
            && ! in_array($this->status, ['completed', 'rolled_back', 'skipped'], true);
    }
}
