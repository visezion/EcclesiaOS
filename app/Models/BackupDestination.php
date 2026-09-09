<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BackupDestination extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'name', 'driver', 'configuration', 'is_active', 'is_offsite', 'last_tested_at', 'last_test_status', 'last_error'];

    protected $hidden = ['configuration'];

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
            'is_active' => 'boolean',
            'is_offsite' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
