<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Sermon;
use App\Models\YouTubeAppCredential;
use App\Models\YouTubeConnection;
use App\Services\YouTubeSermonSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SermonController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeMedia($request);
        $church = $this->churchForRequest($request);

        return view('sermons.index', [
            'church' => $church,
            'sermons' => $church->sermons()->latest('preached_at')->latest('id')->get(),
            'youtubeConnection' => $church->youtubeConnection,
            'youtubeCredentials' => $church->youtubeAppCredential,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Sermons & Media', 'url' => null],
            ],
        ]);
    }

    public function youtubeIntegration(Request $request): View
    {
        $this->authorizeSettings($request);
        $church = $this->churchForRequest($request);

        return view('administration.youtube-integration', [
            'church' => $church,
            'youtubeCredentials' => $church->youtubeAppCredential,
            'youtubeConnection' => $church->youtubeConnection,
            'canManageMedia' => $request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage media'),
            'youtubeStats' => [
                'total' => $church->sermons()->whereNotNull('youtube_video_id')->count(),
                'upcoming' => $church->sermons()->where('youtube_live_status', 'upcoming')->count(),
                'live' => $church->sermons()->where('youtube_live_status', 'live')->count(),
                'completed' => $church->sermons()->where('youtube_live_status', 'completed')->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Administration', 'url' => null],
                ['label' => 'YouTube Integration', 'url' => null],
            ],
        ]);
    }

    public function youtubeConnect(Request $request): RedirectResponse
    {
        $this->authorizeMedia($request);
        $church = $this->churchForRequest($request);
        $credentials = $church->youtubeAppCredential;
        $clientId = $credentials?->client_id ?: config('services.youtube.client_id');
        $redirectUri = $credentials?->redirect_uri ?: config('services.youtube.redirect_uri');
        if (! $clientId || (! $credentials?->client_secret && ! config('services.youtube.client_secret'))) {
            return redirect()->route('sermons.index')->with('error', 'Add your Google OAuth client ID and client secret before connecting YouTube.');
        }

        $state = bin2hex(random_bytes(32));
        $request->session()->put('youtube_oauth_state', $state);
        $request->session()->put('youtube_oauth_church_id', $church->id);
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function youtubeCallback(Request $request, YouTubeSermonSyncService $sync): RedirectResponse
    {
        $this->authorizeMedia($request);
        abort_unless(hash_equals((string) $request->session()->pull('youtube_oauth_state'), (string) $request->string('state')), 419);
        $church = Church::query()->findOrFail((int) $request->session()->pull('youtube_oauth_church_id'));
        $tokens = $sync->exchangeCode((string) $request->string('code'), $church->youtubeAppCredential);
        $existingConnection = $church->youtubeConnection;
        $connection = YouTubeConnection::query()->updateOrCreate(
            ['church_id' => $church->id],
            [
                'channel_id' => 'pending-'.$church->id,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $existingConnection?->refresh_token,
                'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600) - 60),
                'last_sync_error' => null,
            ],
        );
        try {
            $result = $sync->sync($connection);

            return redirect()->route('sermons.index')->with('status', "YouTube connected. Imported {$result['imported']} new videos and updated {$result['updated']} existing videos.");
        } catch (\Throwable $exception) {
            return redirect()->route('sermons.index')->with('error', 'YouTube connected, but the first sync failed: '.$exception->getMessage());
        }
    }

    public function youtubeCredentialsUpdate(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);
        $church = $this->churchForRequest($request);
        $existing = $church->youtubeAppCredential;
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [$existing ? 'nullable' : 'required', 'string', 'max:500'],
            'redirect_uri' => ['nullable', 'url', 'max:500'],
        ]);
        if ($existing && blank($validated['client_secret'] ?? null)) {
            unset($validated['client_secret']);
        }
        $validated['redirect_uri'] = $validated['redirect_uri'] ?? route('sermons.youtube.callback');
        YouTubeAppCredential::query()->updateOrCreate(['church_id' => $church->id], $validated + ['church_id' => $church->id]);

        return back()->with('status', 'YouTube OAuth credentials saved securely. You can now connect the channel.');
    }

    public function youtubeSync(Request $request, YouTubeSermonSyncService $sync): RedirectResponse
    {
        $this->authorizeMedia($request);
        $connection = $this->churchForRequest($request)->youtubeConnection;
        abort_unless($connection, 422, 'Connect a YouTube channel before syncing.');
        try {
            $result = $sync->sync($connection);

            return back()->with('status', "YouTube sync complete. {$result['imported']} new, {$result['updated']} updated, {$result['total']} channel videos checked.");
        } catch (\Throwable $exception) {
            return back()->with('error', 'YouTube sync failed: '.$exception->getMessage());
        }
    }

    public function youtubeTest(Request $request, YouTubeSermonSyncService $sync): RedirectResponse
    {
        $this->authorizeSettings($request);
        $connection = $this->churchForRequest($request)->youtubeConnection;
        if (! $connection) {
            return back()->with('error', 'Connect a YouTube channel before testing the API connection.');
        }
        try {
            $result = $sync->testConnection($connection);

            return back()->with('status', "Connection test passed. YouTube returned {$result['channel_title']}.");
        } catch (\Throwable $exception) {
            return back()->with('error', 'Connection test failed: '.$exception->getMessage());
        }
    }

    public function youtubeDisconnect(Request $request): RedirectResponse
    {
        $this->authorizeMedia($request);
        $this->churchForRequest($request)->youtubeConnection?->delete();

        return back()->with('status', 'YouTube channel disconnected. Imported sermons were kept in your library.');
    }

    public function create(Request $request): View
    {
        $this->authorizeMedia($request);

        return view('sermons.create', [
            'sermon' => null,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Sermons & Media', 'url' => route('sermons.index')],
                ['label' => 'Create sermon', 'url' => null],
            ],
        ]);
    }

    public function edit(Request $request, Sermon $sermon): View
    {
        $this->authorizeMedia($request);
        $this->authorizeSermon($request, $sermon);

        return view('sermons.edit', [
            'sermon' => $sermon,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Sermons & Media', 'url' => route('sermons.index')],
                ['label' => 'Edit sermon', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMedia($request);
        $church = $this->churchForRequest($request);
        $data = $this->validatedSermon($request, $church);
        if ($request->hasFile('video_file')) {
            $data['video_url'] = $request->file('video_file')->store('website/'.$church->id.'/sermons', 'public');
        }
        if ($request->hasFile('thumbnail_file')) {
            $data['thumbnail_url'] = $request->file('thumbnail_file')->store('website/'.$church->id.'/sermons', 'public');
        }
        $data['church_id'] = $church->id;
        Sermon::query()->create($data);

        return redirect()->route('sermons.index')->with('status', 'Sermon added to the library.');
    }

    public function update(Request $request, Sermon $sermon): RedirectResponse
    {
        $this->authorizeMedia($request);
        $this->authorizeSermon($request, $sermon);
        $data = $this->validatedSermon($request, $sermon->church, $sermon);
        if ($request->hasFile('video_file')) {
            $data['video_url'] = $request->file('video_file')->store('website/'.$sermon->church_id.'/sermons', 'public');
        }
        if ($request->hasFile('thumbnail_file')) {
            $data['thumbnail_url'] = $request->file('thumbnail_file')->store('website/'.$sermon->church_id.'/sermons', 'public');
        }
        $sermon->update($data);

        return redirect()->route('sermons.index')->with('status', 'Sermon updated.');
    }

    public function destroy(Request $request, Sermon $sermon): RedirectResponse
    {
        $this->authorizeMedia($request);
        $this->authorizeSermon($request, $sermon);
        $sermon->delete();

        return back()->with('status', 'Sermon archived.');
    }

    private function authorizeMedia(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage media'), 403);
    }

    private function authorizeSettings(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage settings'), 403);
    }

    private function authorizeSermon(Request $request, Sermon $sermon): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $sermon->church_id === $request->user()?->church_id, 404);
    }

    private function churchForRequest(Request $request): Church
    {
        $church = $request->user()?->church_id
            ? Church::query()->find($request->user()->church_id)
            : null;

        return $church ?? Church::query()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validatedSermon(Request $request, Church $church, ?Sermon $sermon = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'alpha_dash', 'max:120'],
            'speaker' => ['nullable', 'string', 'max:160'],
            'scripture' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'preached_at' => ['nullable', 'date'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'video_file' => ['nullable', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:51200'],
            'audio_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_file' => ['nullable', 'image', 'max:15360'],
            'status' => ['required', 'in:draft,published'],
        ]);
        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['title']);

        $duplicate = $church->sermons()->where('slug', $validated['slug'])->when($sermon, fn ($query) => $query->whereKeyNot($sermon->id))->exists();
        abort_if($duplicate, 422, 'A sermon with this URL already exists.');

        return $validated;
    }
}
