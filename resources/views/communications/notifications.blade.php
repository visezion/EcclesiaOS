<x-app-layout title="Notifications Center" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Unread Notifications', 'value' => $stats['unread'], 'hint' => '+ 18% vs yesterday', 'icon' => 'bell', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Action-Required Alerts', 'value' => $stats['action_required'], 'hint' => '+ 12% vs yesterday', 'icon' => 'triangle-alert', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Scheduled Reminders Today', 'value' => $stats['scheduled_today'], 'hint' => '+ 9% vs yesterday', 'icon' => 'calendar-days', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Sent Today', 'value' => $stats['sent_today'], 'hint' => '+ 22% vs yesterday', 'icon' => 'send', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Failed Today', 'value' => $stats['failed_today'], 'hint' => '- 5% vs yesterday', 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Archived Notifications', 'value' => $stats['archived'], 'hint' => '+ 14% vs last 7 days', 'icon' => 'archive', 'tone' => 'bg-slate-50 text-slate-600 ring-slate-100'],
        ];

        $eventMeta = [
            'EventSessionCreated' => ['label' => 'Event Created', 'icon' => 'calendar-days', 'tone' => 'text-violet-600'],
            'EventSessionUpdated' => ['label' => 'Meeting Time Changed', 'icon' => 'clock', 'tone' => 'text-orange-600'],
            'EventSessionCancelled' => ['label' => 'Session Cancelled', 'icon' => 'x', 'tone' => 'text-rose-600'],
            'RegistrationConfirmed' => ['label' => 'Registration Approved', 'icon' => 'badge-check', 'tone' => 'text-emerald-600'],
            'AttendanceSessionOpened' => ['label' => 'Attendance Opens', 'icon' => 'calendar-check', 'tone' => 'text-blue-600'],
            'AttendanceRecorded' => ['label' => 'QR Check-in Available', 'icon' => 'scan-qr-code', 'tone' => 'text-emerald-600'],
            'VolunteerAssigned' => ['label' => 'Volunteer Assigned', 'icon' => 'user-check', 'tone' => 'text-emerald-600'],
            'FollowUpRequired' => ['label' => 'Follow-up Required', 'icon' => 'heart-handshake', 'tone' => 'text-orange-600'],
            'BulkCampaign' => ['label' => 'Bulk Campaign', 'icon' => 'send', 'tone' => 'text-blue-600'],
            'ProviderTest' => ['label' => 'Provider Test', 'icon' => 'radio-tower', 'tone' => 'text-violet-600'],
        ];
        $grouped = $notifications->getCollection()->groupBy(function ($notification) {
            if ($notification->created_at?->isToday()) {
                return 'Today - '.$notification->created_at->format('M d, Y');
            }

            if ($notification->created_at?->isYesterday()) {
                return 'Yesterday - '.$notification->created_at->format('M d, Y');
            }

            return $notification->created_at?->format('M d, Y') ?? 'Undated';
        });
        $statusTone = fn (string $status): string => match ($status) {
            'delivered' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-rose-50 text-rose-700',
            default => 'bg-orange-50 text-orange-700',
        };
        $priorityFor = fn ($delivery): array => match ($delivery?->status) {
            'failed' => ['label' => 'High', 'tone' => 'bg-rose-50 text-rose-700'],
            'queued' => ['label' => 'Medium', 'tone' => 'bg-orange-50 text-orange-700'],
            default => ['label' => 'Low', 'tone' => 'bg-emerald-50 text-emerald-700'],
        };
        $selectedMeta = $selected ? ($eventMeta[$selected->event_type] ?? ['label' => Str::headline($selected->event_type ?? 'Notification'), 'icon' => 'bell', 'tone' => 'text-violet-600']) : null;
        $selectedPriority = $priorityFor($selected);
        $priorityTotal = max(collect($priorityBreakdown)->sum('value'), 1);
        $statusTotal = max(collect($statusBreakdown)->sum('value'), 1);
    @endphp

    <div class="space-y-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-950">Notifications Center</h1>
            <p class="text-sm text-slate-500">View, manage, and track all in-app, email, SMS, WhatsApp, and push notifications across your church operations.</p>
        </div>

        @include('communications.partials.flash')

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach($cards as $card)
                <article class="dashboard-card p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold leading-none text-slate-950">{{ number_format($card['value']) }}</div>
                            <div class="mt-1 text-xs {{ str_starts_with($card['hint'], '-') ? 'text-rose-600' : 'text-emerald-600' }}">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_330px]">
            <main class="space-y-4">
                <form method="GET" class="dashboard-card p-0">
                    <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-6">
                        <label class="text-xs font-medium text-slate-600">Channel
                            <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Channels</option>
                                @foreach($channels as $key => $channel)
                                    <option value="{{ $key }}" @selected(request('channel') === $key)>{{ $channel['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Event Type
                            <select name="event_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Event Types</option>
                                @foreach($eventTypes as $eventType)
                                    <option value="{{ $eventType }}" @selected(request('event_type') === $eventType)>{{ $eventMeta[$eventType]['label'] ?? Str::headline($eventType) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Priority
                            <select name="priority" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Priorities</option>
                                <option value="high" @selected(request('priority') === 'high')>High</option>
                                <option value="medium" @selected(request('priority') === 'medium')>Medium</option>
                                <option value="low" @selected(request('priority') === 'low')>Low</option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Date Range
                            <select name="date_range" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">May 19 - May 25, 2025</option>
                                <option value="today" @selected(request('date_range') === 'today')>Today</option>
                                <option value="7_days" @selected(request('date_range') === '7_days')>Last 7 Days</option>
                                <option value="30_days" @selected(request('date_range') === '30_days')>Last 30 Days</option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Recipient Type
                            <select name="recipient_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Recipients</option>
                                <option value="member" @selected(request('recipient_type') === 'member')>Members</option>
                                <option value="system" @selected(request('recipient_type') === 'system')>System</option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All Statuses</option>
                                @foreach(['queued','delivered','failed'] as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="flex flex-col gap-3 border-t border-slate-100 p-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="relative flex-1">
                            <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 py-2.5 pl-9 pr-3 text-sm" placeholder="Search notifications, recipients, templates, or messages...">
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700">
                                <i data-lucide="sliders-horizontal" class="size-4"></i>
                                More Filters
                            </button>
                            <a href="{{ route('communications.notifications') }}" class="px-3 py-2 text-sm text-violet-700">Reset</a>
                            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm text-white">
                                <i data-lucide="filter" class="size-4"></i>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>

                <section class="dashboard-card p-0">
                    <div class="grid gap-3 border-b border-slate-100 px-4 py-3 lg:grid-cols-[1fr_auto_auto_auto] lg:items-center">
                        <div class="text-sm text-slate-600">{{ $notifications->firstItem() ?? 0 }}-{{ $notifications->lastItem() ?? 0 }} of {{ number_format($notifications->total()) }} notifications</div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">Group by:
                            <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <option>Date</option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">Sort by:
                            <select name="sort" form="sort-form" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <option value="newest" @selected(request('sort') !== 'oldest')>Newest First</option>
                                <option value="oldest" @selected(request('sort') === 'oldest')>Oldest First</option>
                            </select>
                        </label>
                        <form id="sort-form" method="GET">
                            @foreach(request()->except('sort', 'page') as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                        </form>
                        <button type="button" class="inline-grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600"><i data-lucide="list-filter" class="size-4"></i></button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1020px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="w-12 px-4 py-3"></th>
                                    <th class="px-4 py-3">Notification</th>
                                    <th class="px-4 py-3">Message</th>
                                    <th class="px-4 py-3">Recipients</th>
                                    <th class="px-4 py-3">Channels</th>
                                    <th class="px-4 py-3">Created</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Read</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($grouped as $dateLabel => $rows)
                                    <tr class="bg-white">
                                        <td colspan="9" class="px-4 py-2 text-xs font-medium text-slate-700">
                                            <span class="inline-flex items-center gap-2"><i data-lucide="chevron-down" class="size-4"></i>{{ $dateLabel }} ({{ $rows->count() }})</span>
                                        </td>
                                    </tr>
                                    @foreach($rows as $notification)
                                        @php
                                            $meta = $eventMeta[$notification->event_type] ?? ['label' => Str::headline($notification->event_type ?? 'Notification'), 'icon' => 'bell', 'tone' => 'text-violet-600'];
                                            $rowPriority = $priorityFor($notification);
                                            $selectedRow = $selected?->is($notification);
                                        @endphp
                                        <tr class="{{ $selectedRow ? 'bg-violet-50/70' : 'hover:bg-slate-50/70' }}">
                                            <td class="px-4 py-3"><input type="checkbox" @checked($selectedRow) class="rounded border-slate-300 text-violet-600"></td>
                                            <td class="px-4 py-3">
                                                <a href="{{ request()->fullUrlWithQuery(['notification' => $notification->opaqueId()]) }}" class="flex items-center gap-3">
                                                    <i data-lucide="{{ $meta['icon'] }}" class="size-4 {{ $meta['tone'] }}"></i>
                                                    <span class="font-medium text-slate-950">{{ $meta['label'] }}</span>
                                                </a>
                                            </td>
                                            <td class="max-w-[280px] px-4 py-3">
                                                <div class="truncate text-slate-700">{{ $notification->subject ?? $notification->body_excerpt ?? $meta['label'] }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-700">{{ $notification->campaign?->segment_name ?? $notification->recipient_name }}</td>
                                            <td class="px-4 py-3">@include('communications.partials.channel-chips', ['selected' => [$notification->channel], 'channels' => $channels])</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $notification->created_at?->format('h:i A') }}</td>
                                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs {{ $statusTone($notification->status) }}">{{ Str::headline($notification->status) }}</span></td>
                                            <td class="px-4 py-3"><span class="inline-block size-2.5 rounded-full {{ $notification->read_at ? 'border border-violet-600' : 'bg-violet-600' }}"></span></td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="inline-flex items-center gap-1">
                                                    @if($notification->status === 'failed')
                                                        <form method="POST" action="{{ route('communications.delivery-logs.retry', $notification) }}">@csrf<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50" title="Retry"><i data-lucide="refresh-cw" class="size-4"></i></button></form>
                                                    @endif
                                                    <form method="POST" action="{{ route('communications.notifications.read', $notification) }}">@csrf<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50" title="Mark as read"><i data-lucide="check" class="size-4"></i></button></form>
                                                    <a href="{{ request()->fullUrlWithQuery(['notification' => $notification->opaqueId()]) }}" class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50" title="View details"><i data-lucide="ellipsis" class="size-4"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr><td colspan="9" class="px-5 py-12 text-center"><x-empty-state icon="bell" title="No notifications found" message="Delivery events will appear here after a campaign, template test, or provider test runs." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $notifications->links() }}</div>
                </section>

                <section class="grid gap-4 xl:grid-cols-4">
                    <article class="dashboard-card p-0 xl:col-span-1">
                        <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Activity Timeline <span class="font-normal text-slate-500">(Last 7 Days)</span></h2></div>
                        <div class="grid gap-4 p-4 lg:grid-cols-[110px_1fr] lg:items-center">
                            <div class="space-y-3 text-xs">
                                @foreach($timeline['datasets'] as $dataset)
                                    <div class="flex items-center justify-between gap-3"><span class="inline-flex items-center gap-2 text-slate-600"><span class="size-2 rounded-full" style="background: {{ $dataset['color'] }}"></span>{{ $dataset['label'] }}</span><span class="font-medium text-slate-900">{{ number_format(array_sum($dataset['values'])) }}</span></div>
                                @endforeach
                            </div>
                            <div class="h-40"><canvas data-chart="multi-line" data-labels='@json($timeline["labels"])' data-datasets='@json($timeline["datasets"])'></canvas></div>
                        </div>
                    </article>

                    @foreach([['Priority Breakdown', $priorityBreakdown, $priorityTotal], ['Notification Status', $statusBreakdown, $statusTotal]] as [$title, $rows, $total])
                        <article class="dashboard-card p-0">
                            <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">{{ $title }}</h2></div>
                            <div class="grid gap-4 p-4 md:grid-cols-[130px_1fr] md:items-center">
                                <div class="relative h-36">
                                    <canvas data-chart="doughnut" data-labels='@json(collect($rows)->pluck("label"))' data-values='@json(collect($rows)->pluck("value"))' data-colors='@json(collect($rows)->pluck("color"))'></canvas>
                                    <div class="pointer-events-none absolute inset-0 grid place-items-center text-center"><span><span class="block text-xl font-semibold text-slate-950">{{ number_format($total) }}</span><span class="text-xs text-slate-500">Total</span></span></div>
                                </div>
                                <div class="space-y-3 text-sm">
                                    @foreach($rows as $row)
                                        @php($percent = round(($row['value'] / $total) * 100, 1))
                                        <div class="grid grid-cols-[1fr_auto] gap-3">
                                            <span class="inline-flex items-center gap-2 text-slate-600"><span class="size-2.5 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span>
                                            <span class="text-slate-900">{{ number_format($row['value']) }} <span class="text-slate-400">({{ $percent }}%)</span></span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </article>
                    @endforeach

                    <article class="dashboard-card p-0">
                        <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Quick Actions</h2></div>
                        <div class="grid grid-cols-3 gap-3 p-4">
                            <a href="{{ route('communications.bulk') }}" class="rounded-lg border border-slate-200 p-3 text-center text-xs hover:bg-violet-50"><i data-lucide="pencil" class="mx-auto mb-2 size-5 text-violet-600"></i>Compose Message</a>
                            <form method="POST" action="{{ route('communications.notifications.read-all') }}">@csrf<button class="w-full rounded-lg border border-slate-200 p-3 text-center text-xs hover:bg-violet-50"><i data-lucide="check-check" class="mx-auto mb-2 size-5 text-blue-600"></i>Mark All Read</button></form>
                            <a href="{{ route('communications.delivery-logs', ['status' => 'failed']) }}" class="rounded-lg border border-slate-200 p-3 text-center text-xs hover:bg-violet-50"><i data-lucide="refresh-cw" class="mx-auto mb-2 size-5 text-emerald-600"></i>Resend Failed</a>
                            <form method="POST" action="{{ route('communications.notifications.archive-old') }}">@csrf<button class="w-full rounded-lg border border-slate-200 p-3 text-center text-xs hover:bg-violet-50"><i data-lucide="archive" class="mx-auto mb-2 size-5 text-slate-600"></i>Archive Old</button></form>
                            <a href="{{ route('communications.scheduled') }}" class="rounded-lg border border-slate-200 p-3 text-center text-xs hover:bg-violet-50"><i data-lucide="calendar-plus" class="mx-auto mb-2 size-5 text-violet-600"></i>Schedule Message</a>
                            <a href="{{ route('communications.delivery-logs.export') }}" class="rounded-lg border border-slate-200 p-3 text-center text-xs hover:bg-violet-50"><i data-lucide="download" class="mx-auto mb-2 size-5 text-blue-600"></i>Export Report</a>
                        </div>
                    </article>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card p-0">
                    @if($selected)
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid size-11 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $selectedMeta['icon'] }}" class="size-5"></i></span>
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">{{ $selectedMeta['label'] }}</h2>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs {{ $statusTone($selected->status) }}">{{ Str::headline($selected->status) }}</span>
                                        <span class="text-xs text-slate-500">ID: NTF-{{ Str::upper(Str::substr($selected->opaqueId(), 0, 10)) }}</span>
                                        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs text-violet-700">{{ $selected->read_at ? 'Read' : 'Unread' }}</span>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('communications.notifications') }}" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="size-5"></i></a>
                        </div>

                        <div class="grid grid-cols-3 border-b border-slate-100 text-center text-xs font-medium text-slate-600">
                            <span class="border-b-2 border-violet-600 px-3 py-3 text-violet-700">Details</span>
                            <span class="px-3 py-3">Recipients ({{ number_format($selected->campaign?->recipient_count ?? 1) }})</span>
                            <span class="px-3 py-3">Related Record</span>
                        </div>

                        <div class="space-y-4 p-4">
                            <div class="rounded-lg border border-slate-100 bg-white p-4 text-sm text-slate-700">
                                <p>{{ $selected->body_excerpt ?: 'Notification content was recorded without a message excerpt.' }}</p>
                                <dl class="mt-4 space-y-2">
                                    <div class="flex gap-2"><dt class="w-20 shrink-0 text-slate-500">Date</dt><dd>{{ $selected->created_at?->format('M d, Y') }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-20 shrink-0 text-slate-500">Time</dt><dd>{{ $selected->created_at?->format('h:i A') }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-20 shrink-0 text-slate-500">Recipient</dt><dd>{{ $selected->recipient_name }}</dd></div>
                                </dl>
                            </div>

                            <dl class="grid gap-3 text-sm">
                                <div class="grid grid-cols-[130px_1fr] items-center gap-3"><dt class="inline-flex items-center gap-2 text-slate-500"><i data-lucide="radio" class="size-4"></i>Channels Used</dt><dd>@include('communications.partials.channel-chips', ['selected' => [$selected->channel], 'channels' => $channels])</dd></div>
                                <div class="grid grid-cols-[130px_1fr] items-center gap-3"><dt class="inline-flex items-center gap-2 text-slate-500"><i data-lucide="users" class="size-4"></i>Recipients</dt><dd>{{ $selected->campaign?->segment_name ?? $selected->recipient_name }}</dd></div>
                                <div class="grid grid-cols-[130px_1fr] items-center gap-3"><dt class="inline-flex items-center gap-2 text-slate-500"><i data-lucide="file-text" class="size-4"></i>Template Used</dt><dd>{{ $selected->template?->name ?? 'Direct Message' }}</dd></div>
                                <div class="grid grid-cols-[130px_1fr] items-center gap-3"><dt class="inline-flex items-center gap-2 text-slate-500"><i data-lucide="triangle-alert" class="size-4"></i>Priority</dt><dd><span class="rounded-full px-2.5 py-1 text-xs {{ $selectedPriority['tone'] }}">{{ $selectedPriority['label'] }}</span></dd></div>
                                <div class="grid grid-cols-[130px_1fr] items-center gap-3"><dt class="inline-flex items-center gap-2 text-slate-500"><i data-lucide="user" class="size-4"></i>Created By</dt><dd>{{ $selected->campaign?->creator?->name ?? 'System' }}</dd></div>
                                <div class="grid grid-cols-[130px_1fr] items-center gap-3"><dt class="inline-flex items-center gap-2 text-slate-500"><i data-lucide="rotate-cw" class="size-4"></i>Retry Attempts</dt><dd>{{ $selected->attempt - 1 }}</dd></div>
                            </dl>
                        </div>

                        <div class="border-t border-slate-100 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-slate-950">Communication History</h3>
                            <div class="space-y-3">
                                <div class="rounded-lg border border-slate-100 p-3 text-sm"><div class="font-medium text-emerald-700">Delivered</div><div class="text-xs text-slate-500">{{ $selected->delivered_at?->format('h:i A') ?? $selected->created_at?->format('h:i A') }}</div></div>
                                @if($selected->opened_at)<div class="rounded-lg border border-slate-100 p-3 text-sm"><div class="font-medium text-emerald-700">Opened</div><div class="text-xs text-slate-500">{{ $selected->opened_at->format('h:i A') }}</div></div>@endif
                                @if($selected->read_at)<div class="rounded-lg border border-slate-100 p-3 text-sm"><div class="font-medium text-emerald-700">In-App Read</div><div class="text-xs text-slate-500">{{ $selected->read_at->format('h:i A') }}</div></div>@endif
                            </div>
                        </div>

                        <div class="space-y-3 border-t border-slate-100 p-4">
                            <a href="{{ route('communications.delivery-logs') }}" class="flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm text-white"><i data-lucide="external-link" class="size-4"></i>Open Related Record</a>
                            <div class="grid grid-cols-3 gap-2">
                                <form method="POST" action="{{ route('communications.delivery-logs.retry', $selected) }}">@csrf<button class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><i data-lucide="send" class="mx-auto mb-1 size-4"></i>Resend</button></form>
                                <form method="POST" action="{{ route('communications.notifications.archive', $selected) }}">@csrf<button class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><i data-lucide="archive" class="mx-auto mb-1 size-4"></i>Archive</button></form>
                                <form method="POST" action="{{ route('communications.notifications.read', $selected) }}">@csrf<button class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><i data-lucide="check" class="mx-auto mb-1 size-4"></i>Mark Read</button></form>
                            </div>
                        </div>
                    @else
                        <div class="p-8"><x-empty-state icon="bell" title="No notification selected" message="Select a notification to view details, recipients, history, and actions." /></div>
                    @endif
                </section>

                <section class="dashboard-card p-0">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-950">Scheduled Messages <span class="font-normal text-slate-500">(Next 7 Days)</span></h2>
                        <a href="{{ route('communications.scheduled') }}" class="text-xs font-medium text-violet-700">View Calendar</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($scheduled as $campaign)
                            <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-3 text-sm">
                                <i data-lucide="calendar-days" class="size-4 text-violet-600"></i>
                                <div class="min-w-0"><div class="truncate font-medium text-slate-900">{{ $campaign->name }}</div><div class="text-xs text-slate-500">{{ $campaign->scheduled_at?->format('M d, h:i A') }}</div></div>
                                <span class="text-xs text-slate-500">{{ $channels[($campaign->channels ?? ['email'])[0]]['label'] ?? 'Email' }}</span>
                            </div>
                        @empty
                            <p class="px-4 py-8 text-sm text-slate-500">No scheduled messages in the next 7 days.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('communications.scheduled') }}" class="flex items-center justify-center gap-1 border-t border-slate-100 px-4 py-3 text-xs font-medium text-violet-700">View All Scheduled <i data-lucide="arrow-right" class="size-3.5"></i></a>
                </section>
            </aside>
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
