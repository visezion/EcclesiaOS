@foreach ($components as $component)
    @php($animation = in_array($component['animation'] ?? 'none', ['none', 'fade', 'slide-up', 'slide-left', 'zoom', 'bounce', 'float'], true) ? ($component['animation'] ?? 'none') : 'none')
    <div class="public-widget widget-animation-{{ $animation }}">
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
            @if (count($component['slides'] ?? []) > 1)<div class="loop-carousel-dots" data-carousel-dots></div>@endif
        </div>
    @elseif (($component['type'] ?? '') === 'gallery')
        @include('website.templates.main._gallery', ['component' => $component])
    @elseif (($component['type'] ?? '') === 'card')
        <article class="content-card-widget" style="--card-background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['background_color'] ?? '') ? $component['background_color'] : '#6d4aff' }};">
            @if (!empty($component['url']))<img src="{{ $assetUrl($component['url']) }}" alt="{{ $component['title'] ?? '' }}" loading="lazy">@endif
            <div class="content-card-widget-body">
                @if (!empty($component['title']))<h3>{{ $component['title'] }}</h3>@endif
                @if (!empty($component['body']))<p>{{ $component['body'] }}</p>@endif
                @if (!empty($component['link']))<a class="button button-light" href="{{ $component['link'] }}">Learn more <span>→</span></a>@endif
            </div>
        </article>
    @elseif (($component['type'] ?? '') === 'icon')
        @php($iconAlign = in_array($component['align'] ?? 'left', ['left', 'center', 'right'], true) ? ($component['align'] ?? 'left') : 'left')
        <a class="content-icon-widget" href="{{ $component['link'] ?? '' ?: '#' }}" @if (empty($component['link'])) onclick="return false" @endif style="justify-content: {{ $iconAlign === 'center' ? 'center' : ($iconAlign === 'right' ? 'flex-end' : 'flex-start') }};text-align: {{ $iconAlign }};--icon-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['icon_color'] ?? '') ? $component['icon_color'] : '#6d4aff' }};--icon-background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['background_color'] ?? '') ? $component['background_color'] : '#ede9fe' }};--icon-size: {{ max(24, min(160, (int) ($component['icon_size'] ?? 56))) }}px;">
            <span class="content-icon-mark">{{ $component['icon'] ?? '✦' }}</span>
        </a>
    @elseif (($component['type'] ?? '') === 'heading')
        <h3 style="text-align: {{ in_array($component['align'] ?? 'left', ['left', 'center', 'right', 'justify'], true) ? ($component['align'] ?? 'left') : 'left' }}">{{ $component['text'] ?? '' }}</h3>
    @elseif (($component['type'] ?? '') === 'text')
        <p style="text-align: {{ in_array($component['align'] ?? 'left', ['left', 'center', 'right', 'justify'], true) ? ($component['align'] ?? 'left') : 'left' }}">{{ $component['text'] ?? '' }}</p>
    @elseif (($component['type'] ?? '') === 'quote')
        <blockquote>{{ $component['text'] ?? '' }}</blockquote>
    @elseif (($component['type'] ?? '') === 'image' && !empty($component['url']))
        <img src="{{ $assetUrl($component['url']) }}" alt="{{ $component['alt'] ?? '' }}" loading="lazy">
    @elseif (($component['type'] ?? '') === 'video' && !empty($component['url']))
        <video controls preload="metadata"><source src="{{ $assetUrl($component['url']) }}"></video>
    @elseif (($component['type'] ?? '') === 'button' && !empty($component['url']))
        <a class="button button-size-{{ $component['button_size'] ?? 'medium' }}" style="background: {{ $component['button_color'] ?? '#6d4aff' }}" href="{{ $component['url'] }}">{{ $component['text'] ?? 'Learn more' }}</a>
    @elseif (($component['type'] ?? '') === 'spacer')
        <div class="component-spacer" style="height: {{ max(0, min(600, (int) ($component['height'] ?? 36))) }}px" aria-hidden="true"></div>
    @endif
    </div>
@endforeach
