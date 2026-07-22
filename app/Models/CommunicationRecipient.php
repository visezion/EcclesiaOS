<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunicationRecipient extends Model
{
    protected $fillable = ['communication_campaign_id', 'member_id', 'user_id', 'name', 'email', 'phone', 'preferences', 'status'];

    protected function casts(): array
    {
        return ['preferences' => 'array'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'communication_campaign_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
