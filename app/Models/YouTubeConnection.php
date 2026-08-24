<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class YouTubeConnection extends Model
{
    protected $table = 'youtube_connections';

    protected $fillable = [
        'church_id', 'channel_id', 'channel_title', 'uploads_playlist_id',
        'access_token', 'refresh_token', 'token_expires_at', 'last_synced_at', 'last_sync_error',
        'last_sync_started_at', 'last_sync_completed_at', 'last_sync_total', 'last_sync_imported',
        'last_sync_updated', 'last_sync_duration_ms', 'last_connection_tested_at',
        'last_connection_test_status', 'last_connection_test_message',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_sync_started_at' => 'datetime',
            'last_sync_completed_at' => 'datetime',
            'last_connection_tested_at' => 'datetime',
            'last_sync_total' => 'integer',
            'last_sync_imported' => 'integer',
            'last_sync_updated' => 'integer',
            'last_sync_duration_ms' => 'integer',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
