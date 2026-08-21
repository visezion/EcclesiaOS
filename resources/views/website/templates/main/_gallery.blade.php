@php
    $galleryStyle = in_array($component['style'] ?? 'grid', ['grid', 'slider', 'masonry', 'featured', 'art-wall'], true) ? $component['style'] : 'grid';
    $galleryImages = collect($component['images'] ?? [])->filter(fn ($image) => !empty($image['url']))->values();
@endphp
@if ($galleryImages->isNotEmpty())
    <div class="content-gallery content-gallery-{{ $galleryStyle }}" data-gallery data-gallery-style="{{ $galleryStyle }}">
        @if (!empty($component['title']))<h3 class="content-gallery-title">{{ $component['title'] }}</h3>@endif
        <div class="content-gallery-grid" style="--gallery-columns: {{ max(2, min(6, (int) ($component['columns'] ?? 3))) }};">
            @foreach ($galleryImages as $index => $image)
                <figure class="content-gallery-item">
                    <img src="{{ $assetUrl($image['url']) }}" alt="{{ $image['alt'] ?? '' }}" loading="lazy">
                </figure>
            @endforeach
        </div>
        @if ($galleryStyle === 'slider' && $galleryImages->count() > 1)<div class="content-gallery-dots" data-gallery-dots></div>@endif
    </div>
@endif
