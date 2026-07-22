<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserNotificationPreference extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'user_id', 'member_id', 'channels', 'categories', 'digest_mode', 'quiet_hours_start', 'quiet_hours_end', 'language', 'critical_alerts', 'opted_out_at'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'categories' => 'array',
            'critical_alerts' => 'boolean',
            'opted_out_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
