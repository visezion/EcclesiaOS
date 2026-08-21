@php
    $sections = collect($page->sections ?? ['hero', 'welcome', 'services', 'events', 'ministries', 'locations', 'sermons', 'giving', 'contact'])->map(fn ($section) => is_array($section) ? ($section['type'] ?? null) : $section)->filter()->all();
    $assetUrl = static fn (?string $value): ?string => filled($value)
        ? (str_starts_with($value, 'http') || str_starts_with($value, '//')
            ? $value
            : asset('storage/'.ltrim($value, '/')))
        : null;
    $logoUrl = $assetUrl($settings['logo_url'] ?? null);
    $heroImageUrl = $assetUrl($settings['hero_image_url'] ?? null);
    $heroVideoUrl = $assetUrl($settings['hero_video_url'] ?? null);
    $colorScheme = in_array($settings['color_scheme'] ?? 'dark', ['dark', 'light'], true) ? $settings['color_scheme'] : 'dark';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->seo_title ?: $settings['site_name'] }}</title>
    <meta name="description" content="{{ $page->seo_description ?: ($settings['seo_description'] ?: $settings['tagline']) }}">
    <meta name="theme-color" content="{{ $settings['primary_color'] }}">
    <link rel="stylesheet" href="{{ asset('css/website/templates/main.css') }}?v={{ filemtime(public_path('css/website/templates/main.css')) }}">
