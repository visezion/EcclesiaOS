@php
    $sermonLimit = ($component['sermon_limit'] ?? 'all') === 'all' ? null : (int) $component['sermon_limit'];
    $sermonItems = collect($sermons ?? [])->take($sermonLimit);
@endphp
<section class="content-sermons-widget">
    <div class="content-sermon-list">
        @forelse ($sermonItems as $sermon)
            @php
                $videoUrl = (string) ($sermon->video_url ?? '');
                $youtubeId = $sermon->youtube_video_id;
                if (!$youtubeId && preg_match('/(?:youtu\\.be\\/|youtube\\.com\\/(?:watch\\?v=|live\\/|embed\\/))([^?&\\/]+)/i', $videoUrl, $matches)) $youtubeId = $matches[1];
                $thumbnailUrl = $assetUrl($sermon->thumbnail_url ?? null) ?: ($youtubeId ? 'https://img.youtube.com/vi/'.$youtubeId.'/hqdefault.jpg' : null);
                $detailUrl = route('website.public.sermons.show', ['church' => $church->slug, 'sermon' => $sermon]);
            @endphp
            <article class="content-sermon-card">
                @if ($thumbnailUrl)
                    <a class="content-sermon-player content-sermon-player-link" href="{{ $detailUrl }}" aria-label="Open sermon"><img src="{{ $thumbnailUrl }}" alt="{{ $sermon->title }}" loading="lazy"></a>
                @else
                    <a class="content-sermon-player content-sermon-player-link content-sermon-thumbnail-empty" href="{{ $detailUrl }}" aria-label="Open sermon"><span>View sermon</span></a>
                @endif
                <div class="content-sermon-details"><h3>{{ $sermon->title }}</h3><p class="content-sermon-meta">{{ $sermon->speaker ?: 'Teaching team' }} @if ($sermon->youtube_live_status && $sermon->youtube_live_status !== 'none') · {{ ucfirst($sermon->youtube_live_status) }} @endif</p></div>
            </article>
        @empty
            <p class="widget-empty">No published sermons are available yet.</p>
        @endforelse
    </div>
</section>
