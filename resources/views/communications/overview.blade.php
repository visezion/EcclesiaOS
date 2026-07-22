<x-app-layout title="Communications" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Total Notifications Sent', 'value' => number_format($stats['sent']), 'hint' => '+ stored delivery records', 'icon' => 'send', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Delivery Rate', 'value' => $stats['delivery_rate'].'%', 'hint' => 'delivered / total', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Scheduled Messages', 'value' => number_format($stats['scheduled']), 'hint' => 'future campaigns', 'icon' => 'calendar-clock', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Pending Retries', 'value' => number_format($stats['queued']), 'hint' => 'waiting in queue', 'icon' => 'refresh-cw', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Failed Deliveries', 'value' => number_format($stats['failed']), 'hint' => 'requires review', 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Active Templates', 'value' => number_format($stats['templates']), 'hint' => '+ this week', 'icon' => 'file-search', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Bulk Campaigns This Month', 'value' => number_format($stats['campaigns']), 'hint' => 'last 30 days', 'icon' => 'messages-square', 'tone' => 'bg-teal-50 text-teal-600 ring-teal-100'],
            ['label' => 'Channel Integrations', 'value' => $stats['integrations'].' / '.count($channels), 'hint' => 'Active', 'icon' => 'link', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
        ];

        $triggerMeta = [
            'EventSessionCreated' => ['label' => 'Event created', 'copy' => 'Triggers when a new event is created', 'icon' => 'calendar-plus', 'tone' => 'bg-violet-50 text-violet-600'],
            'EventSessionUpdated' => ['label' => 'Meeting time changed', 'copy' => 'Triggers when event time or date changes', 'icon' => 'clock', 'tone' => 'bg-blue-50 text-blue-600'],
            'EventSessionCancelled' => ['label' => 'Session cancelled', 'copy' => 'Triggers when a session is cancelled', 'icon' => 'x', 'tone' => 'bg-rose-50 text-rose-600'],
            'RegistrationConfirmed' => ['label' => 'Registration approved', 'copy' => 'Triggers when registration is approved', 'icon' => 'badge-check', 'tone' => 'bg-teal-50 text-teal-600'],
            'AttendanceSessionOpened' => ['label' => 'Attendance opens', 'copy' => 'Triggers when attendance window opens', 'icon' => 'clipboard-check', 'tone' => 'bg-emerald-50 text-emerald-600'],
            'AttendanceRecorded' => ['label' => 'QR check-in available', 'copy' => 'Triggers when QR check-in is enabled', 'icon' => 'scan-qr-code', 'tone' => 'bg-emerald-50 text-emerald-600'],
            'VolunteerAssigned' => ['label' => 'Volunteer assigned', 'copy' => 'Triggers when a volunteer is assigned', 'icon' => 'user-plus', 'tone' => 'bg-blue-50 text-blue-600'],
            'FollowUpRequired' => ['label' => 'Follow-up required', 'copy' => 'Triggers for follow-up or pastoral care', 'icon' => 'heart-handshake', 'tone' => 'bg-orange-50 text-orange-600'],
        ];

        $quickActions = [
            ['label' => 'Compose Message', 'hint' => 'Create a new message', 'route' => 'communications.notifications', 'icon' => 'pencil'],
            ['label' => 'Create Template', 'hint' => 'Design reusable template', 'route' => 'communications.templates', 'icon' => 'file-search'],
            ['label' => 'Schedule Notice', 'hint' => 'Plan for later delivery', 'route' => 'communications.scheduled', 'icon' => 'calendar-clock'],
            ['label' => 'Send Bulk Campaign', 'hint' => 'Target large audience', 'route' => 'communications.bulk', 'icon' => 'send'],
            ['label' => 'Test Channel', 'hint' => 'Send test message', 'route' => 'communications.integrations', 'icon' => 'radio-tower'],
        ];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Communications & Notifications</h1>
                <p class="text-sm text-slate-500">Events, attendance, registrations, and volunteer operations use reliable queued communications.</p>
            </div>

            <a href="{{ route('communications.integrations') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                <i data-lucide="settings" class="size-4"></i>
                Communication Settings
                <i data-lucide="chevron-right" class="size-4"></i>
            </a>
        </div>
        @include('communications.partials.flash')
        <!-- @include('communications.partials.subnav') -->
        <section class="grid items-stretch gap-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
            @foreach($cards as $card)
                <article class="dashboard-card p-3">
                    <div class="flex h-full items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-4"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-[11px] leading-tight text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-xl font-semibold leading-none text-slate-950">{{ $card['value'] }}</div>
                            <div class="mt-1 text-[11px] leading-tight text-emerald-600">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_300px]">
            <main class="grid auto-rows-min gap-4 xl:grid-cols-12">
                    <article class="dashboard-card p-0 xl:col-span-3">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950">
                                <i data-lucide="bell-ring" class="size-4 text-violet-600"></i>
                                Notification Triggers
                            </h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($triggers as $trigger)
                                @php($meta = $triggerMeta[$trigger['name']] ?? ['label' => $trigger['name'], 'copy' => 'Communication trigger', 'icon' => 'radio', 'tone' => 'bg-slate-50 text-slate-600'])
                                <div class="flex items-center gap-3 px-4 py-2.5 text-sm">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $meta['tone'] }}">
                                        <i data-lucide="{{ $meta['icon'] }}" class="size-4"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-slate-900">{{ $meta['label'] }}</div>
                                        <div class="truncate text-xs text-slate-500">{{ $meta['copy'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('communications.templates') }}" class="flex items-center justify-center gap-1 border-t border-slate-100 px-4 py-3 text-xs font-medium text-violet-700">
                            Manage Triggers
                            <i data-lucide="arrow-right" class="size-3.5"></i>
                        </a>
                    </article>

                    <article class="dashboard-card p-0 xl:col-span-3">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950">
                                <i data-lucide="database" class="size-4 text-violet-600"></i>
                                Queued Domain Events
                            </h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($triggers as $trigger)
                                <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                    <span class="truncate font-medium text-slate-700">{{ $trigger['name'] }}</span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ number_format($trigger['queued']) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('communications.delivery-logs') }}" class="flex items-center justify-center gap-1 border-t border-slate-100 px-4 py-3 text-xs font-medium text-violet-700">
                            View All Events
                            <i data-lucide="arrow-right" class="size-3.5"></i>
                        </a>
                    </article>

                    <article class="dashboard-card p-0 xl:col-span-3">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950">
                                <i data-lucide="radio-tower" class="size-4 text-violet-600"></i>
                                Queued Listeners
                            </h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($queuedListeners as $listener)
                                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3 px-4 py-3 text-sm">
                                    <span class="truncate font-medium text-slate-700">{{ $listener['listener'] }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-xs {{ $listener['status'] === 'Healthy' ? 'bg-emerald-50 text-emerald-700' : 'bg-orange-50 text-orange-700' }}">{{ $listener['status'] }}</span>
                                    <span class="text-xs font-semibold text-slate-700">{{ number_format($listener['throughput'], 1) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('communications.scheduled') }}" class="flex items-center justify-center gap-1 border-t border-slate-100 px-4 py-3 text-xs font-medium text-violet-700">
                            View All Listeners
                            <i data-lucide="arrow-right" class="size-3.5"></i>
                        </a>
                    </article>

                    <article class="dashboard-card p-0 xl:col-span-3">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950">
                                <i data-lucide="chart-no-axes-combined" class="size-4 text-violet-600"></i>
                                Channel Health
                            </h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($providerHealth as $provider)
                                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3 px-4 py-3 text-sm">
                                    <span class="truncate font-medium text-slate-700">{{ $channels[$provider['channel']]['label'] }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-xs {{ $provider['enabled'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $provider['enabled'] ? 'Connected' : 'Disabled' }}</span>
                                    <span class="text-xs font-semibold text-slate-700">{{ $provider['rate'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('communications.integrations') }}" class="flex items-center justify-center gap-1 border-t border-slate-100 px-4 py-3 text-xs font-medium text-violet-700">
                            View Channel Details
                            <i data-lucide="arrow-right" class="size-3.5"></i>
                        </a>
                    </article>
                    <article class="dashboard-card p-0 xl:col-span-4">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Recent Communication Activity</h2>
                            <a href="{{ route('communications.delivery-logs') }}" class="text-xs font-medium text-violet-700">View All Activity</a>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse($recentDeliveries->take(5) as $delivery)
                                <div class="flex items-center gap-3 px-4 py-3 text-sm">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $channels[$delivery->channel]['tone'] ?? 'bg-slate-50 text-slate-600 ring-slate-100' }}">
                                        <i data-lucide="{{ $channels[$delivery->channel]['icon'] ?? 'message-square' }}" class="size-4"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium text-slate-900">{{ $delivery->subject ?? Str::headline($delivery->event_type ?? 'Message') }}</div>
                                        <div class="truncate text-xs text-slate-500">To: {{ $delivery->recipient_name }}</div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="text-[11px] text-slate-500">{{ $delivery->created_at?->format('M d, h:i A') }}</div>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] {{ $delivery->status === 'delivered' ? 'bg-emerald-50 text-emerald-700' : ($delivery->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-orange-50 text-orange-700') }}">{{ Str::headline($delivery->status) }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="p-8"><x-empty-state icon="inbox" title="No communication activity" message="Create a template or campaign to start sending tracked messages." /></div>
                            @endforelse
                        </div>
                        <a href="{{ route('communications.delivery-logs') }}" class="flex items-center justify-center gap-1 border-t border-slate-100 px-4 py-3 text-xs font-medium text-violet-700">
                            View Full Activity Feed
                            <i data-lucide="arrow-right" class="size-3.5"></i>
                        </a>
                    </article>

                    <article class="dashboard-card p-0 xl:col-span-4">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Delivery Trend <span class="font-normal text-slate-500">(Last 30 Days)</span></h2>
                            <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                @foreach($trendSeries['datasets'] as $dataset)
                                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full" style="background: {{ $dataset['color'] }}"></span>{{ $dataset['label'] }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="h-48 px-4 py-4">
                            <canvas data-chart="multi-line" data-labels='@json($trendSeries["labels"])' data-datasets='@json($trendSeries["datasets"])'></canvas>
                        </div>
                        <div class="grid grid-cols-4 gap-2 border-t border-slate-100 p-4 text-center text-xs">
                            <div class="rounded-lg border border-slate-100 p-2"><div class="text-sm font-semibold text-violet-700">{{ number_format($stats['sent']) }}</div><div class="text-slate-500">Sent</div></div>
                            <div class="rounded-lg border border-slate-100 p-2"><div class="text-sm font-semibold text-emerald-700">{{ number_format($stats['sent'] - $stats['failed'] - $stats['queued']) }}</div><div class="text-slate-500">Delivered</div></div>
                            <div class="rounded-lg border border-slate-100 p-2"><div class="text-sm font-semibold text-rose-700">{{ number_format($stats['failed']) }}</div><div class="text-slate-500">Failed</div></div>
                            <div class="rounded-lg border border-slate-100 p-2"><div class="text-sm font-semibold text-slate-900">{{ $stats['delivery_rate'] }}%</div><div class="text-slate-500">Rate</div></div>
                        </div>
                    </article>

                    <article class="dashboard-card p-0 xl:col-span-4">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Channel Mix <span class="font-normal text-slate-500">(Last 30 Days)</span></h2>
                        </div>
                        <div class="grid gap-4 p-4 md:grid-cols-[170px_1fr] md:items-center">
                            <div class="relative h-48">
                                <canvas data-chart="doughnut" data-labels='@json(collect($channelMix)->pluck("label"))' data-values='@json(collect($channelMix)->pluck("value"))' data-colors='@json(collect($channelMix)->pluck("color"))'></canvas>
                                <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                                    <span><span class="block text-2xl font-semibold text-slate-950">{{ number_format($stats['sent']) }}</span><span class="text-xs text-slate-500">Total</span></span>
                                </div>
                            </div>
                            <div class="space-y-3 text-sm">
                                @foreach($channelMix as $row)
                                    @php($percent = $stats['sent'] > 0 ? round(($row['value'] / $stats['sent']) * 100, 1) : 0)
                                    <div class="grid grid-cols-[1fr_auto] items-center gap-3">
                                        <span class="inline-flex items-center gap-2 text-slate-600"><span class="size-2.5 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span>
                                        <span class="text-right text-slate-900">{{ $percent }}% <span class="text-slate-400">({{ number_format($row['value']) }})</span></span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <a href="{{ route('communications.delivery-logs') }}" class="flex items-center justify-center gap-1 border-t border-slate-100 px-4 py-3 text-xs font-medium text-violet-700">
                            View Detailed Breakdown
                            <i data-lucide="arrow-right" class="size-3.5"></i>
                        </a>
                    </article>

                    <article class="dashboard-card p-0 xl:col-span-4">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Quick Actions</h2>
                        </div>
                        <div class="grid divide-y divide-slate-100 sm:grid-cols-5 sm:divide-x sm:divide-y-0">
                            @foreach($quickActions as $action)
                                <a href="{{ route($action['route']) }}" class="group p-3 text-center text-sm hover:bg-violet-50">
                                    <span class="mx-auto grid size-10 place-items-center rounded-full bg-violet-50 text-violet-600 group-hover:bg-white">
                                        <i data-lucide="{{ $action['icon'] }}" class="size-5"></i>
                                    </span>
                                    <span class="mt-2 block font-medium leading-tight text-slate-900">{{ $action['label'] }}</span>
                                    <span class="mt-1 block text-xs leading-tight text-slate-500">{{ $action['hint'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </article>

                    <article class="dashboard-card p-0 xl:col-span-8">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Communication History Summary <span class="font-normal text-slate-500">(Last 30 Days)</span></h2>
                        </div>
                        <div class="grid grid-cols-2 gap-2 p-3 xl:grid-cols-5">
                            @foreach($historySummary as $item)
                                <div class="rounded-lg border border-slate-100 p-3 text-center">
                                    <span class="mx-auto grid size-8 place-items-center rounded-full {{ $item['tone'] }}">
                                        <i data-lucide="{{ $item['icon'] }}" class="size-4"></i>
                                    </span>
                                    <div class="mt-2 text-base font-semibold leading-none text-slate-950">{{ number_format($item['value']) }}</div>
                                    <div class="text-xs text-slate-500">{{ $item['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </article>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950">
                            <i data-lucide="sparkles" class="size-4 text-violet-600"></i>
                            Operational Insights
                        </h2>
                    </div>
                    <div class="space-y-5 p-4">
                        <div>
                            <div class="text-xs font-medium text-slate-500">Retry Queue</div>
                            <div class="mt-1 text-3xl font-semibold text-slate-950">{{ number_format($operationalInsights['retry_queue']) }}</div>
                            <div class="mt-1 text-xs text-slate-500">Oldest in queue: {{ $operationalInsights['oldest_queue'] }}</div>
                            <div class="mt-3 h-12"><canvas data-chart="sparkline" data-values='@json($operationalInsights["retry_trend"])' data-color="#f97316"></canvas></div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <div class="text-xs font-medium text-slate-500">Average Send Time <span class="text-slate-400">(All Channels)</span></div>
                            <div class="mt-1 text-3xl font-semibold text-slate-950">{{ number_format($operationalInsights['avg_send_time'], 2) }}s</div>
                            <div class="mt-1 text-xs text-emerald-600">Down from recent delivery attempts</div>
                            <div class="mt-3 h-12"><canvas data-chart="sparkline" data-values='@json($operationalInsights["latency_trend"])' data-color="#2477f2"></canvas></div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <div class="mb-3 text-xs font-medium text-slate-500">Provider Status</div>
                            <div class="space-y-2">
                                @foreach($operationalInsights['providers'] as $provider)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="inline-flex min-w-0 items-center gap-2 text-slate-700">
                                            <span class="grid size-7 shrink-0 place-items-center rounded-lg {{ $provider['tone'] ?? 'bg-slate-50 text-slate-600' }}">
                                                <i data-lucide="{{ $provider['icon'] ?? 'radio' }}" class="size-3.5"></i>
                                            </span>
                                            <span class="truncate">{{ $provider['label'] }}</span>
                                        </span>
                                        <span class="inline-flex items-center gap-1 text-xs text-emerald-600"><span class="size-2 rounded-full bg-emerald-500"></span>{{ $provider['status'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <div class="mb-3 text-xs font-medium text-slate-500">Automation Readiness</div>
                            <div class="flex items-center gap-4">
                                <div class="grid size-24 place-items-center rounded-full" style="background: conic-gradient(#10b981 {{ $operationalInsights['automation_readiness'] }}%, #e2e8f0 0);">
                                    <div class="grid size-16 place-items-center rounded-full bg-white text-center">
                                        <span class="text-lg font-semibold text-slate-950">{{ $operationalInsights['automation_readiness'] }}%</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="font-semibold text-emerald-700">Excellent</div>
                                    <div class="mt-1 text-xs text-slate-500">Your automation setup is optimized.</div>
                                </div>
                            </div>
                            <a href="{{ route('communications.integrations') }}" class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-violet-700">
                                View Recommendations
                                <i data-lucide="arrow-right" class="size-3.5"></i>
                            </a>
                        </div>


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

                    </div>
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
