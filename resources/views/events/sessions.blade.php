<x-app-layout title="Event Sessions" :breadcrumbs="$breadcrumbs">
    @php
        $statusStyles = [
            'scheduled' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'draft' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'completed' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100',
        ];
        $typeStyles = [
            'physical' => ['label' => 'Physical', 'icon' => 'map-pin', 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
            'online' => ['label' => 'Online', 'icon' => 'video', 'tone' => 'bg-blue-50 text-blue-700 ring-blue-100'],
            'hybrid' => ['label' => 'Hybrid', 'icon' => 'radio-tower', 'tone' => 'bg-violet-50 text-violet-700 ring-violet-100'],
        ];
        $activeFilters = filled(request('q')) || filled(request('status')) || filled(request('meeting_type')) || filled(request('date'));
        $selectedSession = $sessions->getCollection()->first();
        $enabledCount = count($enabledMeetingProviders);
        $visibleMeetingLinks = fn ($links) => collect($links ?? [])
            ->filter(fn ($link) => is_array($link) && (! array_key_exists('enabled', $link) || filter_var($link['enabled'], FILTER_VALIDATE_BOOLEAN)));
        $statCards = [
            ['label' => 'Event Sessions', 'value' => $stats['total'] ?? $sessions->total(), 'hint' => 'exact dates and times', 'icon' => 'clock', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Physical', 'value' => $stats['physical'] ?? 0, 'hint' => 'venue based', 'icon' => 'map-pin', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Online', 'value' => $stats['online'] ?? 0, 'hint' => 'built-in room only', 'icon' => 'video', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Hybrid', 'value' => $stats['hybrid'] ?? 0, 'hint' => 'venue plus room', 'icon' => 'radio-tower', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
    @endphp

    <div x-data="{ createOpen: {{ $errors->any() || request()->fullUrlIs('*#new-session') ? 'true' : 'false' }} }" x-init="if (window.location.hash === '#new-session') createOpen = true" class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="clock" class="size-7"></i>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <a href="{{ route('programs.index') }}" class="text-violet-600 hover:text-violet-700">Programs</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <a href="{{ route('programs.events', $program) }}" class="text-violet-600 hover:text-violet-700">{{ $program->name }}</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <span>Event Sessions</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-slate-950">{{ $event->title }}</h1>
                    <p class="text-sm text-slate-500">Manage exact event times, venues, built-in online rooms, and attendance setup.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="calendar-days" class="size-4"></i>
                    Calendar
                </a>
                <button id="new-session" type="button" @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    New Event Session
                </button>
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
                <form method="GET" action="{{ route('event-sessions.index', [$program, $event]) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 xl:grid-cols-[1fr_160px_160px_165px_auto_auto] xl:items-end">
                        <label class="text-sm text-slate-600">
                            Search Sessions
                            <span class="relative mt-1 block">
                                <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search title, venue, or address...">
                                <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            </span>
                        </label>
                        <label class="text-sm text-slate-600">
                            Date
                            <input name="date" value="{{ request('date') }}" type="date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
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
                            <a href="{{ route('event-sessions.index', [$program, $event]) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Clear</a>
                        @endif
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Event Sessions</h2>
                            <p class="text-sm text-slate-500">Showing {{ $sessions->firstItem() ?? 0 }} to {{ $sessions->lastItem() ?? 0 }} of {{ number_format($sessions->total()) }} sessions</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-700 ring-1 ring-blue-100">
                            <i data-lucide="video" class="size-3.5"></i>
                            {{ $enabledCount }} built-in methods enabled
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[960px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Session</th>
                                    <th class="px-5 py-3">Date & Time</th>
                                    <th class="px-5 py-3">Type</th>
                                    <th class="px-5 py-3">Venue / Room</th>
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
                                        $attendance = $session->attendanceSession;
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="max-w-sm px-5 py-4">
                                            <a href="{{ route('event-sessions.meeting', $session) }}" class="font-medium text-slate-950 hover:text-violet-600">{{ $session->title }}</a>
                                            <div class="mt-1 text-xs text-slate-500">{{ $session->campus?->name ?? $program->campus?->name ?? 'Campus not assigned' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-900">{{ $session->session_date->format('M d, Y') }}</div>
                                            <div class="text-xs text-slate-500">{{ Str::of($session->starts_at)->substr(0,5) }}{{ $session->ends_at ? ' - '.Str::of($session->ends_at)->substr(0,5) : '' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs ring-1 {{ $type['tone'] }}">
                                                <i data-lucide="{{ $type['icon'] }}" class="size-3.5"></i>
                                                {{ $type['label'] }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-900">{{ $session->venue ?: ($links->keys()->map(fn ($key) => Str::headline($key))->join(', ') ?: 'Not set') }}</div>
                                            <div class="text-xs text-slate-500">{{ $session->address ?: ($links->count().' selected online room(s)') }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            @if($attendance)
                                                <a href="{{ route('event-sessions.attendance', $session) }}" class="text-violet-600 hover:text-violet-700">{{ Str::headline($attendance->status) }}</a>
                                                <div class="text-xs text-slate-500">{{ count($attendance->methods ?? []) }} methods</div>
                                            @else
                                                <span class="text-slate-500">Not created</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusStyles[$session->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ Str::headline($session->status) }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <a href="{{ route('event-sessions.meeting', $session) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Meeting setup"><i data-lucide="settings" class="size-4"></i></a>
                                            <a href="{{ route('event-sessions.attendance', $session) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Attendance session"><i data-lucide="clipboard-check" class="size-4"></i></a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-14 text-center">
                                            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="clock" class="size-6"></i></div>
                                            <h2 class="mt-3 text-base font-semibold text-slate-950">No sessions found</h2>
                                            <p class="mt-1 text-sm text-slate-500">Create a session or clear filters to see existing records.</p>
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
                    <h2 class="text-base font-semibold text-slate-950">Session Setup</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([['Calendar', 'Exact date and time', 'calendar-days'], ['Meeting', 'Physical, online, or hybrid', 'video'], ['Attendance', 'Verification policy and methods', 'clipboard-check'], ['Record', 'One final record per person', 'badge-check']] as [$label, $copy, $icon])
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-slate-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                <div><div class="font-medium text-slate-900">{{ $label }}</div><div class="text-xs text-slate-500">{{ $copy }}</div></div>
                            </div>
                        @endforeach
                    </div>
                </section>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Selected Session</h2>
                    @if($selectedSession)
                        @php $selectedLinks = $visibleMeetingLinks($selectedSession->meeting_links); @endphp
                        <div class="mt-4 rounded-lg bg-violet-50 p-4 ring-1 ring-violet-100">
                            <h3 class="text-base font-semibold text-slate-950">{{ $selectedSession->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $selectedSession->session_date->format('M d, Y') }} at {{ Str::of($selectedSession->starts_at)->substr(0,5) }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse($selectedLinks->keys() as $provider)
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs text-violet-700 ring-1 ring-violet-100">{{ Str::headline($provider) }}</span>
                                @empty
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs text-slate-600 ring-1 ring-slate-200">No online room selected</span>
                                @endforelse
                            </div>
                            <a href="{{ route('event-sessions.meeting', $selectedSession) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-violet-600">Open meeting <i data-lucide="arrow-right" class="size-4"></i></a>
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-500">No session is available for the current filters.</p>
                    @endif
                </section>
            </aside>
        </section>

        <div x-cloak x-show="createOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false"></div>
        <aside x-cloak x-show="createOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-lg overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">New Event Session</h2>
                    <p class="text-sm text-slate-500">Create the exact schedule, venue, and allowed built-in rooms.</p>
                </div>
                <button type="button" @click="createOpen = false" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close"><i data-lucide="x" class="size-5"></i></button>
            </div>
            <form method="POST" action="{{ route('event-sessions.store', [$program, $event]) }}" class="space-y-4">
                @csrf
                <label class="block text-sm text-slate-600">Session Title
                    <input name="title" required value="{{ old('title', $event->title) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                </label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block text-sm text-slate-600">Date
                        <input name="session_date" required type="date" value="{{ old('session_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block text-sm text-slate-600">Start
                        <input name="starts_at" required type="time" value="{{ old('starts_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block text-sm text-slate-600">End
                        <input name="ends_at" type="time" value="{{ old('ends_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm text-slate-600">Meeting Type
                        <select name="meeting_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            @foreach($typeStyles as $type => $meta)
                                <option value="{{ $type }}" @selected(old('meeting_type') === $type)>{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm text-slate-600">Campus
                        <select name="campus_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            <option value="">Program campus</option>
                            @foreach($campuses as $campus)
                                <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="block text-sm text-slate-600">Venue
                    <input name="venue" value="{{ old('venue') }}" placeholder="Main Auditorium" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                </label>
                <label class="block text-sm text-slate-600">Address
                    <input name="address" value="{{ old('address') }}" placeholder="123 Kingdom Way" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm text-slate-600">Capacity
                        <input name="capacity" type="number" min="0" value="{{ old('capacity') }}" placeholder="500" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block text-sm text-slate-600">Status
                        <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            <option value="scheduled">Scheduled</option>
                            <option value="draft">Draft</option>
                        </select>
                    </label>
                </div>
                <section class="rounded-lg border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-950">Built-in Online Rooms</h3>
                    <p class="mt-1 text-xs text-slate-500">Only enabled meeting methods appear here. Users only see selected rooms at check-in.</p>
                    <div class="mt-3 space-y-3">
                        @forelse($enabledMeetingProviders as $provider => $meta)
                            <div class="rounded-lg bg-slate-50 p-3">
                                <label class="mb-2 flex items-center justify-between gap-3 text-sm text-slate-700">
                                    <span class="inline-flex items-center gap-2"><i data-lucide="{{ $meta['icon'] }}" class="size-4 text-violet-600"></i>{{ $meta['label'] }}</span>
                                    <input type="checkbox" name="meeting_links[{{ $provider }}][enabled]" value="1" @checked(old("meeting_links.$provider.enabled")) class="rounded border-slate-300 text-violet-600">
                                </label>
                                <div class="grid gap-2 sm:grid-cols-[1fr_120px]">
                                    <input name="meeting_links[{{ $provider }}][room]" value="{{ old("meeting_links.$provider.room") }}" placeholder="{{ Str::slug($meta['label']) }} room ID" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input name="meeting_links[{{ $provider }}][access_code]" value="{{ old("meeting_links.$provider.access_code") }}" placeholder="Code" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">No built-in meeting methods are enabled. Enable them in Meeting Method Setup first.</div>
                        @endforelse
                    </div>
                </section>
                <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="save" class="size-4"></i>
                    Create Session
                </button>
            </form>
        </aside>
    </div>
</x-app-layout>
