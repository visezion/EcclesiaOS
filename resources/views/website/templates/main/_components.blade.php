@foreach ($components as $component)
    @php($animation = in_array($component['animation'] ?? 'none', ['none', 'fade', 'slide-up', 'slide-left', 'zoom', 'bounce', 'float'], true) ? ($component['animation'] ?? 'none') : 'none')
    <div class="public-widget widget-animation-{{ $animation }}">
    @if (($component['type'] ?? '') === 'columns')
        <div class="component-group-stack">
            @foreach (($component['groups'] ?? [$component]) as $group)
                @php($fullBleedColumn = count($group['columns'] ?? []) === 1 && (($group['columns'][0]['column_width'] ?? ($group['columns'][0]['content_width'] ?? 'default')) === 'full'))
                <div class="component-columns nested-component-columns {{ $fullBleedColumn ? 'column-full-bleed' : '' }}" style="grid-template-columns: {{ collect($group['columns'] ?? [])->map(fn ($column) => max(1, (int) ($column['width'] ?? 1)).'fr')->join(' ') }};--column-group-gap:{{ max(0, min(100, (int) ($group['gap'] ?? 24))) }}px;--column-group-margin:{{ max(0, min(120, (int) ($group['margin'] ?? 0))) }}px;">
                    @foreach ($group['columns'] ?? [] as $column)
                        @php($columnBackgroundImage = !empty($column['background_image']) ? $assetUrl($column['background_image']) : null)
                        @php($columnBackgroundVideo = !empty($column['background_video']) ? $assetUrl($column['background_video']) : null)
                        @php($columnBackgroundColor = (($column['background_color'] ?? null) === 'transparent' || !empty($column['background_transparent'])) ? 'transparent' : (preg_match('/^#[0-9a-fA-F]{6}$/', $column['background_color'] ?? '') ? $column['background_color'] : 'transparent'))
                        <div class="component-column has-column-presentation {{ $columnBackgroundVideo ? 'has-column-video' : '' }}" style="--column-background-color:{{ $columnBackgroundColor }};--column-min-height:{{ ['auto' => 'auto', 'compact' => '240px', 'tall' => '560px', 'full' => '100%'][$column['height'] ?? 'auto'] ?? 'auto' }};--column-content-width:{{ ['default' => '92%', 'wide' => '100%', 'full' => '100%'][$column['column_width'] ?? ($column['content_width'] ?? 'default')] ?? '92%' }};--column-border-radius:{{ max(0, min(100, (int) ($column['border_radius'] ?? 0))) }}px;--column-padding:{{ max(0, min(100, (int) ($column['padding'] ?? 16))) }}px;--column-background-position:{{ in_array($column['background_position'] ?? 'center', ['center', 'top', 'bottom', 'left', 'right'], true) ? ($column['background_position'] ?? 'center') : 'center' }};{{ $columnBackgroundImage ? 'background-image:url('.$columnBackgroundImage.');' : '' }}">
                            @if ($columnBackgroundVideo)<video class="component-column-background" autoplay muted loop playsinline preload="metadata"><source src="{{ $columnBackgroundVideo }}"></video>@endif
                            @include('website.templates.main._components', ['components' => $column['components'] ?? [], 'events' => $events ?? collect(), 'sermons' => $sermons ?? collect()])
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
                        @if (!empty($slide['video']))
                            <video class="loop-carousel-background" src="{{ $assetUrl($slide['video']) }}" autoplay muted loop playsinline preload="metadata"></video>
                        @elseif (!empty($slide['image']))
                            <img src="{{ $assetUrl($slide['image']) }}" alt="{{ $slide['title'] ?? '' }}" loading="lazy">
                        @endif
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
    @elseif (($component['type'] ?? '') === 'video-slider')
        @include('website.templates.main._video-slider', ['component' => $component])
    @elseif (($component['type'] ?? '') === 'gallery')
        @include('website.templates.main._gallery', ['component' => $component])
    @elseif (($component['type'] ?? '') === 'divider')
        <div class="content-divider-widget" style="--divider-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['divider_color'] ?? '') ? $component['divider_color'] : '#e2e8f0' }};--divider-width: {{ max(10, min(100, (int) ($component['divider_width'] ?? 100))) }}%;--divider-thickness: {{ max(1, min(8, (int) ($component['divider_thickness'] ?? 1))) }}px;--divider-spacing: {{ max(0, min(120, (int) ($component['divider_spacing'] ?? 24))) }}px;--divider-style: {{ in_array($component['divider_style'] ?? 'solid', ['solid', 'dashed', 'dotted'], true) ? ($component['divider_style'] ?? 'solid') : 'solid' }}" aria-hidden="true"><span></span></div>
    @elseif (($component['type'] ?? '') === 'events')
        @include('website.templates.main._events', ['component' => $component, 'events' => $events ?? collect()])
    @elseif (($component['type'] ?? '') === 'sermons')
        @include('website.templates.main._sermons', ['component' => $component, 'sermons' => $sermons ?? collect()])
    @elseif (($component['type'] ?? '') === 'card')
        @php($cardVideoUrl = !empty($component['background_video']) ? $assetUrl($component['background_video']) : null)
        @if (!empty($component['link']))<a class="content-card-widget-link" href="{{ $component['link'] }}" aria-label="Open {{ $component['title'] ?? 'card' }}">@endif
        <article class="content-card-widget content-card-shadow-{{ in_array($component['card_shadow'] ?? 'none', ['none', 'small', 'medium', 'large'], true) ? ($component['card_shadow'] ?? 'none') : 'none' }} {{ $cardVideoUrl || !empty($component['url']) ? 'has-media' : '' }}" style="--card-background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['background_color'] ?? '') ? $component['background_color'] : ($settings['primary_color'] ?? '#6d4aff') }};--card-border-width: {{ max(0, min(12, (int) ($component['card_border_width'] ?? 0))) }}px;--card-border-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['card_border_color'] ?? '') ? $component['card_border_color'] : '#ffffff' }};--card-border-radius:{{ max(0, min(100, (int) ($component['card_border_radius'] ?? 24))) }}px;">
            @if ($cardVideoUrl)
                <video class="content-card-widget-background" autoplay muted loop playsinline preload="auto" data-background-video @if (!empty($component['url'])) poster="{{ $assetUrl($component['url']) }}" @endif>
                    <source src="{{ $cardVideoUrl }}">
                </video>
            @elseif (!empty($component['url']))
                <img src="{{ $assetUrl($component['url']) }}" alt="{{ $component['title'] ?? '' }}" loading="lazy">
            @endif
            <div class="content-card-widget-body">
                @if (!empty($component['title']))<h3>{{ $component['title'] }}</h3>@endif
                @if (!empty($component['body']))<p>{{ $component['body'] }}</p>@endif
                @if (!empty($component['link']))<span class="button button-light">Learn more <span>→</span></span>@endif
            </div>
        </article>
        @if (!empty($component['link']))</a>@endif
    @elseif (($component['type'] ?? '') === 'icon')
        @php($iconAlign = in_array($component['align'] ?? 'left', ['left', 'center', 'right'], true) ? ($component['align'] ?? 'left') : 'left')
        <a class="content-icon-widget" href="{{ $component['link'] ?? '' ?: '#' }}" @if (empty($component['link'])) onclick="return false" @endif style="justify-content: {{ $iconAlign === 'center' ? 'center' : ($iconAlign === 'right' ? 'flex-end' : 'flex-start') }};text-align: {{ $iconAlign }};--icon-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['icon_color'] ?? '') ? $component['icon_color'] : ($settings['primary_color'] ?? '#6d4aff') }};--icon-background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['background_color'] ?? '') ? $component['background_color'] : 'color-mix(in srgb, '.($settings['primary_color'] ?? '#6d4aff').' 12%, #fff)' }};--icon-size: {{ max(24, min(160, (int) ($component['icon_size'] ?? 56))) }}px;">
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
        <video src="{{ $assetUrl($component['url']) }}" controls preload="metadata"></video>
    @elseif (($component['type'] ?? '') === 'button' && !empty($component['url']))
        <a class="button button-size-{{ $component['button_size'] ?? 'medium' }}" style="background: {{ $component['button_color'] ?? '#6d4aff' }}" href="{{ $component['url'] }}">{{ $component['text'] ?? 'Learn more' }}</a>
    @elseif (($component['type'] ?? '') === 'spacer')
        <div class="component-spacer" style="height: {{ max(0, min(600, (int) ($component['height'] ?? 36))) }}px" aria-hidden="true"></div>
    @endif
    </div>
@endforeach
