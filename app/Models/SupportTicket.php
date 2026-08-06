<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupportTicket extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'reference',
        'central_id',
        'sync_status',
        'sync_error',
        'synced_at',
        'church_id',
        'created_by',
        'assigned_to',
        'category',
        'priority',
        'status',
        'progress',
        'subject',
        'description',
        'expected_outcome',
        'page_url',
        'browser',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SupportTicketActivity::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    public function syncEvents(): HasMany
    {
        return $this->hasMany(CentralSupportSyncEvent::class);
    }
}
