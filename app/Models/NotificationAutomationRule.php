<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationAutomationRule extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'communication_template_id', 'event_type', 'name', 'category', 'enabled', 'channels', 'audience', 'reminder_minutes', 'critical', 'last_run_at', 'last_status', 'last_recipient_count', 'last_error'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channels' => 'array',
            'critical' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }
}
