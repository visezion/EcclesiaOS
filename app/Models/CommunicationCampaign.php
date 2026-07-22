<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CommunicationCampaign extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'template_id', 'created_by', 'name', 'segment_name', 'audience_filters', 'channels', 'subject', 'body', 'send_mode', 'scheduled_at', 'status', 'recipient_count', 'sent_count', 'delivered_count', 'failed_count', 'opened_count', 'clicked_count'];

    protected function casts(): array
    {
        return [
            'audience_filters' => 'array',
            'channels' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CommunicationDelivery::class);
    }
}
