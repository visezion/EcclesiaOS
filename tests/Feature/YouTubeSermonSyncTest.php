<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Sermon;
use App\Models\YouTubeConnection;
use App\Services\YouTubeSermonSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class YouTubeSermonSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_imports_normal_upcoming_live_and_completed_videos_and_is_idempotent(): void
    {
        $church = Church::factory()->create();
        $connection = YouTubeConnection::query()->create([
            'church_id' => $church->id,
            'channel_id' => 'channel-old',
            'uploads_playlist_id' => 'uploads-old',
            'access_token' => 'test-token',
            'token_expires_at' => now()->addHour(),
        ]);
        $completedOnSecondSync = false;
        Http::fake(function ($request) use (&$completedOnSecondSync) {
            if (str_contains($request->url(), '/channels')) return Http::response(['items' => [['id' => 'channel-1', 'snippet' => ['title' => 'Test Church TV'], 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'uploads-1']]]]]);
            if (str_contains($request->url(), '/playlistItems')) return Http::response(['items' => array_map(fn ($id) => ['contentDetails' => ['videoId' => $id]], ['normal-1', 'upcoming-1', 'live-1', 'completed-1'])]);
            if (str_contains($request->url(), '/videos')) {
                $liveStatus = $completedOnSecondSync ? ['liveBroadcastContent' => 'none', 'publishedAt' => '2026-08-20T09:00:00Z'] : ['liveBroadcastContent' => 'live', 'publishedAt' => '2026-08-20T09:00:00Z'];
                $liveDetails = ['actualStartTime' => '2026-08-20T09:01:00Z'] + ($completedOnSecondSync ? ['actualEndTime' => '2026-08-20T10:01:00Z'] : []);
                return Http::response(['items' => [
                    ['id' => 'normal-1', 'snippet' => ['title' => 'Sunday teaching', 'description' => 'A normal upload.', 'publishedAt' => '2026-08-10T09:00:00Z', 'thumbnails' => ['high' => ['url' => 'https://img.test/normal.jpg'], 'liveBroadcastContent' => 'none']], 'status' => []],
                    ['id' => 'upcoming-1', 'snippet' => ['title' => 'Sunday live', 'description' => 'Scheduled stream.', 'publishedAt' => '2026-08-20T09:00:00Z', 'liveBroadcastContent' => 'upcoming'], 'liveStreamingDetails' => ['scheduledStartTime' => '2026-08-30T09:00:00Z']],
                    ['id' => 'live-1', 'snippet' => ['title' => 'Now live', 'description' => 'Active stream.', 'publishedAt' => '2026-08-20T09:00:00Z'] + $liveStatus, 'liveStreamingDetails' => $liveDetails],
                    ['id' => 'completed-1', 'snippet' => ['title' => 'Completed live', 'description' => 'Finished stream.', 'publishedAt' => '2026-08-19T09:00:00Z', 'liveBroadcastContent' => 'none'], 'liveStreamingDetails' => ['actualStartTime' => '2026-08-19T09:01:00Z', 'actualEndTime' => '2026-08-19T10:01:00Z']],
                ]]);
            }
            return Http::response([], 404);
        });

        $first = app(YouTubeSermonSyncService::class)->sync($connection);
        $this->assertSame(4, $first['imported']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(['completed', 'live', 'none', 'upcoming'], Sermon::query()->where('church_id', $church->id)->orderBy('youtube_video_id')->pluck('youtube_live_status')->all());
        $synced = Sermon::query()->where('youtube_video_id', 'normal-1')->firstOrFail();
        $this->assertSame('https://www.youtube.com/watch?v=normal-1', $synced->video_url);
        $this->assertSame('https://img.test/normal.jpg', $synced->thumbnail_url);

        $completedOnSecondSync = true;
        $second = app(YouTubeSermonSyncService::class)->sync($connection->fresh());
        $this->assertSame(0, $second['imported']);
        $this->assertSame(4, $second['updated']);
        $this->assertSame('completed', Sermon::query()->where('youtube_video_id', 'live-1')->value('youtube_live_status'));
        $tested = app(YouTubeSermonSyncService::class)->testConnection($connection->fresh());
        $this->assertSame('Test Church TV', $tested['channel_title']);
        $this->assertSame('passed', $connection->fresh()->last_connection_test_status);
        $this->assertCount(4, Sermon::query()->where('church_id', $church->id)->get());
    }
}
