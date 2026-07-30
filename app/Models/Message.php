<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Message extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'message_thread_id',
        'sender_id',
        'body',
        'body_html',
        'is_internal_note',
        'status',
        'scheduled_at',
        'sent_at',
        'edited_at',
        'forwarded_from_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'body_html' => 'encrypted',
            'is_internal_note' => 'boolean',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'edited_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forwarded_from_id');
    }
}
