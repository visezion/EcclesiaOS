<x-app-layout title="Scheduled Messages" :breadcrumbs="$breadcrumbs">
    @php
        $activeStatuses = ['scheduled', 'queued', 'draft', 'partial'];
        $timeline = $campaigns->getCollection()->take(7)->values();
        $calendarStart = $calendarMonth->copy()->startOfWeek();
        $monthQuery = request()->except('page');
        $previousMonthQuery = array_merge($monthQuery, ['month' => $calendarMonth->copy()->subMonth()->format('Y-m')]);
        $nextMonthQuery = array_merge($monthQuery, ['month' => $calendarMonth->copy()->addMonth()->format('Y-m')]);
        $currentMonthQuery = array_merge(request()->except(['page', 'month']), ['month' => now()->format('Y-m')]);
        $scheduledDays = $campaigns->getCollection()
            ->filter(fn ($campaign) => filled($campaign->scheduled_at))
            ->groupBy(fn ($campaign) => $campaign->scheduled_at->format('Y-m-d'));
        $cards = [
            ['label' => 'Scheduled Today', 'value' => $stats['today'], 'hint' => '+ 21% vs yesterday', 'icon' => 'calendar-days', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Upcoming This Week', 'value' => $stats['week'], 'hint' => '+ 15% vs last 7 days', 'icon' => 'clock', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Automation Rules Active', 'value' => $stats['rules'], 'hint' => '+ 8% vs last 30 days', 'icon' => 'bot', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Paused Automations', 'value' => $stats['paused'], 'hint' => '- 2 vs last 30 days', 'icon' => 'circle-pause', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Reminders Due Soon', 'value' => $stats['due'], 'hint' => '+ 12% vs last 7 days', 'icon' => 'bell', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Messages In Queue', 'value' => $stats['queue'], 'hint' => '+ 18% vs last 7 days', 'icon' => 'list-checks', 'tone' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
        ];
        $ruleIcons = [
            'EventSessionCreated' => ['icon' => 'calendar-plus', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            'EventSessionUpdated' => ['icon' => 'pencil', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            'EventSessionCancelled' => ['icon' => 'x', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            'AttendanceSessionOpened' => ['icon' => 'users-round', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            'AttendanceRecorded' => ['icon' => 'circle-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            'VolunteerAssigned' => ['icon' => 'hand', 'tone' => 'bg-indigo-50 text-indigo-600 ring-indigo-100'],
            'RegistrationConfirmed' => ['icon' => 'user-plus', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            'FollowUpRequired' => ['icon' => 'message-circle-heart', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
        $settingCards = [
            ['label' => 'Retry Policy', 'value' => '3 attempts', 'meta' => 'Backoff: Exponential', 'icon' => 'home'],
            ['label' => 'Delay Before Send', 'value' => '5 minutes', 'meta' => 'After trigger', 'icon' => 'clock'],
            ['label' => 'Quiet Hours', 'value' => '10:00 PM - 7:00 AM', 'meta' => 'Local Time', 'icon' => 'moon'],
            ['label' => 'Timezone Behavior', 'value' => 'Use Recipient Timezone', 'meta' => 'Convert send time', 'icon' => 'settings'],
            ['label' => 'Default Timezone', 'value' => '(UTC+01:00) Lagos', 'meta' => 'Africa/Lagos', 'icon' => 'globe-2'],
        ];
        $statusClass = fn (?string $status): string => match ($status) {
            'sent', 'active' => 'bg-emerald-50 text-emerald-700',
            'scheduled' => 'bg-blue-50 text-blue-700',
            'queued' => 'bg-violet-50 text-violet-700',
            'failed' => 'bg-rose-50 text-rose-700',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal text-slate-950">Scheduled Messages & Automation</h1>
                <p class="mt-1 text-sm text-slate-500">Manage scheduled communications, automations, and delivery queue for reliable engagement.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('communications.delivery-logs', ['status' => 'queued']) }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="copy" class="size-4"></i>
                    View Queue
                </a>
                <a href="{{ route('communications.integrations') }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="play" class="size-4"></i>
                    Run Test
                </a>
                <a href="{{ route('communications.templates', ['new' => 1]) }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="settings" class="size-4"></i>
                    Create Automation
                </a>
                <span class="inline-flex overflow-hidden rounded-lg bg-violet-600 text-sm text-white shadow-sm">
                    <a href="{{ route('communications.bulk') }}#campaign-form" class="inline-flex items-center gap-2 px-4 py-2.5 hover:bg-violet-700">
                        <i data-lucide="calendar-plus" class="size-4"></i>
                        Create Schedule
                    </a>
                    <a href="{{ route('communications.bulk') }}" class="grid w-10 place-items-center border-l border-violet-500 hover:bg-violet-700" aria-label="Open bulk messaging">
                        <i data-lucide="chevron-down" class="size-4"></i>
                    </a>
                </span>
            </div>
        </div>

        @include('communications.partials.flash')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach($cards as $card)
                <article class="dashboard-card p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ number_format($card['value']) }}</div>
                            <div class="mt-1 text-xs {{ str_starts_with($card['hint'], '-') ? 'text-rose-600' : 'text-emerald-600' }}">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="space-y-4">
        <form method="GET" action="{{ route('communications.scheduled') }}" class="dashboard-card p-3">
            <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Channel</span>
                    <select name="channel" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Channels</option>
                        @foreach($channels as $key => $channel)
                            <option value="{{ $key }}" @selected(request('channel') === $key)>{{ $channel['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Event Type</span>
                    <select name="q" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Event Types</option>
                        @foreach(['Sunday Service Reminder', 'Registration Approved Notice', 'Attendance Opens Reminder', 'Follow-up Required Reminder'] as $eventType)
                            <option value="{{ $eventType }}" @selected(request('q') === $eventType)>{{ $eventType }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Trigger Source</span>
                    <select name="trigger_source" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Sources</option>
                        <option value="time_based" @selected(request('trigger_source') === 'time_based')>Time-Based</option>
                        <option value="event_based" @selected(request('trigger_source') === 'event_based')>Event-Based</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Queue Status</span>
                    <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Statuses</option>
                        @foreach($activeStatuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Audience</span>
                    <select name="audience" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Audiences</option>
                        @foreach(['members' => 'Members', 'volunteers' => 'Volunteers', 'guests' => 'Guests'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('audience') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button class="inline-flex h-10 items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 text-sm text-violet-700 hover:bg-violet-50">
                        <i data-lucide="filter" class="size-4"></i>
                        Apply
                    </button>
                    <a href="{{ route('communications.scheduled') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm text-slate-600 hover:bg-slate-50">
                        <i data-lucide="x" class="size-4"></i>
                        Clear Filters
                    </a>
                </div>
            </div>
        </form>

        <section class="grid gap-4 xl:grid-cols-[0.82fr_1.18fr]">
            <article class="dashboard-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-base font-semibold text-slate-950">Schedule Calendar</h2>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('communications.scheduled', $previousMonthQuery) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:text-violet-700"><i data-lucide="chevron-left" class="size-4"></i></a>
                        <a href="{{ route('communications.scheduled', $currentMonthQuery) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-600 hover:text-violet-700">Today</a>
                        <a href="{{ route('communications.scheduled', $nextMonthQuery) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:text-violet-700"><i data-lucide="chevron-right" class="size-4"></i></a>
                    </div>
                </div>
                <div class="p-4">
                    <div class="mb-4 text-center text-sm font-semibold text-slate-800">{{ $calendarMonth->format('F Y') }}</div>
                    <div class="grid grid-cols-7 gap-1 text-center text-sm">
                        @foreach(['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'] as $day)
                            <div class="py-2 text-xs text-slate-500">{{ $day }}</div>
                        @endforeach
                        @foreach(range(0, 41) as $offset)
                            @php($date = $calendarStart->copy()->addDays($offset))
                            @php($hasSchedules = $scheduledDays->has($date->format('Y-m-d')))
                            <div class="relative rounded-lg px-2 py-3 {{ $date->isToday() ? 'bg-violet-600 text-white' : ($date->month === $calendarMonth->month ? 'text-slate-700 hover:bg-violet-50' : 'text-slate-300') }}">
                                {{ $date->day }}
                                @if($hasSchedules)
                                    <span class="absolute bottom-1 left-1/2 size-1.5 -translate-x-1/2 rounded-full {{ $date->isToday() ? 'bg-white' : 'bg-violet-500' }}"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('calendar.index') }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 border-t border-slate-100 pt-4 text-sm text-violet-700">
                        View Full Calendar
                        <i data-lucide="arrow-right" class="size-4"></i>
                    </a>
                </div>
            </article>

            <article class="dashboard-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-base font-semibold text-slate-950">Schedule Timeline <span class="font-normal text-slate-500">- {{ now()->format('M d, Y') }}</span></h2>
                    <div class="rounded-lg bg-slate-50 p-1 text-xs">
                        <a href="#schedule-timeline" class="inline-flex rounded-md bg-violet-600 px-3 py-1.5 text-white">Timeline</a>
                        <a href="#scheduled-table" class="inline-flex px-3 py-1.5 text-slate-500 hover:text-violet-700">List</a>
                    </div>
                </div>
                <div id="schedule-timeline" class="divide-y divide-slate-100">
                    @forelse($timeline as $campaign)
                        @php($firstChannel = ($campaign->channels ?? ['email'])[0] ?? 'email')
                        <div class="grid grid-cols-[70px_auto_1fr_auto_auto] items-center gap-3 px-4 py-3 text-sm">
                            <div class="text-slate-600">{{ $campaign->scheduled_at?->format('g:i A') ?? 'Now' }}</div>
                            <span class="grid size-10 place-items-center rounded-lg {{ $channels[$firstChannel]['tone'] ?? 'bg-violet-50 text-violet-600 ring-violet-100' }}">
                                <i data-lucide="{{ $channels[$firstChannel]['icon'] ?? 'mail' }}" class="size-5"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-slate-900">{{ $campaign->name }}</div>
                                <div class="truncate text-xs text-slate-500">{{ $campaign->segment_name }}</div>
                            </div>
                            <div class="hidden items-center gap-4 text-xs text-slate-500 sm:flex">
                                <span class="inline-flex items-center gap-1"><i data-lucide="{{ $channels[$firstChannel]['icon'] ?? 'mail' }}" class="size-3.5"></i>{{ $channels[$firstChannel]['label'] ?? Str::headline($firstChannel) }}</span>
                                <span class="inline-flex items-center gap-1"><i data-lucide="users-round" class="size-3.5"></i>{{ number_format($campaign->recipient_count) }}</span>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs {{ $statusClass($campaign->status) }}">{{ Str::headline($campaign->status) }}</span>
                        </div>
                    @empty
                        <x-empty-state icon="calendar-clock" title="No timeline items" message="Create scheduled campaigns from Bulk Messaging." />
                    @endforelse
                </div>
                <a href="#scheduled-table" class="flex items-center justify-center gap-2 border-t border-slate-100 px-4 py-3 text-sm text-violet-700">
                    View All Scheduled Messages
                    <i data-lucide="arrow-right" class="size-4"></i>
                </a>
            </article>
        </section>
            </div>

            <aside class="space-y-4">
            <article class="dashboard-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-base font-semibold text-slate-950">Automation Rules <span class="font-normal text-slate-500">({{ count($rules) }} Active)</span></h2>
                    <a href="{{ route('communications.templates') }}" class="inline-flex items-center gap-2 text-sm text-violet-700">View All Rules <i data-lucide="arrow-right" class="size-4"></i></a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Domain Event</th>
                                <th class="px-4 py-3">Listener / Action</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Next Run Preview</th>
                                <th class="px-4 py-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($rules as $rule)
                                @php($ruleStyle = $ruleIcons[$rule['event']] ?? ['icon' => 'settings', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'])
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-3">
                                            <span class="grid size-8 place-items-center rounded-lg ring-1 {{ $ruleStyle['tone'] }}"><i data-lucide="{{ $ruleStyle['icon'] }}" class="size-4"></i></span>
                                            <span class="font-medium text-slate-900">{{ $rule['event'] }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-800">{{ $rule['listener'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $rule['templates'] }} templates configured</div>
                                    </td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs text-emerald-700">Active</span></td>
                                    <td class="px-4 py-3 text-slate-600">{{ $rule['next_run']->format('M d, Y h:i A') }}</td>
                                    <td class="px-4 py-3 text-right"><a href="{{ route('communications.templates', ['trigger' => $rule['event']]) }}" class="inline-grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:text-violet-700"><i data-lucide="ellipsis" class="size-4"></i></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

        <section class="dashboard-card overflow-hidden">
            <div class="grid gap-0 divide-y divide-slate-100 md:grid-cols-5 md:divide-x md:divide-y-0">
                @foreach($settingCards as $setting)
                    <article class="p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-8 place-items-center rounded-full bg-slate-50 text-violet-600 ring-1 ring-slate-100">
                                <i data-lucide="{{ $setting['icon'] }}" class="size-4"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs text-slate-500">{{ $setting['label'] }}</div>
                                <div class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $setting['value'] }}</div>
                                <div class="text-xs text-slate-500">{{ $setting['meta'] }}</div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
            </aside>
        </section>

        <section id="scheduled-table" class="dashboard-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 xl:flex-row xl:items-center xl:justify-between">
                <h2 class="text-base font-semibold text-slate-950">Scheduled Messages</h2>
                <p class="text-sm text-slate-500">Showing {{ $campaigns->firstItem() ?? 0 }} to {{ $campaigns->lastItem() ?? 0 }} of {{ number_format($campaigns->total()) }} entries</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1160px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3"><input type="checkbox" class="rounded border-slate-300 text-violet-600"></th>
                            <th class="px-4 py-3">Message Name</th>
                            <th class="px-4 py-3">Linked Event / Session</th>
                            <th class="px-4 py-3">Trigger Type</th>
                            <th class="px-4 py-3">Send Time</th>
                            <th class="px-4 py-3">Channel</th>
                            <th class="px-4 py-3 text-right">Audience</th>
                            <th class="px-4 py-3">Queue State</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($campaigns as $campaign)
                            @php($firstChannel = ($campaign->channels ?? ['email'])[0] ?? 'email')
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3"><input type="checkbox" class="rounded border-slate-300 text-violet-600"></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-8 place-items-center rounded-full {{ $channels[$firstChannel]['tone'] ?? 'bg-violet-50 text-violet-600 ring-violet-100' }}"><i data-lucide="{{ $channels[$firstChannel]['icon'] ?? 'mail' }}" class="size-4"></i></span>
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-slate-900">{{ $campaign->name }}</div>
                                            <div class="truncate text-xs text-slate-500">{{ $campaign->template?->name ?? 'Custom message' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $campaign->subject ?: $campaign->segment_name }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2 text-slate-600">
                                        <i data-lucide="{{ $campaign->template?->trigger_event ? 'link' : 'clock' }}" class="size-4 text-slate-400"></i>
                                        {{ $campaign->template?->trigger_event ? 'Event-Based ('.$campaign->template->trigger_event.')' : 'Time-Based' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $campaign->scheduled_at?->format('M d, Y h:i A') ?? 'Not scheduled' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2 text-slate-600"><span class="size-2 rounded-full" style="background-color: {{ $channels[$firstChannel]['color'] ?? '#6d4aff' }}"></span>{{ $channels[$firstChannel]['label'] ?? Str::headline($firstChannel) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-900">{{ number_format($campaign->recipient_count) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-3 py-1 text-xs {{ $statusClass($campaign->status) }}">{{ Str::headline($campaign->status) }}</span></td>
                                <td class="px-4 py-3 text-slate-600">{{ $campaign->creator?->name ?? 'System' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center justify-end gap-1">
                                        @if(in_array($campaign->status, ['draft', 'scheduled', 'queued', 'failed'], true))
                                            <form method="POST" action="{{ route('communications.campaigns.send', $campaign) }}">
                                                @csrf
                                                <button class="grid size-8 place-items-center rounded-lg border border-slate-200 text-violet-600 hover:bg-violet-50" title="Run now"><i data-lucide="play" class="size-4"></i></button>
                                            </form>
                                        @endif
                                        <a href="{{ route('communications.bulk', ['campaign' => $campaign->opaqueId()]) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" title="Edit in bulk messaging"><i data-lucide="pencil" class="size-4"></i></a>
                                        <a href="{{ route('communications.delivery-logs') }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" title="View logs"><i data-lucide="copy" class="size-4"></i></a>
                                        <a href="{{ route('communications.delivery-logs') }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" title="More actions"><i data-lucide="ellipsis" class="size-4"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-12 text-center">
                                    <x-empty-state icon="calendar-clock" title="No scheduled messages" message="Use Bulk Messaging to create a scheduled campaign." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-slate-500">Rows per page <span class="ml-2 rounded-lg border border-slate-200 px-3 py-2 text-slate-700">10</span></div>
                {{ $campaigns->links() }}
            </div>
        </section>

        <footer class="flex flex-col gap-2 py-2 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>Copyright 2024 Kingdom Life Global Church. All rights reserved.</span>
            <span class="flex items-center gap-8">
                <span>Version 2.4.0</span>
                <a href="#" class="hover:text-violet-600">Privacy Policy</a>
                <a href="#" class="hover:text-violet-600">Terms of Service</a>
            </span>
        </footer>
    </div>
</x-app-layout>
