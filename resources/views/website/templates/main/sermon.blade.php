@php
    $assetUrl = static function (?string $value): ?string {
        if (! filled($value)) return null;
        $value = trim($value);
        if (str_starts_with($value, 'http') || str_starts_with($value, '//')) return $value;
        $value = ltrim($value, '/');
        if (($storagePosition = strpos($value, 'storage/')) !== false) $value = substr($value, $storagePosition + 8);
        return asset('storage/'.ltrim($value, '/'));
    };
    $videoUrl = (string) ($sermon->video_url ?? '');
    $youtubeId = $sermon->youtube_video_id;
    if (!$youtubeId && preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|live\/|embed\/))([^?&\/]+)/i', $videoUrl, $matches)) $youtubeId = $matches[1];
    $isFileVideo = $videoUrl && preg_match('/\.(mp4|webm|ogg)(?:\?.*)?$/i', $videoUrl);
    $thumbnailUrl = $assetUrl($sermon->thumbnail_url);
    $logoUrl = $assetUrl($settings['logo_url'] ?? null);
    $faviconUrl = $assetUrl($settings['favicon_url'] ?? null);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sermon->title }} · {{ $settings['site_name'] }}</title>
    <meta name="description" content="{{ $sermon->summary ?: $sermon->title }}">
    @if ($faviconUrl)<link rel="icon" href="{{ $faviconUrl }}"><link rel="shortcut icon" href="{{ $faviconUrl }}">@endif
    <link rel="stylesheet" href="{{ asset('css/website/templates/main.css') }}?v={{ filemtime(public_path('css/website/templates/main.css')) }}">
