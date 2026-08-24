<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\Sermon;
use App\Models\YouTubeConnection;
use App\Models\YouTubeAppCredential;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class YouTubeSermonSyncService
{
    private const API = 'https://www.googleapis.com/youtube/v3';

    /** @return array{connection: YouTubeConnection, imported: int, updated: int, total: int} */
    public function sync(YouTubeConnection $connection): array
    {
        $startedAt = microtime(true);
        $connection->update(['last_sync_started_at' => now(), 'last_sync_error' => null]);
        try {
            $token = $this->validAccessToken($connection);
            $channel = $this->youtube($token, 'channels', [
                'part' => 'snippet,contentDetails',
                'mine' => 'true',
            ])['items'][0] ?? null;
            if (!$channel) {
                throw new RuntimeException('YouTube did not return a channel for this connection.');
            }

            $connection->update([
                'channel_id' => (string) $channel['id'],
                'channel_title' => data_get($channel, 'snippet.title'),
                'uploads_playlist_id' => data_get($channel, 'contentDetails.relatedPlaylists.uploads'),
                'last_sync_error' => null,
            ]);

            $videoIds = $this->uploadVideoIds($token, (string) $connection->uploads_playlist_id);
            $imported = 0;
            $updated = 0;
            foreach (array_chunk($videoIds, 50) as $ids) {
                $videos = $this->youtube($token, 'videos', [
                    'part' => 'snippet,contentDetails,liveStreamingDetails,status',
                    'id' => implode(',', $ids),
                ])['items'] ?? [];
                foreach ($videos as $video) {
                    $sermon = Sermon::query()->where('church_id', $connection->church_id)->where('youtube_video_id', $video['id'])->first();
                    $attributes = $this->attributesFor($connection->church_id, $video);
                    if ($sermon) {
                        $sermon->update($attributes);
                        $updated++;
                    } else {
                        Sermon::query()->create($attributes);
                        $imported++;
                    }
                }
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $connection->update([
                'last_synced_at' => now(),
                'last_sync_completed_at' => now(),
                'last_sync_total' => count($videoIds),
                'last_sync_imported' => $imported,
                'last_sync_updated' => $updated,
                'last_sync_duration_ms' => $durationMs,
                'last_sync_error' => null,
            ]);

            return compact('connection', 'imported', 'updated') + ['total' => count($videoIds), 'duration_ms' => $durationMs];
        } catch (\Throwable $exception) {
            $connection->update([
                'last_sync_completed_at' => now(),
                'last_sync_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'last_sync_error' => Str::limit($exception->getMessage(), 1000),
            ]);
            throw $exception;
        }
    }

    /** @return array{channel_title: string, channel_id: string} */
    public function testConnection(YouTubeConnection $connection): array
    {
        try {
            $token = $this->validAccessToken($connection);
            $channel = $this->youtube($token, 'channels', [
                'part' => 'snippet,contentDetails',
                'mine' => 'true',
            ])['items'][0] ?? null;
            if (!$channel) throw new RuntimeException('YouTube did not return a channel for this connection.');
            $result = ['channel_title' => (string) data_get($channel, 'snippet.title', 'YouTube channel'), 'channel_id' => (string) $channel['id']];
            $connection->update([
                'last_connection_tested_at' => now(),
                'last_connection_test_status' => 'passed',
                'last_connection_test_message' => 'YouTube API responded successfully.',
            ]);
            return $result;
        } catch (\Throwable $exception) {
            $connection->update([
                'last_connection_tested_at' => now(),
                'last_connection_test_status' => 'failed',
                'last_connection_test_message' => Str::limit($exception->getMessage(), 1000),
            ]);
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function exchangeCode(string $code, ?YouTubeAppCredential $credentials = null): array
    {
        $credentials ??= new YouTubeAppCredential([
            'client_id' => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
            'redirect_uri' => config('services.youtube.redirect_uri'),
        ]);
        return Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $credentials->client_id,
            'client_secret' => $credentials->client_secret,
            'redirect_uri' => $credentials->redirect_uri ?: config('services.youtube.redirect_uri'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();
    }

    private function validAccessToken(YouTubeConnection $connection): string
    {
        if ($connection->access_token && (!$connection->token_expires_at || $connection->token_expires_at->isFuture())) {
            return $connection->access_token;
        }
        if (!$connection->refresh_token) {
            throw new RuntimeException('The YouTube connection has expired. Please connect it again.');
        }

        $credentials = $connection->church->youtubeAppCredential;
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $credentials?->client_id ?: config('services.youtube.client_id'),
            'client_secret' => $credentials?->client_secret ?: config('services.youtube.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw()->json();
        $connection->update([
            'access_token' => $token['access_token'],
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600) - 60),
        ]);

        return (string) $token['access_token'];
    }

    /** @return list<string> */
    private function uploadVideoIds(string $token, string $playlistId): array
    {
        $ids = [];
        $pageToken = null;
        do {
            $query = ['part' => 'contentDetails', 'playlistId' => $playlistId, 'maxResults' => 50];
            if ($pageToken) $query['pageToken'] = $pageToken;
            $page = $this->youtube($token, 'playlistItems', $query);
            foreach ($page['items'] ?? [] as $item) {
                if (!empty($item['contentDetails']['videoId'])) $ids[] = $item['contentDetails']['videoId'];
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken);

        return array_values(array_unique($ids));
    }

    /** @return array<string, mixed> */
    private function youtube(string $token, string $endpoint, array $query): array
    {
        return Http::withToken($token)->timeout(30)->get(self::API.'/'.$endpoint, $query)->throw()->json();
    }

    /** @return array<string, mixed> */
    private function attributesFor(int $churchId, array $video): array
    {
        $snippet = $video['snippet'] ?? [];
        $live = $video['liveStreamingDetails'] ?? [];
        $liveStatus = $this->liveStatus($snippet, $live);
        $publishedAt = $snippet['publishedAt'] ?? $live['scheduledStartTime'] ?? null;
        $date = $publishedAt ? CarbonImmutable::parse($publishedAt) : null;

        return [
            'church_id' => $churchId,
            'youtube_video_id' => $video['id'],
            'youtube_live_status' => $liveStatus,
            'youtube_published_at' => $publishedAt,
            'title' => Str::limit((string) ($snippet['title'] ?? 'YouTube video'), 180, ''),
            'slug' => Str::slug(($snippet['title'] ?? 'youtube-video').'-'.$video['id']),
            'speaker' => null,
            'scripture' => null,
            'summary' => Str::limit((string) ($snippet['description'] ?? ''), 3000, ''),
            'preached_at' => $date?->toDateString(),
            'video_url' => 'https://www.youtube.com/watch?v='.$video['id'],
            'thumbnail_url' => data_get($snippet, 'thumbnails.high.url') ?: data_get($snippet, 'thumbnails.medium.url') ?: data_get($snippet, 'thumbnails.default.url'),
            'status' => 'published',
        ];
    }

    private function liveStatus(array $snippet, array $live): string
    {
        $broadcast = $snippet['liveBroadcastContent'] ?? 'none';
        if ($broadcast === 'upcoming' || (!empty($live['scheduledStartTime']) && empty($live['actualStartTime']))) return 'upcoming';
        if (!empty($live['actualEndTime'])) return 'completed';
        if ($broadcast === 'live' || !empty($live['actualStartTime'])) return 'live';
        return 'none';
    }
}
