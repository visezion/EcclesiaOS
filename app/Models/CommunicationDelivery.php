<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunicationDelivery extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'communication_campaign_id', 'communication_template_id', 'member_id', 'communication_whatsapp_group_id', 'channel', 'provider', 'recipient_name', 'recipient_contact', 'subject', 'body_excerpt', 'event_type', 'status', 'retry_status', 'attempt', 'latency_ms', 'provider_message_id', 'response_code', 'error', 'sent_at', 'delivered_at', 'opened_at', 'read_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'communication_campaign_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function whatsappGroup(): BelongsTo
    {
        return $this->belongsTo(CommunicationWhatsAppGroup::class, 'communication_whatsapp_group_id');
    }
}