</head>
<body class="theme-{{ $settings['color_scheme'] ?? 'dark' }} menu-style-{{ in_array($settings['menu_style'] ?? 'classic', ['classic', 'floating', 'centered', 'pill', 'accent'], true) ? ($settings['menu_style'] ?? 'classic') : 'classic' }}" style="--primary:{{ $settings['primary_color'] ?? '#276749' }};--accent:{{ $settings['accent_color'] ?? '#d18b35' }};--font:'{{ $settings['font'] ?? 'Manrope' }}',Arial,sans-serif">
    <div class="site-shell">
        <header class="site-header" data-header><div class="container nav-row"><a class="brand" href="{{ route('website.public', ['church' => $church->slug]) }}"><span class="brand-mark">✦</span><span><strong>{{ $settings['site_name'] }}</strong><small>{{ $settings['tagline'] }}</small></span></a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-menu-toggle><span>☰</span><span class="sr-only">Menu</span></button><label class="site-search"><span>⌕</span><input type="search" placeholder="Search" aria-label="Search this website" data-site-search></label>@include('website.templates.main._navigation')<button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode" title="Switch website appearance"><span class="theme-toggle-sun" aria-hidden="true">☀</span><span class="theme-toggle-moon" aria-hidden="true">☾</span></button><a class="button button-small nav-action" href="{{ $settings['hero_button_url'] ?: '#visit' }}">{{ $settings['hero_button_label'] ?: 'Plan a visit' }}</a></div></header>
        <main class="section sermon-detail-page"><div class="container narrow"><a class="text-link sermon-back-link" href="{{ route('website.public', ['church' => $church->slug, 'page' => 'sermon-page']) }}">← Back to sermons</a><p class="eyebrow">{{ $sermon->youtube_live_status && $sermon->youtube_live_status !== 'none' ? ucfirst($sermon->youtube_live_status) : 'Sermon message' }}</p><h1>{{ $sermon->title }}</h1><p class="sermon-detail-meta"><span>{{ $sermon->speaker ?: 'Teaching team' }}</span><span class="sermon-detail-meta-separator">·</span><span class="sermon-detail-date">▣ {{ $sermon->preached_at?->format('F d, Y') ?: 'Recent message' }}</span></p><div class="sermon-detail-actions"><a class="sermon-detail-action" href="{{ $settings['giving_button_url'] ?? '#contact' }}">Give <span>♥</span></a>@if ($youtubeId)<a class="sermon-detail-action" href="{{ $videoUrl }}" target="_blank" rel="noreferrer">Subscribe <span>▣</span></a>@endif</div>
                <div class="sermon-detail-player">
                    @if ($youtubeId)
                        <button type="button" class="sermon-detail-poster" data-youtube-id="{{ $youtubeId }}" aria-label="Play sermon"><img src="{{ $thumbnailUrl ?: 'https://img.youtube.com/vi/'.$youtubeId.'/hqdefault.jpg' }}" alt="{{ $sermon->title }}"><span class="sermon-detail-play">▶</span></button>
                    @elseif ($isFileVideo)
                        <video controls playsinline preload="metadata" @if ($thumbnailUrl) poster="{{ $thumbnailUrl }}" @endif><source src="{{ $assetUrl($videoUrl) }}"></video>
                    @elseif ($sermon->audio_url)
                        <audio controls src="{{ $assetUrl($sermon->audio_url) }}"></audio>
                    @else
                        <div class="sermon-detail-empty">No media player is available for this sermon yet.</div>
                    @endif
                </div>
                @if ($sermon->summary || $sermon->scripture)<div class="sermon-detail-copy">@if ($sermon->scripture)<p class="eyebrow">{{ $sermon->scripture }}</p>@endif<h2>About this message</h2><p>{{ $sermon->summary ?: 'A message from our church family.' }}</p></div>@endif
                @if ($settings['sermon_next_step_enabled'] ?? true)
                    @php($nextSteps = collect(['one', 'two', 'three'])->map(fn ($step) => ['icon' => $settings['sermon_next_step_'.$step.'_icon'] ?? '♡', 'title' => $settings['sermon_next_step_'.$step.'_title'] ?? '', 'body' => $settings['sermon_next_step_'.$step.'_body'] ?? '', 'link_label' => $settings['sermon_next_step_'.$step.'_link_label'] ?? 'Learn more', 'link' => $settings['sermon_next_step_'.$step.'_link'] ?? '#contact'])->filter(fn ($step) => filled($step['title'])) )
                    <section class="sermon-next-step"><div class="section-heading centered"><p class="eyebrow">{{ $settings['sermon_next_step_kicker'] ?? 'Your next step' }}</p><h2>{{ $settings['sermon_next_step_heading'] ?? 'Take your next step of faith' }}</h2><p class="lead">{{ $settings['sermon_next_step_body'] ?? '' }}</p></div><div class="sermon-next-step-grid">@foreach ($nextSteps as $step)<article><span class="sermon-next-step-icon">{{ $step['icon'] }}</span><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p><a href="{{ $step['link'] }}">{{ $step['link_label'] }} <span>→</span></a></article>@endforeach</div></section>
                @endif
            </div></main>
        @if ($relatedSermons->isNotEmpty())
            <section class="section soft sermon-more-section">
                <div class="container">
                    <div class="section-heading inline-heading"><div><p class="eyebrow">Keep exploring</p><h2>More messages</h2></div><div class="sermon-more-controls"><button type="button" class="sermon-more-control" data-sermon-more-prev aria-label="Previous sermons">←</button><button type="button" class="sermon-more-control" data-sermon-more-next aria-label="Next sermons">→</button></div></div>
                    <div class="sermon-more-viewport" data-sermon-more>
                        <div class="sermon-more-track">
                            <?php foreach ($relatedSermons->take(6) as $related): ?>
                                <?php
                                    $relatedYoutubeId = $related->youtube_video_id;
                                    if (!$relatedYoutubeId && preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|live\/|embed\/))([^?&\/]+)/i', (string) $related->video_url, $relatedMatches)) $relatedYoutubeId = $relatedMatches[1];
                                    $relatedThumbnail = $assetUrl($related->thumbnail_url) ?: ($relatedYoutubeId ? 'https://img.youtube.com/vi/'.$relatedYoutubeId.'/hqdefault.jpg' : null);
                                ?>
                                <a class="card sermon-related-card" href="{{ route('website.public.sermons.show', ['church' => $church->slug, 'sermon' => $related]) }}">
                                    <?php if ($relatedThumbnail): ?>
                                        <img src="{{ $relatedThumbnail }}" alt="{{ $related->title }}">
                                    <?php else: ?>
                                        <div class="sermon-related-placeholder">MESSAGE</div>
                                    <?php endif; ?>
                                    <h3>{{ $related->title }}</h3>
                                    <p>{{ $related->speaker ?: 'Teaching team' }}</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        @endif
        <footer><div class="container footer-row"><span>© {{ now()->year }} {{ $settings['site_name'] }}</span><span>{{ $settings['tagline'] }}</span></div></footer>
    </div>
    <script src="{{ asset('js/website/templates/main.js') }}?v={{ filemtime(public_path('js/website/templates/main.js')) }}" defer></script>
    <script>document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-youtube-id]').forEach(function(button){button.addEventListener('click',function(){var iframe=document.createElement('iframe');iframe.src='https://www.youtube-nocookie.com/embed/'+button.dataset.youtubeId+'?autoplay=1';iframe.title={{ Js::from($sermon->title) }};iframe.allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';iframe.allowFullscreen=true;button.replaceWith(iframe);});});var carousel=document.querySelector('[data-sermon-more]');if(carousel){var track=carousel.querySelector('.sermon-more-track'),index=0;var move=function(direction){var cards=track.querySelectorAll('.sermon-related-card');if(!cards.length)return;var visible=window.matchMedia('(max-width: 700px)').matches?1:4;var max=Math.max(0,cards.length-visible);index=Math.max(0,Math.min(max,index+direction));track.style.transform='translateX(-'+cards[index].offsetLeft+'px)';};document.querySelector('[data-sermon-more-prev]')?.addEventListener('click',function(){move(-1);});document.querySelector('[data-sermon-more-next]')?.addEventListener('click',function(){move(1);});window.addEventListener('resize',function(){move(0);});}});</script>
</body>
</html>
