@php
    $tabs = [
        ['Overview', 'communications.index', 'layout-dashboard'],
        ['Notifications', 'communications.notifications', 'bell'],
        ['Templates', 'communications.templates', 'file-search'],
        ['Scheduled', 'communications.scheduled', 'calendar-clock'],
        ['Bulk', 'communications.bulk', 'send'],
        ['Delivery Logs', 'communications.delivery-logs', 'clipboard-list'],
        ['Preferences', 'communications.preferences', 'sliders-horizontal'],
        ['Integrations', 'communications.integrations', 'webhook'],
    ];
@endphp

<nav class="flex gap-2 overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 text-sm shadow-sm">
    @foreach($tabs as [$label, $route, $icon])
        @php($active = request()->routeIs($route))
        <a href="{{ route($route) }}" class="{{ $active ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-violet-50 hover:text-violet-700' }} inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2">
            <i data-lucide="{{ $icon }}" class="size-4"></i>
            {{ $label }}
        </a>
    @endforeach
</nav>
