<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiCopilotConversation extends Model
{
    protected $fillable = ['church_id', 'user_id', 'title', 'messages', 'last_message_at'];

    protected function casts(): array
    {
        return ['messages' => 'array', 'last_message_at' => 'datetime'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
