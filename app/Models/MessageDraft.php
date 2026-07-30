<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MessageDraft extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'user_id',
        'message_thread_id',
        'subject',
        'body',
        'body_html',
        'recipients',
        'conversation_type',
        'linked_type',
        'linked_id',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'body_html' => 'encrypted',
            'recipients' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
