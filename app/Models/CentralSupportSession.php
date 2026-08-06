<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CentralSupportSession extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'support_ticket_id',
        'approved_by',
        'support_user_id',
        'grant_token_hash',
        'login_token_hash',
        'central_agent_id',
        'agent_name',
        'agent_email',
        'scopes',
        'status',
        'expires_at',
        'exchanged_at',
        'started_at',
        'last_seen_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'exchanged_at' => 'datetime',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supportUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_user_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['pending', 'ready', 'active'], true)
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
