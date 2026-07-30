<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MessageAttachment extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'message_id',
        'message_draft_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sha256',
        'is_image',
    ];

    protected function casts(): array
    {
        return ['is_image' => 'boolean', 'size' => 'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(MessageDraft::class, 'message_draft_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
