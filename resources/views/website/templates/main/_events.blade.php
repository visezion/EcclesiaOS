@php
    $eventLimit = in_array((int) ($component['event_limit'] ?? 3), [3, 6], true) ? (int) $component['event_limit'] : 3;
    $eventItems = collect($events ?? [])->take($eventLimit);
    $eventsUrl = route('website.public', ['church' => $church->slug, 'page' => 'events']);
@endphp
<section class="content-events-widget content-events-widget-{{ $eventLimit }}" style="--event-button-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['event_button_color'] ?? '') ? $component['event_button_color'] : '#6d4aff' }};--event-button-text-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $component['event_button_text_color'] ?? '') ? $component['event_button_text_color'] : '#ffffff' }};">
    <div class="content-events-widget-header">
        <h2 class="content-events-widget-title"><span>Upcoming</span> <em>Events</em></h2>
        @if ($eventItems->isNotEmpty())<a class="content-events-view-all content-events-view-all-top" href="{{ $eventsUrl }}">View all events <span>↗</span></a>@endif
    </div>
    <div class="content-event-list">
    @forelse ($eventItems as $event)
        @php
            $startDate = $event->starts_at?->format('F j, Y');
            $endDate = $event->ends_at?->format('F j, Y');
            $dateLabel = $endDate && $endDate !== $startDate ? $startDate.' – '.$endDate : $startDate;
        @endphp
        <a class="content-event-widget" href="{{ $eventsUrl }}">
            <div class="content-event-poster">
                @if (!empty($event->poster_path))
                    <img src="{{ $assetUrl($event->poster_path) }}" alt="{{ $event->title }}" loading="lazy">
                @else
                    <span aria-hidden="true">✦</span>
                @endif
            </div>
            <div class="content-event-details">
                <p class="content-event-date">{{ $dateLabel }}</p>
                <h3>{{ $event->title }}</h3>
                <p class="content-event-venue">{{ $event->venue ?: ($settings['site_name'] ?? 'At our church') }}</p>
            </div>
            <span class="content-event-action">Learn more <b>→</b></span>
        </a>
    @empty
        <p class="widget-empty">No upcoming events are scheduled yet.</p>
    @endforelse
    </div>
    @if ($eventItems->isNotEmpty())<a class="content-events-view-all" href="{{ $eventsUrl }}">View all events <span>→</span></a>@endif
</section>
