<x-app-layout title="Attendance" :breadcrumbs="$breadcrumbs">
    @php
        $statusStyles = [
            'scheduled' => 'bg-blue-50 text-blue-700 ring-blue-100',
            'open' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'closed' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ];
        $methodIcons = [
            'manual' => 'user-check',
            'qr' => 'scan-qr-code',
            'geolocation' => 'map-pin',
            'kiosk' => 'monitor',
            'face' => 'scan-face',
            'zoom' => 'video',
            'google_meet' => 'calendar-clock',
            'jitsi' => 'radio',
            'livekit' => 'radio-tower',
        ];
        $activeFilters = filled(request('q')) || filled(request('status'));
        $statCards = [
            ['label' => 'Attendance Sessions', 'value' => $stats['sessions'], 'hint' => 'visible sessions', 'icon' => 'clipboard-check', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Open Now', 'value' => $stats['open'], 'hint' => 'ready for check-in', 'icon' => 'badge-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Scheduled', 'value' => $stats['scheduled'], 'hint' => 'upcoming windows', 'icon' => 'calendar-clock', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Final Records', 'value' => $stats['records'], 'hint' => 'stored evidence', 'icon' => 'shield-check', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="clipboard-check" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Attendance Sessions</h1>
                    <p class="text-sm text-slate-500">Manage check-in windows, allowed methods, verification policy, and final attendance records.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="calendar-days" class="size-4"></i>
                    Calendar
                </a>
                <a href="{{ route('meetings.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="video" class="size-4"></i>
                    Meetings
                </a>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($statCards as $card)
                <article class="dashboard-card">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl text-slate-950">{{ number_format($card['value']) }}</div>
                            <div class="text-xs text-slate-500">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <main class="space-y-4">
                <form method="GET" action="{{ route('attendance.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 lg:grid-cols-[1fr_190px_auto_auto] lg:items-end">
                        <label class="text-sm text-slate-600">
                            Search Sessions
                            <span class="relative mt-1 block">
                                <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search session, event, or program...">
                                <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            </span>
                        </label>
                        <label class="text-sm text-slate-600">
                            Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Statuses</option>
                                @foreach(['scheduled' => 'Scheduled', 'open' => 'Open', 'closed' => 'Closed'] as $key => $label)
                                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">
                            <i data-lucide="sliders-horizontal" class="size-4"></i>
                            Apply
                        </button>
                        @if($activeFilters)
                            <a href="{{ route('attendance.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Clear</a>
                        @endif
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Session Directory</h2>
                            <p class="text-sm text-slate-500">Showing {{ $attendanceSessions->firstItem() ?? 0 }} to {{ $attendanceSessions->lastItem() ?? 0 }} of {{ number_format($attendanceSessions->total()) }} sessions</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs text-violet-700 ring-1 ring-violet-100">
                            <i data-lucide="database" class="size-3.5"></i>
                            Final records stored once per member
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Session</th>
                                    <th class="px-5 py-3">Schedule</th>
                                    <th class="px-5 py-3">Methods</th>
                                    <th class="px-5 py-3">Records</th>
                                    <th class="px-5 py-3">Policy</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($attendanceSessions as $attendanceSession)
                                    @php
                                        $eventSession = $attendanceSession->eventSession;
                                        $methods = collect($attendanceSession->methods ?? []);
                                        $recordPercent = $attendanceSession->expected_attendance > 0 ? min(100, round(($attendanceSession->records_count / $attendanceSession->expected_attendance) * 100)) : 0;
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="max-w-sm px-5 py-4">
                                            <a href="{{ $eventSession ? route('event-sessions.attendance', $eventSession) : '#' }}" class="font-medium text-slate-950 hover:text-violet-600">{{ $attendanceSession->title }}</a>
                                            <div class="mt-1 text-xs text-slate-500">{{ $eventSession?->event?->program?->name ?? 'No program' }} | {{ $eventSession?->title ?? 'No event session' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-900">{{ $attendanceSession->opens_at?->format('M d, Y') ?? 'Not set' }}</div>
                                            <div class="text-xs text-slate-500">{{ $attendanceSession->opens_at?->format('h:i A') }} - {{ $attendanceSession->closes_at?->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex max-w-xs flex-wrap gap-1.5">
                                                @foreach($methods->take(4) as $method)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-xs text-slate-600 ring-1 ring-slate-100"><i data-lucide="{{ $methodIcons[$method] ?? 'circle-dot' }}" class="size-3"></i>{{ Str::headline($method) }}</span>
                                                @endforeach
                                                @if($methods->count() > 4)
                                                    <span class="rounded-full bg-violet-50 px-2 py-1 text-xs text-violet-700 ring-1 ring-violet-100">+{{ $methods->count() - 4 }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-950">{{ number_format($attendanceSession->records_count) }} / {{ number_format($attendanceSession->expected_attendance ?: 0) }}</div>
                                            <div class="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-violet-600" style="width: {{ $recordPercent }}%"></div></div>
                                        </td>
                                        <td class="px-5 py-4">{{ Str::headline($attendanceSession->verification_policy) }}</td>
                                        <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusStyles[$attendanceSession->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ Str::headline($attendanceSession->status) }}</span></td>
                                        <td class="px-5 py-4 text-right">
                                            @if($eventSession)
                                                <a href="{{ route('event-sessions.attendance', $eventSession) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Manage attendance"><i data-lucide="settings" class="size-4"></i></a>
                                                <a href="{{ route('attendance.methods', $attendanceSession) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Open check-in"><i data-lucide="scan-line" class="size-4"></i></a>
                                            @endif
                                            <form method="POST" action="{{ route('attendance.destroy', $attendanceSession) }}" onsubmit="return confirm('Delete {{ addslashes($attendanceSession->title) }} and all attendance records for this session?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600" title="Delete attendance session"><i data-lucide="trash-2" class="size-4"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-14 text-center">
                                            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="clipboard-check" class="size-6"></i></div>
                                            <h2 class="mt-3 text-base font-semibold text-slate-950">No attendance sessions found</h2>
                                            <p class="mt-1 text-sm text-slate-500">Create an event session first, then configure its attendance policy.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $attendanceSessions->links() }}</div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Status Overview</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([['Open', $stats['open'], 'bg-emerald-500'], ['Scheduled', $stats['scheduled'], 'bg-blue-500'], ['Closed', $stats['closed'], 'bg-slate-500']] as [$label, $value, $bar])
                            @php($percent = $stats['sessions'] > 0 ? round(($value / $stats['sessions']) * 100) : 0)
                            <div>
                                <div class="mb-1 flex justify-between text-xs text-slate-500"><span>{{ $label }}</span><span>{{ $percent }}%</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full {{ $bar }}" style="width: {{ $percent }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Verification Flow</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([['Session opens', 'Attendance window becomes available', 'clock'], ['Member checks in', 'QR, geolocation, kiosk, manual, or built-in room', 'scan-line'], ['Evidence saved', 'Each method is retained for audit', 'shield-check'], ['Final record', 'One final record per member per session', 'badge-check']] as [$label, $copy, $icon])
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
