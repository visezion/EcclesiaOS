<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MessageRecipient extends Model
{
    protected $fillable = ['message_thread_id', 'recipient_type', 'recipient_id', 'label', 'resolved_count'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }
}
