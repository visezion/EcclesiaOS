@if (count($heroSlides) > 0)
    <div class="hero-art hero-slider" data-hero-slider data-autoplay="true">
        <div class="hero-slider-track">
            @foreach ($heroSlides as $slide)
                <div class="hero-slider-slide" data-hero-slide>
                    @if (($slide['type'] ?? 'image') === 'video')
                        <video src="{{ $slide['url'] }}" @if (!empty($slide['poster'])) poster="{{ $slide['poster'] }}" @endif autoplay muted loop playsinline preload="metadata"></video>
                    @else
                        <img src="{{ $slide['url'] }}" alt="{{ $settings['site_name'] }}">
                    @endif
                </div>
            @endforeach
        </div>
        @if (count($heroSlides) > 1)
            <button type="button" class="hero-slider-control hero-slider-prev" data-hero-prev aria-label="Previous slide">‹</button>
            <button type="button" class="hero-slider-control hero-slider-next" data-hero-next aria-label="Next slide">›</button>
            <div class="hero-slider-dots" data-hero-dots></div>
        @endif
    </div>
@else
    <div class="hero-art">@if ($heroVideoUrl)<video src="{{ $heroVideoUrl }}" autoplay muted loop playsinline poster="{{ $heroImageUrl }}"></video>@elseif ($heroImageUrl)<img src="{{ $heroImageUrl }}" alt="{{ $settings['site_name'] }}">@endif<div class="hero-art-card"><span>Every person has a place here.</span><strong>{{ $settings['site_name'] }}</strong></div></div>
@endif