</head>
<body class="theme-{{ $colorScheme }}" data-default-theme="{{ $colorScheme }}" data-theme-key="ecclesia-site-theme-{{ $church->slug }}" style="--primary:{{ $settings['primary_color'] }};--accent:{{ $settings['accent_color'] }};--font:'{{ $settings['font'] ?? 'Manrope' }}',Arial,sans-serif">
    @if ($preview)<div class="preview-bar">Preview mode · unpublished changes are visible only to you</div>@endif
    <div class="site-shell">
        <header class="site-header" data-header>
            <div class="container nav-row">
                <a class="brand" href="{{ route('website.public', ['church' => $church->slug]) }}"><span class="brand-mark">@if ($logoUrl)<img src="{{ $logoUrl }}" alt="">@else✦@endif</span><span><strong>{{ $settings['site_name'] }}</strong><small>{{ $settings['tagline'] }}</small></span></a>
                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-menu-toggle><span>☰</span><span class="sr-only">Menu</span></button>
                <label class="site-search"><span>⌕</span><input type="search" placeholder="Search" aria-label="Search this website" data-site-search></label>
                <nav id="site-nav" class="site-nav" data-menu>@foreach ($navigation as $item)<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@endforeach</nav>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode" title="Switch website appearance"><span class="theme-toggle-sun" aria-hidden="true">☀</span><span class="theme-toggle-moon" aria-hidden="true">☾</span></button>
                <a class="button button-small nav-action" href="{{ $settings['hero_button_url'] ?: '#visit' }}">{{ $settings['hero_button_label'] ?: 'Plan a visit' }}</a>
            </div>
        </header>

        <div class="page-section-flow" data-page-section-flow data-page-section-order='@json($pageSectionOrder)'>
        @if ($page->slug === 'home' || in_array('hero', $sections, true))
            <section class="hero" data-page-section="hero"><div class="container hero-grid"><div class="hero-copy"><p class="eyebrow">{{ $settings['hero_eyebrow'] ?: 'You are welcome here' }}</p><h1>{{ $settings['hero_heading'] ?: $page->title }}</h1><p class="hero-text">{{ $settings['hero_body'] }}</p><div class="button-row"><a class="button" href="{{ $settings['hero_button_url'] ?: '#visit' }}">{{ $settings['hero_button_label'] ?: 'Plan your visit' }} <span>→</span></a><a class="text-link" href="#community">Meet the community <span>↗</span></a></div></div><div class="hero-art">@if ($heroVideoUrl)<video src="{{ $heroVideoUrl }}" autoplay muted loop playsinline poster="{{ $heroImageUrl }}"></video>@elseif ($heroImageUrl)<img src="{{ $heroImageUrl }}" alt="{{ $settings['site_name'] }}">@endif<div class="hero-art-card"><span>Every person has a place here.</span><strong>{{ $settings['site_name'] }}</strong></div></div></div></section>
        @endif

        @if ($page->slug === 'home' && $sermons->isNotEmpty())<section class="feature-strip"><div class="container feature-grid"><div><p class="eyebrow">Latest message</p><h2>{{ $sermons->first()->title }}</h2><p>{{ $sermons->first()->summary ?: 'A message to strengthen your faith and help you take your next step.' }}</p><a class="feature-link" href="{{ $sermons->first()->video_url ?: ($sermons->first()->audio_url ?: '#sermons') }}">Watch sermon <span>→</span></a></div><div class="feature-image">@if ($sermons->first()->thumbnail_url)<img src="{{ $assetUrl($sermons->first()->thumbnail_url) }}" alt="{{ $sermons->first()->title }}">@else<span>WATCH<br>THE<br>MESSAGE</span>@endif</div></div></section>@endif
        @if ($page->body && $page->slug !== 'home')<section class="section"><div class="container narrow"><p class="eyebrow">{{ $page->title }}</p><p class="lead preserve-lines">{{ $page->body }}</p></div></section>@endif
        @if ($page->slug === 'home')<section class="section experience"><div class="container"><div class="section-heading centered"><p class="eyebrow">{{ $settings['experience_kicker'] }}</p><h2>{{ $settings['experience_heading'] }}</h2><p class="lead">{{ $settings['experience_body'] }}</p></div><div class="experience-grid"><a class="experience-card" href="#locations"><span class="experience-icon">⌂</span><h3>{{ $settings['experience_one_title'] }}</h3><p>{{ $settings['experience_one_body'] }}</p><strong>Find a location →</strong></a><a class="experience-card" href="#sermons"><span class="experience-icon">▶</span><h3>{{ $settings['experience_two_title'] }}</h3><p>{{ $settings['experience_two_body'] }}</p><strong>Find a time →</strong></a><a class="experience-card" href="#community"><span class="experience-icon">◎</span><h3>{{ $settings['experience_three_title'] }}</h3><p>{{ $settings['experience_three_body'] }}</p><strong>Find your people →</strong></a><a class="experience-card" href="#contact"><span class="experience-icon">✦</span><h3>{{ $settings['experience_four_title'] }}</h3><p>{{ $settings['experience_four_body'] }}</p><strong>Get connected →</strong></a></div></div></section>@endif
        @if (in_array('welcome', $sections, true) && $page->slug === 'home')<section class="section welcome" data-page-section="welcome"><div class="container two-column"><div><p class="eyebrow">Our church family</p><h2>{{ $settings['welcome_heading'] }}</h2></div><div class="statement"><span>“</span><p>{{ $settings['welcome_body'] }}</p></div></div></section>@endif
        @if (in_array('services', $sections, true))<section id="visit" class="section soft" data-page-section="services"><div class="container"><div class="section-heading"><p class="eyebrow">{{ $settings['service_kicker'] }}</p><h2>{{ $settings['service_heading'] }}</h2><p class="lead">{{ $settings['service_body'] }}</p></div><div class="service-grid"><article><span>01</span><h3>{{ $settings['service_one_title'] }}</h3><p>{{ $settings['service_one_body'] }}</p></article><article><span>02</span><h3>{{ $settings['service_two_title'] }}</h3><p>{{ $settings['service_two_body'] }}</p></article><article><span>03</span><h3>{{ $settings['service_three_title'] }}</h3><p>{{ $settings['service_three_body'] }}</p></article></div></div></section>@endif
        @if (in_array('events', $sections, true) && $events->isNotEmpty())<section class="section" data-page-section="events"><div class="container"><div class="section-heading inline-heading"><div><p class="eyebrow">Coming up</p><h2>Make room for what matters.</h2></div><a class="text-link" href="{{ route('website.public', ['church' => $church->slug, 'page' => 'events']) }}">See all events ↗</a></div><div class="event-list">@foreach ($events->take(4) as $event)<article><time datetime="{{ $event->starts_at->toDateString() }}"><b>{{ $event->starts_at->format('d') }}</b><span>{{ $event->starts_at->format('M') }}</span></time><div><h3>{{ $event->title }}</h3><p>{{ $event->venue ?: 'At our church' }} · {{ $event->starts_at->format('g:i A') }}</p></div></article>@endforeach</div></div></section>@endif
        @if (in_array('ministries', $sections, true) && $ministries->isNotEmpty())<section id="community" class="section soft" data-page-section="ministries"><div class="container"><div class="section-heading"><p class="eyebrow">Life together</p><h2>Find your people. Grow together.</h2></div><div class="card-grid">@foreach ($ministries as $ministry)<article class="card"><span class="card-icon">✦</span><h3>{{ $ministry->name }}</h3><p>{{ $ministry->description ?: 'A place to connect, serve, and make a difference together.' }}</p></article>@endforeach</div></div></section>@endif
        @if (in_array('locations', $sections, true) && $campuses->isNotEmpty())<section class="section" data-page-section="locations"><div class="container"><div class="section-heading"><p class="eyebrow">Our locations</p><h2>Gather where you are.</h2></div><div class="card-grid">@foreach ($campuses as $campus)<article class="card"><h3>{{ $campus->name }}</h3><p>{{ collect([$campus->city, $campus->country])->filter()->join(', ') }}</p><p>{{ $campus->address ?: 'Details available from the church office.' }}</p></article>@endforeach</div></div></section>@endif
        @if (in_array('sermons', $sections, true) && $sermons->isNotEmpty())<section class="section soft" data-page-section="sermons"><div class="container"><div class="section-heading"><p class="eyebrow">Watch and listen</p><h2>Messages for the journey.</h2></div><div class="card-grid">@foreach ($sermons as $sermon)<a class="card sermon-card" href="{{ $sermon->video_url ?: ($sermon->audio_url ?: '#sermons') }}" @if ($sermon->video_url || $sermon->audio_url) target="_blank" rel="noreferrer" @endif><span class="play">▶</span><h3>{{ $sermon->title }}</h3><p>{{ $sermon->speaker ?: 'Teaching team' }} · {{ $sermon->preached_at?->format('M d, Y') ?: 'Recent message' }}</p></a>@endforeach</div></div></section>@endif
        @if (in_array('giving', $sections, true))<section class="section" data-page-section="giving"><div class="container"><div class="giving"><div><p class="eyebrow">{{ $settings['giving_kicker'] }}</p><h2>{{ $settings['giving_heading'] }}</h2><p>{{ $settings['giving_body'] }}</p></div><a class="button button-light" href="{{ $settings['giving_button_url'] }}">{{ $settings['giving_button_label'] }} <span>→</span></a></div></div></section>@endif
        @if (in_array('store', $sections, true) && $products->isNotEmpty())<section class="section soft" data-page-section="store"><div class="container"><div class="section-heading"><p class="eyebrow">Church store</p><h2>Resources for your next step.</h2></div><div class="card-grid">@foreach ($products as $product)<article class="card"><h3>{{ $product->name }}</h3><p>{{ $product->author ?: ($product->category ?: 'Church resource') }} · {{ $product->format ?: 'Available now' }}</p><strong>{{ $church->currency }} {{ number_format((float) $product->price, 2) }}</strong></article>@endforeach</div></div></section>@endif
        @if (in_array('contact', $sections, true))<section id="contact" class="section contact" data-page-section="contact"><div class="container two-column"><div><p class="eyebrow">{{ $settings['contact_kicker'] }}</p><h2>{{ $settings['contact_heading'] }}</h2></div><div class="contact-details"><p>{{ $settings['contact_address'] ?: 'Come find us at our church campus.' }}</p>@if ($settings['contact_email'])<a href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }}</a>@endif @if ($settings['contact_phone'])<a href="tel:{{ $settings['contact_phone'] }}">{{ $settings['contact_phone'] }}</a>@endif</div></div></section>@endif
        @foreach ($customSections as $customSection)<section class="section reusable-section" data-page-section="{{ $customSection['id'] }}"><div class="container reusable-grid"><div><p class="eyebrow">{{ $customSection['eyebrow'] ?? '' }}</p><h2>{{ $customSection['title'] }}</h2>@if (!empty($customSection['components']))<div class="component-stack">@foreach ($customSection['components'] as $component)@if (($component['type'] ?? '') === 'heading')<h3>{{ $component['text'] ?? '' }}</h3>@elseif (($component['type'] ?? '') === 'text')<p class="lead preserve-lines">{{ $component['text'] ?? '' }}</p>@elseif (($component['type'] ?? '') === 'quote')<blockquote>{{ $component['text'] ?? '' }}</blockquote>@elseif (($component['type'] ?? '') === 'image' && !empty($component['url']))<img src="{{ $assetUrl($component['url']) }}" alt="{{ $component['alt'] ?? $customSection['title'] }}">@elseif (($component['type'] ?? '') === 'video' && !empty($component['url']))<video class="component-video" controls playsinline><source src="{{ $assetUrl($component['url']) }}"></video>@elseif (($component['type'] ?? '') === 'button' && !empty($component['url']))<a class="button" href="{{ $component['url'] }}">{{ $component['text'] ?? 'Learn more' }} <span>→</span></a>@elseif (($component['type'] ?? '') === 'spacer')<div class="component-spacer" style="height: {{ max(0, min(600, (int) ($component['height'] ?? 36))) }}px"></div>@endif @endforeach</div>@else<p class="lead preserve-lines">{{ $customSection['body'] ?? '' }}</p>@if (!empty($customSection['button_label']) && !empty($customSection['button_url']))<a class="button" href="{{ $customSection['button_url'] }}">{{ $customSection['button_label'] }} <span>→</span></a>@endif @endif</div>@if (!empty($customSection['video_url']) || !empty($customSection['image_url']))<div class="reusable-media">@if (!empty($customSection['video_url']))<video controls playsinline @if (!empty($customSection['image_url'])) poster="{{ $assetUrl($customSection['image_url']) }}" @endif><source src="{{ $assetUrl($customSection['video_url']) }}"></video>@else<img src="{{ $assetUrl($customSection['image_url']) }}" alt="{{ $customSection['title'] }}">@endif</div>@endif</div></section>@endforeach
        @foreach ($customNestedSections as $customSection)
            <section class="section reusable-section reusable-columns" data-page-section="{{ $customSection['id'] }}">
                <div class="container">
                    <p class="eyebrow">{{ $customSection['eyebrow'] ?? '' }}</p>
                    <h2>{{ $customSection['title'] }}</h2>
                    @if (!empty($customSection['body']))<p class="lead preserve-lines">{{ $customSection['body'] }}</p>@endif
                    <div class="component-column nested-root-column">
                    @include('website.templates.main._components', ['components' => [$customSection['components']], 'events' => $events])
                    </div>
                </div>
            </section>
        @endforeach
        @foreach ($customComponentSections as $customSection)
            @php($columnCount = max(1, min(4, ((int) collect($customSection['components'])->max('column')) + 1)))
            @php($columnWidths = count($customSection['column_widths'] ?? []) === $columnCount ? array_map(fn ($width) => max(1, (int) $width), $customSection['column_widths']) : array_fill(0, $columnCount, 1))
            <section class="section reusable-section reusable-columns" data-page-section="{{ $customSection['id'] }}">
                <div class="container">
                    <p class="eyebrow">{{ $customSection['eyebrow'] ?? '' }}</p>
                    <h2>{{ $customSection['title'] }}</h2>
                    @if (!empty($customSection['body']))
                        <p class="lead preserve-lines">{{ $customSection['body'] }}</p>
                    @endif
                    <div class="component-columns" style="grid-template-columns: {{ collect($columnWidths)->map(fn ($width) => $width.'fr')->join(' ') }};">
                        @foreach (collect($customSection['components'])->groupBy(fn ($component) => (int) ($component['column'] ?? 0))->sortKeys() as $components)
                            <div class="component-column">
                                @foreach ($components as $component)
                                    @if (($component['type'] ?? '') === 'heading')
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
                                    @elseif (($component['type'] ?? '') === 'carousel')
                                        @include('website.templates.main._carousel', ['component' => $component])
                                    @elseif (($component['type'] ?? '') === 'video-slider')
                                        @include('website.templates.main._video-slider', ['component' => $component])
                                    @elseif (($component['type'] ?? '') === 'gallery')
                                        @include('website.templates.main._gallery', ['component' => $component])
                                    @elseif (($component['type'] ?? '') === 'divider')
                                        <div class="content-divider-widget" style="--divider-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['divider_color'] ?? '') ? $component['divider_color'] : '#e2e8f0' }};--divider-width: {{ max(10, min(100, (int) ($component['divider_width'] ?? 100))) }}%;--divider-thickness: {{ max(1, min(8, (int) ($component['divider_thickness'] ?? 1))) }}px;--divider-spacing: {{ max(0, min(120, (int) ($component['divider_spacing'] ?? 24))) }}px;--divider-style: {{ in_array($component['divider_style'] ?? 'solid', ['solid', 'dashed', 'dotted'], true) ? ($component['divider_style'] ?? 'solid') : 'solid' }}" aria-hidden="true"><span></span></div>
                                    @elseif (($component['type'] ?? '') === 'events')
                                        @include('website.templates.main._events', ['component' => $component, 'events' => $events])
                                    @elseif (($component['type'] ?? '') === 'card')
                                        @include('website.templates.main._components', ['components' => [$component]])
                                    @elseif (($component['type'] ?? '') === 'icon')
                                        @include('website.templates.main._components', ['components' => [$component]])
                                    @elseif (($component['type'] ?? '') === 'spacer')
                                        <div class="component-spacer" style="height: {{ max(0, min(600, (int) ($component['height'] ?? 36))) }}px" aria-hidden="true"></div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
        </div>

        <footer><div class="container footer-row"><span>© {{ now()->year }} {{ $settings['site_name'] }}</span><span>{{ $settings['footer_text'] }}</span></div></footer>
    </div>
    <script src="{{ asset('js/website/templates/main.js') }}?v={{ filemtime(public_path('js/website/templates/main.js')) }}" defer></script>
</body>
</html>
