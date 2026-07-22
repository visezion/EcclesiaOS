<x-app-layout title="Programs" :breadcrumbs="$breadcrumbs">
    @php
        $statusStyles = [
            'upcoming' => 'bg-blue-50 text-blue-700 ring-blue-100',
            'ongoing' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'completed' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100',
        ];
        $statCards = [
            ['label' => 'Total Programs', 'value' => $stats['programs'], 'hint' => 'all visible initiatives', 'icon' => 'layout-list', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Ongoing', 'value' => $stats['ongoing'], 'hint' => 'active right now', 'icon' => 'clock', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Events', 'value' => $stats['events'], 'hint' => 'under programs', 'icon' => 'calendar-plus', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Attendance', 'value' => $stats['attendance'], 'hint' => 'linked records', 'icon' => 'clipboard-check', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
        $activeFilters = filled(request('q')) || filled(request('status')) || filled(request('campus'));
        $featuredProgram = $programs->first();
    @endphp

    <div x-data="{ createOpen: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="layout-list" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Programs</h1>
                    <p class="text-sm text-slate-500">Plan ministry programs, connect events, schedule sessions, and track attendance from one workflow.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="calendar-days" class="size-4"></i>
                    Calendar
                </a>
                <a href="{{ route('attendance.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="clipboard-check" class="size-4"></i>
                    Attendance
                </a>
                <button type="button" @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    New Program
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

        <section class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <main class="space-y-4">
                <form method="GET" action="{{ route('programs.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 lg:grid-cols-[1fr_180px_220px_auto_auto] lg:items-end">
                        <label class="text-sm text-slate-600">
                            Search Programs
                            <span class="relative mt-1 block">
                                <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search by name or description...">
                                <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            </span>
                        </label>
                        <label class="text-sm text-slate-600">
                            Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Statuses</option>
                                @foreach(['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm text-slate-600">
                            Campus
                            <select name="campus" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Campuses</option>
                                @foreach($campuses as $campus)
                                    <option value="{{ $campus->opaqueId() }}" @selected(request('campus') === $campus->opaqueId())>{{ $campus->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                            <i data-lucide="sliders-horizontal" class="size-4"></i>
                            Apply
                        </button>
                        @if($activeFilters)
                            <a href="{{ route('programs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Clear</a>
                        @endif
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Program Directory</h2>
                            <p class="text-sm text-slate-500">Showing {{ $programs->firstItem() ?? 0 }} to {{ $programs->lastItem() ?? 0 }} of {{ number_format($programs->total()) }} programs</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs text-violet-700 ring-1 ring-violet-100">
                            <i data-lucide="database" class="size-3.5"></i>
                            Database backed
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Program</th>
                                    <th class="px-5 py-3">Campus</th>
                                    <th class="px-5 py-3">Schedule</th>
                                    <th class="px-5 py-3">Events</th>
                                    <th class="px-5 py-3">Sessions</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($programs as $program)
                                    @php
                                        $duration = $program->starts_on && $program->ends_on ? max(1, $program->starts_on->diffInDays($program->ends_on) + 1) : null;
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="max-w-md px-5 py-4">
                                            <a href="{{ route('programs.events', $program) }}" class="font-medium text-slate-950 hover:text-violet-600">{{ $program->name }}</a>
                                            <div class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $program->description ?: 'No description recorded.' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2 text-slate-700">
                                                <span class="grid size-8 place-items-center rounded-lg bg-blue-50 text-blue-600"><i data-lucide="building-2" class="size-4"></i></span>
                                                <span>{{ $program->campus?->name ?? 'All campuses' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-900">{{ $program->starts_on?->format('M d, Y') ?? 'Not scheduled' }}</div>
                                            <div class="text-xs text-slate-500">{{ $program->ends_on?->format('M d, Y') ?? 'No end date' }}{{ $duration ? ' | '.$duration.' day'.($duration === 1 ? '' : 's') : '' }}</div>
                                        </td>
                                        <td class="px-5 py-4">{{ number_format($program->events_count) }}</td>
                                        <td class="px-5 py-4">{{ number_format($program->sessions_count) }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusStyles[$program->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ Str::headline($program->status) }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <a href="{{ route('programs.events', $program) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="View events">
                                                    <i data-lucide="eye" class="size-4"></i>
                                                </a>
                                                <a href="{{ route('programs.events', $program) }}" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Manage events">
                                                    <i data-lucide="arrow-right" class="size-4"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-14 text-center">
                                            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="layout-list" class="size-6"></i></div>
                                            <h2 class="mt-3 text-base font-semibold text-slate-950">No programs found</h2>
                                            <p class="mt-1 text-sm text-slate-500">Create a program or clear filters to see existing records.</p>
                                            <button type="button" @click="createOpen = true" class="mt-4 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">Create Program</button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $programs->links() }}</div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-950">Program Health</h2>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 ring-1 ring-emerald-100">{{ $stats['ongoing'] }} active</span>
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([
                            ['Upcoming', $stats['upcoming'], 'bg-blue-500'],
                            ['Ongoing', $stats['ongoing'], 'bg-emerald-500'],
                            ['Completed', $stats['completed'], 'bg-slate-500'],
                        ] as [$label, $value, $bar])
                            @php($percent = $stats['programs'] > 0 ? round(($value / $stats['programs']) * 100) : 0)
                            <div>
                                <div class="mb-1 flex justify-between text-xs text-slate-500"><span>{{ $label }}</span><span>{{ $percent }}%</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full {{ $bar }}" style="width: {{ $percent }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Next Focus</h2>
                    @if($featuredProgram)
                        <div class="mt-4 rounded-lg bg-violet-50 p-4 ring-1 ring-violet-100">
                            <div class="flex items-center justify-between gap-3">
                                <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusStyles[$featuredProgram->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ Str::headline($featuredProgram->status) }}</span>
                                <span class="text-xs text-slate-500">{{ $featuredProgram->events_count }} events</span>
                            </div>
                            <h3 class="mt-3 text-base font-semibold text-slate-950">{{ $featuredProgram->name }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ Str::limit($featuredProgram->description, 120) ?: 'No description recorded.' }}</p>
                            <a href="{{ route('programs.events', $featuredProgram) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-violet-600">Open program <i data-lucide="arrow-right" class="size-4"></i></a>
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-500">No program is available for the current filters.</p>
                    @endif
                </section>

                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Workflow</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([['Program', 'Main ministry initiative', 'layout-list'], ['Event', 'Specific activity', 'calendar-plus'], ['Session', 'Exact date and time', 'clock'], ['Attendance', 'Final records', 'clipboard-check']] as [$label, $copy, $icon])
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-slate-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                <div><div class="font-medium text-slate-900">{{ $label }}</div><div class="text-xs text-slate-500">{{ $copy }}</div></div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </section>

        <div x-cloak x-show="createOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false"></div>
        <aside x-cloak x-show="createOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">New Program</h2>
                    <p class="text-sm text-slate-500">Create the top-level program before adding events and sessions.</p>
                </div>
                <button type="button" @click="createOpen = false" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close"><i data-lucide="x" class="size-5"></i></button>
            </div>
            <form method="POST" action="{{ route('programs.store') }}" class="space-y-4">
                @csrf
                <label class="block text-sm text-slate-600">Program Name
                    <input name="name" required value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Youth Camp 2026">
                </label>
                <label class="block text-sm text-slate-600">Description
                    <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Purpose, audience, and expected outcomes">{{ old('description') }}</textarea>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm text-slate-600">Starts On
                        <input name="starts_on" type="date" value="{{ old('starts_on') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block text-sm text-slate-600">Ends On
                        <input name="ends_on" type="date" value="{{ old('ends_on') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                </div>
                <label class="block text-sm text-slate-600">Campus
                    <select name="campus_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All campuses</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->opaqueId() }}" @selected(old('campus_id') === $campus->opaqueId())>{{ $campus->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm text-slate-600">Status
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        @foreach(['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', 'upcoming') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    Create Program
                </button>
            </form>
        </aside>
    </div>
</x-app-layout>
