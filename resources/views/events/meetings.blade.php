<x-app-layout title="Meetings" :breadcrumbs="$breadcrumbs">
    @php
        $typeStyles = [
            'physical' => ['label' => 'Physical', 'icon' => 'map-pin', 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
            'online' => ['label' => 'Online', 'icon' => 'video', 'tone' => 'bg-blue-50 text-blue-700 ring-blue-100'],
            'hybrid' => ['label' => 'Hybrid', 'icon' => 'radio-tower', 'tone' => 'bg-violet-50 text-violet-700 ring-violet-100'],
        ];
        $providerIcons = [
            'zoom' => 'video',
            'google_meet' => 'calendar-clock',
            'jitsi' => 'radio',
            'livekit' => 'radio-tower',
        ];
        $statusStyles = [
            'scheduled' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'draft' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'completed' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100',
        ];
        $activeFilters = filled(request('q')) || filled(request('meeting_type')) || filled(request('status'));
        $connectedIntegrations = collect($integrations)->where('enabled', true)->count();
        $visibleMeetingLinks = fn ($links) => collect($links ?? [])
            ->filter(fn ($link) => is_array($link) && (! array_key_exists('enabled', $link) || filter_var($link['enabled'], FILTER_VALIDATE_BOOLEAN)));
        $statCards = [
            ['label' => 'Meetings', 'value' => $stats['total'] ?? $sessions->total(), 'hint' => 'upcoming sessions', 'icon' => 'video', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Physical', 'value' => $stats['physical'] ?? 0, 'hint' => 'venue only', 'icon' => 'map-pin', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Online', 'value' => $stats['online'] ?? 0, 'hint' => 'built-in rooms', 'icon' => 'radio-tower', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Attendance Marked', 'value' => $stats['attendance'] ?? 0, 'hint' => 'current page records', 'icon' => 'badge-check', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="video" class="size-7"></i>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <a href="{{ route('programs.index') }}" class="text-violet-600 hover:text-violet-700">Programs & Attendance</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <span>Meetings</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-slate-950">Meetings</h1>
                    <p class="text-sm text-slate-500">Physical and built-in online meetings connected to sessions and attendance automation.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('meeting-integrations.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="settings" class="size-4"></i>
                    Built-in Setup
                </a>
                <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="calendar-days" class="size-4"></i>
                    Calendar
                </a>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($statCards as $card)
                <article class="dashboard-card">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl text-slate-950">{{ number_format($card['value']) }}</div>
                            <div class="text-xs text-slate-500">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_350px]">
            <main class="space-y-4">
                <form method="GET" action="{{ route('meetings.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 xl:grid-cols-[1fr_180px_180px_auto_auto] xl:items-end">
                        <label class="text-sm text-slate-600">
                            Search Meetings
                            <span class="relative mt-1 block">
                                <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search meeting, venue, or program...">
                                <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            </span>
                        </label>
                        <label class="text-sm text-slate-600">
                            Meeting Type
                            <select name="meeting_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Types</option>
                                @foreach($typeStyles as $type => $meta)
                                    <option value="{{ $type }}" @selected(request('meeting_type') === $type)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm text-slate-600">
                            Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Statuses</option>
                                @foreach($statusStyles as $status => $style)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">
                            <i data-lucide="sliders-horizontal" class="size-4"></i>
                            Apply
                        </button>
                        @if($activeFilters)
                            <a href="{{ route('meetings.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Clear</a>
                        @endif
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Meeting Directory</h2>
                            <p class="text-sm text-slate-500">Showing {{ $sessions->firstItem() ?? 0 }} to {{ $sessions->lastItem() ?? 0 }} of {{ number_format($sessions->total()) }} meetings</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs text-emerald-700 ring-1 ring-emerald-100">
                            <i data-lucide="shield-check" class="size-3.5"></i>
                            {{ $connectedIntegrations }} providers enabled
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1020px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Meeting</th>
                                    <th class="px-5 py-3">Type</th>
                                    <th class="px-5 py-3">Date & Time</th>
                                    <th class="px-5 py-3">Venue / Built-in Rooms</th>
                                    <th class="px-5 py-3">Attendance</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($sessions as $session)
                                    @php
                                        $type = $typeStyles[$session->meeting_type] ?? $typeStyles['physical'];
                                        $links = $visibleMeetingLinks($session->meeting_links);
                                        $attendanceCount = (int) ($session->attendanceSession?->records()->count() ?? 0);
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="max-w-sm px-5 py-4">
                                            <a class="font-medium text-slate-950 hover:text-violet-600" href="{{ route('event-sessions.meeting', $session) }}">{{ $session->title }}</a>
                                            <div class="mt-1 text-xs text-slate-500">{{ $session->event->program?->name ?? 'Program not assigned' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs ring-1 {{ $type['tone'] }}">
                                                <i data-lucide="{{ $type['icon'] }}" class="size-3.5"></i>
                                                {{ $type['label'] }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-900">{{ $session->session_date->format('M d, Y') }}</div>
                                            <div class="text-xs text-slate-500">{{ Str::of($session->starts_at)->substr(0,5) }}{{ $session->ends_at ? ' - '.Str::of($session->ends_at)->substr(0,5) : '' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-900">{{ $session->venue ?: 'Built-in online room' }}</div>
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                @forelse($links->keys() as $provider)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200">
                                                        <i data-lucide="{{ $providerIcons[$provider] ?? 'radio' }}" class="size-3"></i>
                                                        {{ Str::headline($provider) }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-slate-500">{{ $session->address ?: 'No online room selected' }}</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <a href="{{ route('event-sessions.attendance', $session) }}" class="text-violet-600 hover:text-violet-700">{{ number_format($attendanceCount) }} records</a>
                                            <div class="text-xs text-slate-500">{{ $session->attendanceSession?->status ? Str::headline($session->attendanceSession->status) : 'Not configured' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusStyles[$session->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ Str::headline($session->status) }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <a href="{{ route('event-sessions.meeting', $session) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Meeting details"><i data-lucide="settings" class="size-4"></i></a>
                                            <a href="{{ route('event-sessions.attendance', $session) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Attendance setup"><i data-lucide="clipboard-check" class="size-4"></i></a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-14 text-center">
                                            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="video" class="size-6"></i></div>
                                            <h2 class="mt-3 text-base font-semibold text-slate-950">No meetings scheduled</h2>
                                            <p class="mt-1 text-sm text-slate-500">Create event sessions to populate physical and online meetings.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $sessions->links() }}</div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-950">Built-in Room Providers</h2>
                        <a href="{{ route('meeting-integrations.index') }}" class="text-xs font-medium text-violet-600 hover:text-violet-700">Manage</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100">
                        @forelse($integrations as $integration)
                            @php
                                $provider = $integration->provider;
                                $settings = $integration->settings ?? [];
                            @endphp
                            <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded-lg {{ $integration->enabled ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-50 text-slate-400' }}">
                                        <i data-lucide="{{ $providerIcons[$provider] ?? 'radio' }}" class="size-4"></i>
                                    </span>
                                    <div>
                                        <div class="font-medium text-slate-900">{{ Str::headline($provider) }}</div>
                                        <div class="text-xs text-slate-500">{{ $settings['room_prefix'] ?? 'No prefix configured' }}</div>
                                    </div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $integration->enabled ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $integration->enabled ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                        @empty
                            <p class="py-6 text-sm text-slate-500">No meeting providers configured.</p>
                        @endforelse
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Attendance Automation</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([['Online Join', 'User enters the selected built-in room', 'log-in'], ['Webhook', 'Room presence marks evidence', 'radio-tower'], ['Final Record', 'Best valid method becomes final', 'badge-check']] as [$label, $copy, $icon])
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-slate-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                <div><div class="font-medium text-slate-900">{{ $label }}</div><div class="text-xs text-slate-500">{{ $copy }}</div></div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </section>
    </div>
</x-app-layout>
