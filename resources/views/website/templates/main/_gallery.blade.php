@php
    $galleryStyle = in_array($component['style'] ?? 'grid', ['grid', 'slider', 'masonry', 'featured', 'art-wall'], true) ? $component['style'] : 'grid';
    $galleryImages = collect($component['images'] ?? [])->filter(fn ($image) => !empty($image['url']))->values();
@endphp
@if ($galleryImages->isNotEmpty())
    <div class="content-gallery content-gallery-{{ $galleryStyle }}" data-gallery data-gallery-style="{{ $galleryStyle }}">
        @if (!empty($component['title']))<h3 class="content-gallery-title">{{ $component['title'] }}</h3>@endif
        @if ($galleryStyle === 'art-wall')<div class="mosaic-wrapper"><div class="mosaic-glow" aria-hidden="true"></div>@endif
        @if ($galleryStyle === 'slider')<div class="content-gallery-slider-viewport">@endif
            <div class="content-gallery-grid{{ $galleryStyle === 'art-wall' ? ' mosaic-grid' : '' }}" style="--gallery-columns: {{ max(2, min(6, (int) ($component['columns'] ?? 3))) }};">
                @foreach ($galleryImages as $index => $image)
                    @php($imagePosition = in_array($image['position'] ?? 'center', ['center', 'top', 'bottom', 'left', 'right'], true) ? ($image['position'] ?? 'center') : 'center')
                    <figure class="content-gallery-item" @if($galleryStyle === 'slider') aria-hidden="{{ $index === 0 ? 'false' : 'true' }}" @endif>
                        <img src="{{ $assetUrl($image['url']) }}" alt="{{ $image['alt'] ?? '' }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" style="object-position: {{ $imagePosition }};">
                        @if ($galleryStyle === 'slider' && (!empty($image['title']) || !empty($image['text'])))
                            <figcaption class="content-gallery-caption">
                                @if (!empty($image['title']))<strong>{{ $image['title'] }}</strong>@endif
                                @if (!empty($image['text']))<span>{{ $image['text'] }}</span>@endif
                            </figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
            @if ($galleryStyle === 'slider' && $galleryImages->count() > 1)
                <button class="content-gallery-control content-gallery-control-prev" type="button" data-gallery-prev aria-label="Previous gallery image">←</button>
                <button class="content-gallery-control content-gallery-control-next" type="button" data-gallery-next aria-label="Next gallery image">→</button>
            @endif
        @if ($galleryStyle === 'slider')</div>@endif
        @if ($galleryStyle === 'art-wall')</div>@endif
        @if ($galleryStyle === 'slider' && $galleryImages->count() > 1)<div class="content-gallery-dots" data-gallery-dots></div>@endif
    </div>
@endif
