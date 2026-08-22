@php
    $sermonLimit = ($component['sermon_limit'] ?? 'all') === 'all' ? null : (int) $component['sermon_limit'];
    $sermonItems = collect($sermons ?? [])->take($sermonLimit);
@endphp
<section class="content-sermons-widget">
    <div class="content-sermon-list">
        @forelse ($sermonItems as $sermon)
            @php($sermonUrl = $sermon->video_url ?: ($sermon->audio_url ?: '#'))
            <article class="content-sermon-card">
                @if ($sermonUrl !== '#')<a class="content-sermon-card-link" href="{{ $sermonUrl }}" target="_blank" rel="noreferrer">@endif
                    @if (!empty($sermon->thumbnail_url))
                        <img class="content-sermon-thumbnail" src="{{ $assetUrl($sermon->thumbnail_url) }}" alt="{{ $sermon->title }}" loading="lazy">
                    @else
                        <div class="content-sermon-thumbnail content-sermon-thumbnail-empty" aria-hidden="true">▶</div>
                    @endif
                    <div class="content-sermon-details">
                        <h3>{{ $sermon->title }}</h3>
                        <p class="content-sermon-meta">{{ $sermon->speaker ?: 'Teaching team' }}</p>
                    </div>
                @if ($sermonUrl !== '#')</a>@endif
            </article>
        @empty
            <p class="widget-empty">No published sermons are available yet.</p>
        @endforelse
    </div>
</section>
