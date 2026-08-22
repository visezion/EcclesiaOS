<div class="video-slider" data-video-slider data-height="{{ in_array((int) ($component['video_slider_height'] ?? 420), [300, 420, 560, 700], true) ? (int) $component['video_slider_height'] : 420 }}" data-autoplay="{{ ($component['autoplay'] ?? true) ? 'true' : 'false' }}">
    <div class="video-slider-track">
        @foreach ($component['slides'] ?? [] as $slide)
            <article class="video-slider-slide">
                @if (!empty($slide['video']))
                    <video @if (!empty($slide['image'])) poster="{{ $assetUrl($slide['image']) }}" @endif autoplay muted loop preload="auto" playsinline data-background-video>
                        <source src="{{ $assetUrl($slide['video']) }}">
                    </video>
                @elseif (!empty($slide['image']))
                    <img src="{{ $assetUrl($slide['image']) }}" alt="{{ $slide['title'] ?? '' }}" loading="lazy">
                @else
                    <div class="video-slider-empty">Add a video to this slide</div>
                @endif
                <div class="video-slider-caption">
                    @if (!empty($slide['title']))<h3>{{ $slide['title'] }}</h3>@endif
                    @if (!empty($slide['text']))<p>{{ $slide['text'] }}</p>@endif
                    @if (!empty($slide['link']))<a class="button" href="{{ $slide['link'] }}">Learn more <span>→</span></a>@endif
                </div>
            </article>
        @endforeach
    </div>
    @if (count($component['slides'] ?? []) > 1)
        <button class="video-slider-control video-slider-prev" type="button" data-video-slider-prev aria-label="Previous video">‹</button>
        <button class="video-slider-control video-slider-next" type="button" data-video-slider-next aria-label="Next video">›</button>
        <div class="video-slider-dots" data-video-slider-dots></div>
    @endif
</div>
