<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MeetingQnaItem extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'event_session_id', 'user_id', 'guest_identity', 'author_name', 'body', 'status', 'votes_count', 'is_pinned', 'answered_at'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'answered_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }
}
