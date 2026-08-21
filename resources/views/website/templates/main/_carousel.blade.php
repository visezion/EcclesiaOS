<div class="loop-carousel" data-loop-carousel data-autoplay="{{ ($component['autoplay'] ?? true) ? 'true' : 'false' }}">
    <div class="loop-carousel-track">
        @foreach ($component['slides'] ?? [] as $slide)
            <article class="loop-carousel-slide">
                @if (!empty($slide['image']))<img src="{{ $assetUrl($slide['image']) }}" alt="{{ $slide['title'] ?? '' }}" loading="lazy">@endif
                <div class="loop-carousel-caption">
                    @if (!empty($slide['title']))<h3>{{ $slide['title'] }}</h3>@endif
                    @if (!empty($slide['text']))<p>{{ $slide['text'] }}</p>@endif
                    @if (!empty($slide['link']))<a class="button" href="{{ $slide['link'] }}">Learn more <span>→</span></a>@endif
                </div>
            </article>
        @endforeach
    </div>
    @if (count($component['slides'] ?? []) > 1)<button type="button" class="loop-carousel-control loop-carousel-prev" data-carousel-prev aria-label="Previous slide">‹</button><button type="button" class="loop-carousel-control loop-carousel-next" data-carousel-next aria-label="Next slide">›</button><div class="loop-carousel-dots" data-carousel-dots></div>@endif
</div>
