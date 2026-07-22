<x-app-layout title="Calendar" :breadcrumbs="$breadcrumbs">
    @php $start = $month->copy()->startOfMonth()->startOfWeek(); $end = $month->copy()->endOfMonth()->endOfWeek(); @endphp
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"><div class="flex items-center gap-4"><div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600"><i data-lucide="calendar-days" class="size-7"></i></div><div><h1 class="text-2xl font-semibold text-slate-950">Calendar</h1><p class="text-sm text-slate-500">All event sessions, meetings, venue bookings, and attendance schedules in one place.</p></div></div><div class="flex gap-2"><a href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m-01')]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2"><i data-lucide="chevron-left" class="size-4"></i></a><a href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m-01')]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2"><i data-lucide="chevron-right" class="size-4"></i></a></div></div>
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex items-center justify-between"><h2 class="text-lg text-slate-950">{{ $month->format('F Y') }}</h2><div class="flex gap-4 text-xs text-slate-500"><span><i class="mr-1 inline-block size-2 rounded-full bg-violet-500"></i>Event Session</span><span><i class="mr-1 inline-block size-2 rounded-full bg-sky-500"></i>Meeting</span><span><i class="mr-1 inline-block size-2 rounded-full bg-emerald-500"></i>Attendance</span></div></div>
            <div class="grid grid-cols-7 rounded-lg border border-slate-200 text-sm">@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)<div class="border-b border-slate-200 bg-slate-50 p-3 text-center text-xs text-slate-500">{{ $day }}</div>@endforeach
                @for($date = $start->copy(); $date->lte($end); $date->addDay())
                    <div class="min-h-32 border-b border-r border-slate-100 p-2 {{ $date->month !== $month->month ? 'bg-slate-50/60 text-slate-400' : '' }}">
                        <div class="mb-2 text-xs">{{ $date->format('j') }}</div>
                        @foreach(($sessionsByDate[$date->toDateString()] ?? collect())->take(3) as $session)
                            <a href="{{ route('event-sessions.meeting', $session) }}" class="mb-1 block rounded-md bg-violet-50 px-2 py-1 text-xs text-violet-700">{{ $session->title }}<span class="block text-[10px] text-slate-500">{{ Str::of($session->starts_at)->substr(0,5) }}</span></a>
                        @endforeach
                    </div>
                @endfor
            </div>
        </section>
    </div>
</x-app-layout>
