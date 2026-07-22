<x-app-layout title="Communications" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Total Notifications Sent', 'value' => number_format($stats['sent']), 'hint' => 'stored delivery records', 'icon' => 'send', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Delivery Rate', 'value' => $stats['delivery_rate'].'%', 'hint' => 'delivered / total', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Scheduled Messages', 'value' => number_format($stats['scheduled']), 'hint' => 'future campaigns', 'icon' => 'calendar-clock', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Pending Retries', 'value' => number_format($stats['queued']), 'hint' => 'waiting in retry queue', 'icon' => 'refresh-cw', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Failed Deliveries', 'value' => number_format($stats['failed']), 'hint' => 'requires review', 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Active Templates', 'value' => number_format($stats['templates']), 'hint' => 'approved templates', 'icon' => 'file-search', 'tone' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
            ['label' => 'Bulk Campaigns', 'value' => number_format($stats['campaigns']), 'hint' => 'last 30 days', 'icon' => 'messages-square', 'tone' => 'bg-teal-50 text-teal-600 ring-teal-100'],
            ['label' => 'Channel Integrations', 'value' => $stats['integrations'].' / '.count($channels), 'hint' => 'enabled providers', 'icon' => 'link', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Communications & Notifications</h1>
                <p class="text-sm text-slate-500">Events, attendance, registrations, members, and care workflows use one tracked communication system.</p>
            </div>
            <a href="{{ route('communications.integrations') }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                <i data-lucide="settings" class="size-4"></i>
                Communication Settings
            </a>
        </div>

        @include('communications.partials.flash')
        @include('communications.partials.subnav')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($cards as $card)
                <article class="dashboard-card">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl text-slate-950">{{ $card['value'] }}</div>
                            <div class="text-xs text-slate-500">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_1fr_1fr_320px]">
            <article class="dashboard-card">
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="bell-ring" class="size-4 text-violet-600"></i> Notification Triggers</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach($triggers as $trigger)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div>
                                <div class="font-medium text-slate-900">{{ $trigger['name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $trigger['template'] ? 'Template configured' : 'No template yet' }}</div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ number_format($trigger['queued']) }}</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('communications.templates') }}" class="mt-3 inline-flex text-sm text-violet-700">Manage triggers</a>
            </article>

            <article class="dashboard-card">
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="chart-column" class="size-4 text-violet-600"></i> Delivery Trend</h2>
                <div class="mt-4 h-64"><canvas data-chart="attendance" data-labels='@json(range(1, 30))' data-values='@json($trend)'></canvas></div>
            </article>

            <article class="dashboard-card">
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="chart-column" class="size-4 text-violet-600"></i> Channel Mix</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-[150px_1fr] md:items-center">
                    <div class="relative h-40">
                        <canvas data-chart="doughnut" data-labels='@json(collect($channelMix)->pluck("label"))' data-values='@json(collect($channelMix)->pluck("value"))' data-colors='@json(collect($channelMix)->pluck("color"))'></canvas>
                        <div class="pointer-events-none absolute inset-0 grid place-items-center text-center"><span><span class="block text-xl text-slate-950">{{ number_format($stats['sent']) }}</span><span class="text-xs text-slate-500">Total</span></span></div>
                    </div>
                    <div class="space-y-2 text-sm">
                        @foreach($channelMix as $row)
                            <div class="flex items-center justify-between gap-3">
                                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span>
                                <span class="text-slate-900">{{ number_format($row['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Channel Health</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach($providerHealth as $provider)
                            <div class="flex items-center justify-between">
                                <span>{{ $channels[$provider['channel']]['label'] }}</span>
                                <span class="{{ $provider['enabled'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $provider['enabled'] ? $provider['rate'].'%' : 'Disabled' }}</span>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('communications.integrations') }}" class="mt-4 inline-flex text-sm text-violet-700">View channel details</a>
                </section>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Scheduled Messages</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @forelse($scheduled as $campaign)
                            <div class="flex justify-between gap-3">
                                <span class="truncate text-slate-900">{{ $campaign->name }}</span>
                                <span class="shrink-0 text-slate-500">{{ $campaign->scheduled_at?->format('M d, h:i A') }}</span>
                            </div>
                        @empty
                            <p class="text-slate-500">No scheduled campaigns.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_1fr]">
            <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 p-4">
                    <h2 class="text-base font-semibold text-slate-950">Recent Communication Activity</h2>
                    <a href="{{ route('communications.delivery-logs') }}" class="text-sm text-violet-700">View all activity</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentDeliveries as $delivery)
                        <div class="flex items-center gap-3 p-4 text-sm">
                            <span class="grid size-9 place-items-center rounded-lg {{ $channels[$delivery->channel]['tone'] ?? 'bg-slate-50 text-slate-600 ring-slate-100' }}"><i data-lucide="{{ $channels[$delivery->channel]['icon'] ?? 'message-square' }}" class="size-4"></i></span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium text-slate-900">{{ $delivery->subject ?? Str::headline($delivery->event_type ?? 'Message') }}</div>
                                <div class="truncate text-xs text-slate-500">To: {{ $delivery->recipient_name }}</div>
                            </div>
                            <span class="rounded-full px-2 py-1 text-xs {{ $delivery->status === 'delivered' ? 'bg-emerald-50 text-emerald-700' : ($delivery->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-orange-50 text-orange-700') }}">{{ Str::headline($delivery->status) }}</span>
                        </div>
                    @empty
                        <div class="p-8"><x-empty-state icon="inbox" title="No communication activity" message="Create a template or campaign to start sending tracked messages." /></div>
                    @endforelse
                </div>
            </article>
            <article class="dashboard-card">
                <h2 class="text-base font-semibold text-slate-950">Quick Actions</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([['Create Template', 'communications.templates', 'file-search'], ['Send Bulk Campaign', 'communications.bulk', 'send'], ['Schedule Message', 'communications.scheduled', 'calendar-clock'], ['Test Channel', 'communications.integrations', 'radio-tower']] as [$label, $route, $icon])
                        <a href="{{ route($route) }}" class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700 hover:bg-violet-50 hover:text-violet-700">
                            <i data-lucide="{{ $icon }}" class="mb-3 size-5 text-violet-600"></i>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </article>
        </section>
    </div>
</x-app-layout>
