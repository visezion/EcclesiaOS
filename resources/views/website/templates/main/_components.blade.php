@foreach ($components as $component)
    @if (($component['type'] ?? '') === 'columns')
        <div class="component-group-stack">
            @foreach (($component['groups'] ?? [$component]) as $group)
                <div class="component-columns nested-component-columns" style="grid-template-columns: {{ collect($group['columns'] ?? [])->map(fn ($column) => max(1, (int) ($column['width'] ?? 1)).'fr')->join(' ') }};">
                    @foreach ($group['columns'] ?? [] as $column)
                        <div class="component-column">
                            @include('website.templates.main._components', ['components' => $column['components'] ?? []])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @elseif (($component['type'] ?? '') === 'carousel')
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
    @elseif (($component['type'] ?? '') === 'heading')
        <h3>{{ $component['text'] ?? '' }}</h3>
    @elseif (($component['type'] ?? '') === 'text')
        <p>{{ $component['text'] ?? '' }}</p>
    @elseif (($component['type'] ?? '') === 'quote')
        <blockquote>{{ $component['text'] ?? '' }}</blockquote>
    @elseif (($component['type'] ?? '') === 'image' && !empty($component['url']))
        <img src="{{ $assetUrl($component['url']) }}" alt="{{ $component['alt'] ?? '' }}" loading="lazy">
    @elseif (($component['type'] ?? '') === 'video' && !empty($component['url']))
        <video controls preload="metadata"><source src="{{ $assetUrl($component['url']) }}"></video>
    @elseif (($component['type'] ?? '') === 'button' && !empty($component['url']))
        <a class="button" href="{{ $component['url'] }}">{{ $component['text'] ?? 'Learn more' }}</a>
    @elseif (($component['type'] ?? '') === 'spacer')
        <div class="component-spacer" style="height: {{ max(0, min(600, (int) ($component['height'] ?? 36))) }}px" aria-hidden="true"></div>
    @endif
@endforeach
