<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MeetingStudioState extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'event_session_id', 'provider', 'live_scene_id', 'preview_scene_id', 'lower_third', 'scripture', 'chat_visible', 'qna_enabled', 'poll_visible', 'countdown_ends_at', 'ticker_text', 'stream_status', 'audio_mixer', 'destinations', 'quick_actions', 'updated_by'];

    protected function casts(): array
    {
        return [
            'lower_third' => 'array',
            'scripture' => 'array',
            'chat_visible' => 'boolean',
            'qna_enabled' => 'boolean',
            'poll_visible' => 'boolean',
            'countdown_ends_at' => 'datetime',
            'audio_mixer' => 'array',
            'destinations' => 'array',
            'quick_actions' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function liveScene(): BelongsTo
    {
        return $this->belongsTo(MeetingScene::class, 'live_scene_id');
    }

    public function previewScene(): BelongsTo
    {
        return $this->belongsTo(MeetingScene::class, 'preview_scene_id');
    }
}
