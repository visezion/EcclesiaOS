<x-app-layout title="Calendar" :breadcrumbs="$breadcrumbs">
    @php
        $start = $month->copy()->startOfMonth()->startOfWeek();
        $end = $month->copy()->endOfMonth()->endOfWeek();
        $monthSessions = collect($monthSessions ?? []);
        $today = now()->toDateString();
        $upcoming = $monthSessions->filter(fn ($session) => $session->session_date->gte(now()->startOfDay()))->sortBy(['session_date', 'starts_at'])->take(6);
        $statCards = [
            ['label' => 'Month Sessions', 'value' => $monthSessions->count(), 'hint' => $month->format('F Y'), 'icon' => 'calendar-days', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Physical Meetings', 'value' => $monthSessions->where('meeting_type', 'physical')->count(), 'hint' => 'venue scheduled', 'icon' => 'map-pin', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Online / Hybrid', 'value' => $monthSessions->whereIn('meeting_type', ['online', 'hybrid'])->count(), 'hint' => 'built-in rooms', 'icon' => 'video', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Attendance Windows', 'value' => $monthSessions->filter(fn ($session) => filled($session->attendanceSession))->count(), 'hint' => 'check-in configured', 'icon' => 'clipboard-check', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
        $typeTone = [
            'physical' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'online' => 'bg-blue-50 text-blue-700 ring-blue-100',
            'hybrid' => 'bg-violet-50 text-violet-700 ring-violet-100',
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="calendar-days" class="size-7"></i>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <a href="{{ route('programs.index') }}" class="text-violet-600 hover:text-violet-700">Programs & Attendance</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <span>Calendar</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-slate-950">Calendar</h1>
                    <p class="text-sm text-slate-500">All event sessions, meetings, venue bookings, and attendance windows from one shared source.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('calendar.index', ['month' => now()->format('Y-m-01')]) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="calendar-check" class="size-4"></i>
                    Today
                </a>
                <a href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m-01')]) }}" class="inline-grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50" aria-label="Previous month">
                    <i data-lucide="chevron-left" class="size-4"></i>
                </a>
                <a href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m-01')]) }}" class="inline-grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50" aria-label="Next month">
                    <i data-lucide="chevron-right" class="size-4"></i>
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

        <section class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <main class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">{{ $month->format('F Y') }}</h2>
                        <p class="text-sm text-slate-500">Calendar, meetings, and attendance windows are linked to event sessions.</p>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1.5"><i class="size-2.5 rounded-full bg-violet-500"></i>Event Session</span>
                        <span class="inline-flex items-center gap-1.5"><i class="size-2.5 rounded-full bg-blue-500"></i>Online Room</span>
                        <span class="inline-flex items-center gap-1.5"><i class="size-2.5 rounded-full bg-emerald-500"></i>Attendance</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <div class="grid min-w-[980px] grid-cols-7 text-sm">
                        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                            <div class="border-b border-slate-200 bg-slate-50 p-3 text-center text-xs uppercase text-slate-500">{{ $day }}</div>
                        @endforeach
                        @for($date = $start->copy(); $date->lte($end); $date->addDay())
                            @php $daySessions = $sessionsByDate[$date->toDateString()] ?? collect(); @endphp
                            <div class="min-h-36 border-b border-r border-slate-100 p-2 {{ $date->month !== $month->month ? 'bg-slate-50/70 text-slate-400' : 'bg-white' }}">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="grid size-7 place-items-center rounded-full text-xs {{ $date->toDateString() === $today ? 'bg-violet-600 text-white' : 'text-slate-600' }}">{{ $date->format('j') }}</span>
                                    @if($daySessions->count() > 3)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500">+{{ $daySessions->count() - 3 }}</span>
                                    @endif
                                </div>
                                <div class="space-y-1.5">
                                    @foreach($daySessions->take(3) as $session)
                                        <a href="{{ route('event-sessions.meeting', $session) }}" class="block rounded-lg px-2 py-1.5 text-xs ring-1 {{ $typeTone[$session->meeting_type] ?? $typeTone['physical'] }}">
                                            <span class="block truncate font-medium">{{ $session->title }}</span>
                                            <span class="mt-0.5 flex items-center gap-1 text-[10px] opacity-80"><i data-lucide="clock" class="size-3"></i>{{ Str::of($session->starts_at)->substr(0,5) }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-950">Upcoming Sessions</h2>
                        <a href="{{ route('meetings.index') }}" class="text-xs font-medium text-violet-600 hover:text-violet-700">View meetings</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100">
                        @forelse($upcoming as $session)
                            <a href="{{ route('event-sessions.meeting', $session) }}" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="calendar-days" class="size-4"></i></span>
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-slate-950">{{ $session->title }}</span>
                                    <span class="block text-xs text-slate-500">{{ $session->session_date->format('M d') }} · {{ Str::of($session->starts_at)->substr(0,5) }} · {{ Str::headline($session->meeting_type) }}</span>
                                </span>
                            </a>
                        @empty
                            <p class="py-6 text-sm text-slate-500">No upcoming sessions this month.</p>
                        @endforelse
                    </div>
                </section>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Shared Source</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([['Program', 'Main activity container', 'layout-list'], ['Event', 'Specific activity under a program', 'calendar-plus'], ['Session', 'Exact date and time', 'clock'], ['Attendance', 'Check-in and final records', 'badge-check']] as [$label, $copy, $icon])
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
