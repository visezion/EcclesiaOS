<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CommunicationTemplate extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'owner_id', 'name', 'category', 'trigger_event', 'subject', 'body', 'channels', 'language', 'status', 'approval_state', 'variables', 'usage_count', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'variables' => 'array',
            'last_used_at' => 'datetime',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(CommunicationCampaign::class, 'template_id');
    }
}
