<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MeetingScene extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'event_session_id', 'title', 'scene_type', 'description', 'position', 'status', 'media_url', 'settings', 'is_live'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_live' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }
}
