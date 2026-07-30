<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MessageThread extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'created_by',
        'subject',
        'type',
        'status',
        'permission_scope',
        'linked_type',
        'linked_id',
        'linked_label',
        'replies_restricted',
        'metadata',
        'last_message_at',
        'closed_by',
        'closed_at',
        'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'replies_restricted' => 'boolean',
            'metadata' => 'array',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'retention_until' => 'datetime',
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

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_thread_user')
            ->withPivot(['participant_role', 'last_read_at', 'starred_at', 'archived_at', 'notification_level', 'joined_at', 'left_at'])
            ->wherePivotNull('left_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->where('status', 'sent')->with('sender')->oldest();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->where('status', 'sent')->latestOfMany();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(MessageAuditEvent::class);
    }
}
