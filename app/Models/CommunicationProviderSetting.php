<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunicationProviderSetting extends Model
{
    protected $fillable = ['church_id', 'channel', 'provider', 'enabled', 'sender_identity', 'settings', 'rate_limit_per_minute', 'retry_policy', 'webhook_secret_hash', 'last_tested_at', 'last_test_status'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
