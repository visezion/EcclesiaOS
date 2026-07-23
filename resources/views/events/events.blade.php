<x-app-layout title="Events" :breadcrumbs="$breadcrumbs">
    @php
        $statusStyles = [
            'scheduled' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'draft' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'completed' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100',
        ];
        $activeFilters = filled(request('q')) || filled(request('status')) || filled(request('type'));
        $statCards = [
            ['label' => 'Events', 'value' => $stats['total'] ?? $events->total(), 'hint' => 'visible program events', 'icon' => 'calendar-plus', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Sessions', 'value' => $stats['sessions'] ?? 0, 'hint' => 'scheduled times', 'icon' => 'clock', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Scheduled', 'value' => $stats['scheduled'] ?? 0, 'hint' => 'ready to run', 'icon' => 'calendar-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Drafts', 'value' => $stats['draft'] ?? 0, 'hint' => 'needs review', 'icon' => 'file-text', 'tone' => 'bg-amber-50 text-amber-600 ring-amber-100'],
        ];
        $selectedEvent = $events->getCollection()->first();
    @endphp

    <div x-data="{ createOpen: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="calendar-plus" class="size-7"></i>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <a href="{{ route('programs.index') }}" class="text-violet-600 hover:text-violet-700">Programs</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <span>{{ $program?->name ?? 'Events' }}</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-slate-950">{{ $program?->name ?? 'Events' }}</h1>
                    <p class="text-sm text-slate-500">{{ $program?->description ?? 'Events grouped under programs with sessions, meetings, and attendance.' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="calendar-days" class="size-4"></i>
                    Calendar
                </a>
                <a href="{{ route('meetings.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="video" class="size-4"></i>
                    Meetings
                </a>
                @if($program)
                    <button type="button" @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                        <i data-lucide="plus" class="size-4"></i>
                        New Event
                    </button>
                @endif
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

        <section class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <main class="space-y-4">
                <form method="GET" action="{{ $program ? route('programs.events', $program) : route('events.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 xl:grid-cols-[230px_1fr_170px_170px_auto_auto] xl:items-end">
                        <label class="text-sm text-slate-600">
                            Program
                            <select onchange="if(this.value) location.href=this.value" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="{{ route('events.index') }}">All Programs</option>
                                @foreach($programs as $item)
                                    <option value="{{ route('programs.events', $item) }}" @selected($program?->id === $item->id)>{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm text-slate-600">
                            Search Events
                            <span class="relative mt-1 block">
                                <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search event name, venue, or description...">
                                <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            </span>
                        </label>
                        <label class="text-sm text-slate-600">
                            Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Statuses</option>
                                @foreach(['scheduled' => 'Scheduled', 'draft' => 'Draft', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm text-slate-600">
                            Type
                            <input name="type" value="{{ request('type') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Service">
                        </label>
                        <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">
                            <i data-lucide="sliders-horizontal" class="size-4"></i>
                            Apply
                        </button>
                        @if($activeFilters)
                            <a href="{{ $program ? route('programs.events', $program) : route('events.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Clear</a>
                        @endif
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Event Directory</h2>
                            <p class="text-sm text-slate-500">Showing {{ $events->firstItem() ?? 0 }} to {{ $events->lastItem() ?? 0 }} of {{ number_format($events->total()) }} events</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs text-violet-700 ring-1 ring-violet-100">
                            <i data-lucide="database" class="size-3.5"></i>
                            Database backed
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Event</th>
                                    <th class="px-5 py-3">Program</th>
                                    <th class="px-5 py-3">Schedule</th>
                                    <th class="px-5 py-3">Type</th>
                                    <th class="px-5 py-3">Sessions</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($events as $event)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="max-w-sm px-5 py-4">
                                            <a href="{{ $event->program ? route('event-sessions.index', [$event->program, $event]) : route('events.index') }}" class="font-medium text-slate-950 hover:text-violet-600">{{ $event->title }}</a>
                                            <div class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $event->description ?: ($event->venue ?: 'No description recorded.') }}</div>
                                        </td>
                                        <td class="px-5 py-4">{{ $event->program?->name ?? 'Unassigned' }}</td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-900">{{ $event->starts_at?->format('M d, Y') ?? 'Not set' }}</div>
                                            <div class="text-xs text-slate-500">{{ $event->starts_at?->format('h:i A') }}{{ $event->ends_at ? ' - '.$event->ends_at->format('h:i A') : '' }}</div>
                                        </td>
                                        <td class="px-5 py-4">{{ $event->event_type ?? $event->category ?? 'Event' }}</td>
                                        <td class="px-5 py-4">{{ number_format($event->sessions_count) }}</td>
                                        <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusStyles[$event->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ Str::headline($event->status) }}</span></td>
                                        <td class="px-5 py-4 text-right">
                                            @if($event->program)
                                                <a href="{{ route('event-sessions.index', [$event->program, $event]) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="View sessions"><i data-lucide="eye" class="size-4"></i></a>
                                                <a href="{{ route('event-sessions.index', [$event->program, $event]) }}#new-session" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Add session"><i data-lucide="plus" class="size-4"></i></a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-14 text-center">
                                            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="calendar-plus" class="size-6"></i></div>
                                            <h2 class="mt-3 text-base font-semibold text-slate-950">No events found</h2>
                                            <p class="mt-1 text-sm text-slate-500">Create an event or clear filters to see existing records.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $events->links() }}</div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Event Flow</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([['Event', 'Define activity details', 'calendar-plus'], ['Session', 'Set exact date and venue', 'clock'], ['Meeting', 'Attach physical or built-in online room', 'video'], ['Attendance', 'Open verification window', 'clipboard-check']] as [$label, $copy, $icon])
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-slate-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                <div><div class="font-medium text-slate-900">{{ $label }}</div><div class="text-xs text-slate-500">{{ $copy }}</div></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Selected Event</h2>
                    @if($selectedEvent)
                        <div class="mt-4 rounded-lg bg-violet-50 p-4 ring-1 ring-violet-100">
                            <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusStyles[$selectedEvent->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ Str::headline($selectedEvent->status) }}</span>
                            <h3 class="mt-3 text-base font-semibold text-slate-950">{{ $selectedEvent->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ Str::limit($selectedEvent->description ?: $selectedEvent->venue, 130) }}</p>
                            @if($selectedEvent->program)
                                <a href="{{ route('event-sessions.index', [$selectedEvent->program, $selectedEvent]) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-violet-600">Open sessions <i data-lucide="arrow-right" class="size-4"></i></a>
                            @endif
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-500">No event is available for the current filters.</p>
                    @endif
                </section>
            </aside>
        </section>

        @if($program)
            <div x-cloak x-show="createOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false"></div>
            <aside x-cloak x-show="createOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">New Event</h2>
                        <p class="text-sm text-slate-500">Create a specific activity under {{ $program->name }}.</p>
                    </div>
                    <button type="button" @click="createOpen = false" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close"><i data-lucide="x" class="size-5"></i></button>
                </div>
                <form method="POST" action="{{ route('programs.events.store', $program) }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm text-slate-600">Event Name
                        <input name="title" required value="{{ old('title') }}" placeholder="Opening Service" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block text-sm text-slate-600">Description
                        <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Purpose and expected outcome">{{ old('description') }}</textarea>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm text-slate-600">Event Type
                            <input name="event_type" value="{{ old('event_type') }}" placeholder="Service, Workshop..." class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        </label>
                        <label class="block text-sm text-slate-600">Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="scheduled">Scheduled</option>
                                <option value="draft">Draft</option>
                            </select>
                        </label>
                    </div>
                    <label class="block text-sm text-slate-600">Starts At
                        <input name="starts_at" type="datetime-local" required value="{{ old('starts_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block text-sm text-slate-600">Ends At
                        <input name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block text-sm text-slate-600">Venue
                        <input name="venue" value="{{ old('venue') }}" placeholder="Main Auditorium" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                        <i data-lucide="save" class="size-4"></i>
                        Create Event
                    </button>
                </form>
            </aside>
        @endif
    </div>
</x-app-layout>
