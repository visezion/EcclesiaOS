<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CommunicationWhatsAppGroup extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $table = 'communication_whatsapp_groups';

    protected $fillable = ['church_id', 'campus_id', 'ministry_id', 'provider', 'provider_group_id', 'name', 'target_scope', 'participant_count', 'invite_link', 'enabled', 'metadata', 'synced_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'metadata' => 'array',
            'synced_at' => 'datetime',
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

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }
}
