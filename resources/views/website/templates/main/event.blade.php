@php
    $colorScheme = in_array($settings['color_scheme'] ?? 'dark', ['dark', 'light'], true) ? $settings['color_scheme'] : 'dark';
    $eventDate = $event->starts_at?->format('F j, Y');
    $eventTime = $event->starts_at?->format('g:i A').($event->ends_at ? ' – '.$event->ends_at->format('g:i A') : '');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->title }} · {{ $settings['site_name'] }}</title>
    <meta name="description" content="{{ Str::limit(strip_tags($event->description ?: $event->title), 155) }}">
    <meta name="theme-color" content="{{ $settings['primary_color'] }}">
    <link rel="stylesheet" href="{{ asset('css/website/templates/main.css') }}?v={{ filemtime(public_path('css/website/templates/main.css')) }}">
</head>
<body class="theme-{{ $colorScheme }} public-event-page" data-default-theme="{{ $colorScheme }}" data-theme-key="ecclesia-site-theme-{{ $church->slug }}" style="--primary:{{ $settings['primary_color'] }};--accent:{{ $settings['accent_color'] }};--font:'{{ $settings['font'] ?? 'Manrope' }}',Arial,sans-serif">
    <div class="site-shell">
        <header class="site-header">
            <div class="container nav-row">
                <a class="brand" href="{{ route('website.public', ['church' => $church->slug]) }}"><span class="brand-mark">@if ($logoUrl)<img src="{{ $logoUrl }}" alt="">@else✦@endif</span><span><strong>{{ $settings['site_name'] }}</strong><small>{{ $settings['tagline'] }}</small></span></a>
                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-menu-toggle><span>☰</span><span class="sr-only">Menu</span></button>
                <label class="site-search"><span>⌕</span><input type="search" placeholder="Search" aria-label="Search this website" data-site-search></label>
                <nav id="site-nav" class="site-nav" data-menu>@foreach ($navigation as $item)<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@endforeach</nav>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode" title="Switch website appearance"><span class="theme-toggle-sun" aria-hidden="true">☀</span><span class="theme-toggle-moon" aria-hidden="true">☾</span></button>
                <a class="button button-small nav-action" href="{{ $settings['hero_button_url'] ?: '#contact' }}">{{ $settings['hero_button_label'] ?: 'Plan a visit' }}</a>
            </div>
        </header>

        <main>
            <section class="public-event-hero">
                <div class="container public-event-hero-grid">
                    <div class="public-event-copy">
                        <a class="public-event-back" href="{{ route('website.public', ['church' => $church->slug, 'page' => 'events']) }}">← All events</a>
                        <p class="eyebrow">{{ $event->event_type ?: ($event->category ?: 'Church event') }}</p>
                        <h1>{{ $event->title }}</h1>
                        <p class="public-event-intro">{{ $event->description ?: 'Join us for this special time together.' }}</p>
                        <div class="public-event-meta">
                            <span><b>{{ $eventDate ?: 'Date to be announced' }}</b><small>Date</small></span>
                            <span><b>{{ $eventTime ?: 'Time to be announced' }}</b><small>Time</small></span>
                            <span><b>{{ $event->venue ?: ($event->campus?->name ?: $church->name) }}</b><small>Location</small></span>
                        </div>
                    </div>
                    <div class="public-event-art">
                        @if ($posterUrl)<img src="{{ $posterUrl }}" alt="{{ $event->title }}">@else<div class="public-event-placeholder">✦<span>{{ $church->name }}</span></div>@endif
                    </div>
                </div>
            </section>

            <section class="section public-event-content">
                <div class="container public-event-content-grid">
                    <article>
                        <p class="eyebrow">Come as you are</p>
                        <h2>Everything you need to know</h2>
                        <p class="public-event-description">{{ $event->description ?: 'We would love to welcome you. Check the details below and make plans to join us.' }}</p>
                        @if ($event->sessions->isNotEmpty())
                            <div class="public-event-sessions">
                                <h3>Available times</h3>
                                @foreach ($event->sessions as $session)
                                    <div class="public-event-session"><span class="public-event-session-icon">◷</span><div><b>{{ filled($session->session_date) ? Carbon\Carbon::parse($session->session_date)->format('l, F j, Y') : $eventDate }}</b><p>{{ filled($session->starts_at) ? Carbon\Carbon::parse($session->starts_at)->format('g:i A') : '' }}{{ filled($session->ends_at) ? ' – '.Carbon\Carbon::parse($session->ends_at)->format('g:i A') : '' }} · {{ $session->venue ?: ($session->campus?->name ?: $event->venue ?: 'Church campus') }}</p></div></div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                    <aside class="public-event-aside">
                        <div class="public-event-aside-card"><p class="eyebrow">Plan your visit</p><h3>We are saving a place for you.</h3><p>Have a question or need directions? Reach out to our church team before the event.</p><a class="button" href="{{ $settings['hero_button_url'] ?: '#contact' }}">{{ $settings['hero_button_label'] ?: 'Plan your visit' }} <span>→</span></a></div>
                        @if ($relatedEvents->isNotEmpty())<div class="public-event-related"><p class="eyebrow">More to explore</p><h3>Upcoming events</h3>@foreach ($relatedEvents as $related)<a href="{{ route('website.public.events.show', ['church' => $church->slug, 'event' => $related]) }}"><span>{{ $related->starts_at?->format('M d') }}</span><b>{{ $related->title }}</b> <i>→</i></a>@endforeach</div>@endif
                    </aside>
                </div>
            </section>
        </main>

        <footer><div class="container footer-row"><span>© {{ now()->year }} {{ $settings['site_name'] }}</span><span>{{ $settings['footer_text'] }}</span></div></footer>
    </div>
    <script src="{{ asset('js/website/templates/main.js') }}?v={{ filemtime(public_path('js/website/templates/main.js')) }}" defer></script>
</body>
</html>
