<x-app-layout title="Delivery Logs" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Total Delivery Attempts', 'value' => $stats['total'], 'hint' => '+ 14.6% vs last 30 days', 'icon' => 'send', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Successful Deliveries', 'value' => $stats['delivered'], 'hint' => '+ 15.6% vs last 30 days', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Failed Deliveries', 'value' => $stats['failed'], 'hint' => '- 6.2% vs last 30 days', 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Retry Queue Size', 'value' => $stats['retry_queue'], 'hint' => '+ 12.1% vs last 30 days', 'icon' => 'refresh-cw', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Average Send Time', 'value' => round($stats['avg_latency'] / 1000, 2).'s', 'hint' => '+ 8.7% vs last 30 days', 'icon' => 'clock', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Webhook Errors', 'value' => $stats['failed'], 'hint' => '+ 5.3% vs last 30 days', 'icon' => 'link', 'tone' => 'bg-pink-50 text-pink-600 ring-pink-100'],
        ];
        $statusClass = fn (?string $status): string => match ($status) {
            'delivered', 'sent' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-rose-50 text-rose-700',
            'retrying' => 'bg-orange-50 text-orange-700',
            'queued' => 'bg-violet-50 text-violet-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $retryClass = fn (?string $status): string => match ($status) {
            'queued' => 'text-violet-700',
            'processing' => 'text-blue-700',
            'backoff' => 'text-orange-700',
            default => 'text-slate-400',
        };
        $failedTotal = max((int) collect($failedReasons)->sum('total'), 1);
        $failedColors = ['#6d4aff', '#f43f5e', '#f97316', '#f59e0b', '#2477f2', '#64748b'];
        $heatMax = max(collect($deliveryHeatmap)->flatMap(fn ($row) => $row['hours'])->max() ?? 1, 1);
        $historyRows = [
            ['label' => 'Total Notifications', 'value' => $historySummary['notifications'], 'icon' => 'bell'],
            ['label' => 'Unique Recipients', 'value' => $historySummary['unique_recipients'], 'icon' => 'users-round'],
            ['label' => 'Channels Used', 'value' => $historySummary['channels_used'], 'icon' => 'messages-square'],
            ['label' => 'Templates Used', 'value' => $historySummary['templates_used'], 'icon' => 'file-text'],
            ['label' => 'Batches Sent', 'value' => $historySummary['batches_sent'], 'icon' => 'database'],
        ];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal text-slate-950">Delivery Logs & Retry Handling</h1>
                <p class="mt-1 text-sm text-slate-500">Monitor message delivery status, retry failures, and track communication events across all channels and providers.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('communications.integrations') }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="settings" class="size-4"></i>
                    Provider Diagnostics
                </a>
                <a href="{{ route('communications.delivery-logs.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="download" class="size-4"></i>
                    Download Logs
                </a>
                <a href="{{ route('communications.delivery-logs') }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="history" class="size-4"></i>
                    View Full History
                </a>
                <a href="{{ route('communications.delivery-logs', ['status' => 'failed']) }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="refresh-cw" class="size-4"></i>
                    Retry Selected
                    <i data-lucide="chevron-down" class="size-4"></i>
                </a>
                <a href="{{ route('communications.delivery-logs', ['retry_status' => 'queued']) }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="rotate-cw" class="size-4"></i>
                    Requeue Failed
                </a>
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
                            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                            <div class="mt-1 text-xs {{ str_starts_with($card['hint'], '-') ? 'text-rose-600' : 'text-emerald-600' }}">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <form method="GET" action="{{ route('communications.delivery-logs') }}" class="dashboard-card p-4">
            <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
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
                    <span>Provider</span>
                    <select name="provider" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Providers</option>
                        @foreach($providers as $provider)
                            <option value="{{ $provider }}" @selected(request('provider') === $provider)>{{ $provider }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Template</span>
                    <select name="template_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Templates</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected((string) request('template_id') === (string) $template->id)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Event Type</span>
                    <select name="event_type" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Event Types</option>
                        @foreach($eventTypes as $eventType)
                            <option value="{{ $eventType }}" @selected(request('event_type') === $eventType)>{{ $eventType }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Batch ID</span>
                    <input name="batch" value="{{ request('batch') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700" placeholder="Search batch ID...">
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Delivery Status</span>
                    <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Statuses</option>
                        @foreach(['queued', 'delivered', 'failed', 'retrying'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Retry Status</span>
                    <select name="retry_status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        <option value="">All Retry Status</option>
                        @foreach(['none', 'queued', 'processing', 'backoff'] as $status)
                            <option value="{{ $status }}" @selected(request('retry_status') === $status)>{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Search</span>
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                        <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 py-2.5 pl-9 pr-3 text-sm text-slate-700" placeholder="Search by ID, recipient, or message...">
                    </div>
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Date From</span>
                    <input name="date_from" type="date" value="{{ request('date_from') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                </label>
                <label class="space-y-1 text-xs text-slate-500">
                    <span>Date To</span>
                    <input name="date_to" type="date" value="{{ request('date_to') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                </label>
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-3">
                <a href="{{ route('communications.delivery-logs') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-violet-700 hover:bg-violet-50">Clear Filters</a>
                <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="filter" class="size-4"></i>
                    Apply Filters
                </button>
            </div>
        </form>

        <section class="dashboard-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm text-slate-600">Showing 1 to {{ $deliveries->count() }} of {{ number_format($deliveries->total()) }} results</span>
                    <span class="text-xs text-slate-400">(0 selected)</span>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 text-sm text-slate-500">Auto Refresh <span class="relative h-5 w-9 rounded-full bg-violet-600 after:absolute after:right-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-white"></span></span>
                    <a href="{{ route('communications.delivery-logs', request()->query()) }}" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:text-violet-700"><i data-lucide="refresh-cw" class="size-4"></i></a>
                    <span class="text-sm text-slate-500">Rows per page: <span class="text-slate-800">10</span></span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1480px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3"><input type="checkbox" class="rounded border-slate-300 text-violet-600"></th>
                            <th class="px-3 py-3">Timestamp</th>
                            <th class="px-3 py-3">Recipient</th>
                            <th class="px-3 py-3">Channel</th>
                            <th class="px-3 py-3">Provider</th>
                            <th class="px-3 py-3">Template</th>
                            <th class="px-3 py-3">Event Trigger</th>
                            <th class="px-3 py-3 text-right">Attempt</th>
                            <th class="px-3 py-3">Delivery Response</th>
                            <th class="px-3 py-3">Provider Message ID</th>
                            <th class="px-3 py-3 text-right">Latency</th>
                            <th class="px-3 py-3">Final Status</th>
                            <th class="px-3 py-3">Retry Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($deliveries as $delivery)
                            @php($channel = $channels[$delivery->channel] ?? ['label' => Str::headline($delivery->channel), 'icon' => 'radio', 'color' => '#6d4aff', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'])
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-3"><input type="checkbox" class="rounded border-slate-300 text-violet-600"></td>
                                <td class="px-3 py-3 text-slate-600">{{ $delivery->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="grid size-7 place-items-center rounded-full bg-slate-100 text-xs text-slate-600">{{ Str::substr($delivery->recipient_name ?: 'U', 0, 1) }}</span>
                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-slate-900">{{ $delivery->recipient_name }}</div>
                                            <div class="truncate text-xs text-slate-500">{{ $delivery->recipient_contact }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3"><span class="inline-flex items-center gap-2 text-slate-700"><i data-lucide="{{ $channel['icon'] }}" class="size-4" style="color: {{ $channel['color'] }}"></i>{{ $channel['label'] }}</span></td>
                                <td class="px-3 py-3 text-slate-600">{{ $delivery->provider }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $delivery->template?->name ?? $delivery->campaign?->name ?? 'Direct message' }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $delivery->event_type ?? 'Manual Message' }}</td>
                                <td class="px-3 py-3 text-right text-slate-900">{{ number_format($delivery->attempt) }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $delivery->response_code ?? $delivery->error ?? '-' }}</td>
                                <td class="max-w-[180px] truncate px-3 py-3 text-slate-500">{{ $delivery->provider_message_id ?? '-' }}</td>
                                <td class="px-3 py-3 text-right text-slate-600">{{ $delivery->latency_ms ? round($delivery->latency_ms / 1000, 2).'s' : '-' }}</td>
                                <td class="px-3 py-3"><span class="rounded-full px-3 py-1 text-xs {{ $statusClass($delivery->status) }}">{{ Str::headline($delivery->status) }}</span></td>
                                <td class="px-3 py-3">
                                    @if($delivery->status === 'failed')
                                        <form method="POST" action="{{ route('communications.delivery-logs.retry', $delivery) }}">
                                            @csrf
                                            <button class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs text-violet-700 hover:bg-violet-50"><i data-lucide="refresh-cw" class="size-3.5"></i>Retry</button>
                                        </form>
                                    @elseif($delivery->retry_status !== 'none')
                                        <span class="inline-flex items-center gap-1 text-xs {{ $retryClass($delivery->retry_status) }}"><i data-lucide="refresh-cw" class="size-3.5"></i>{{ Str::headline($delivery->retry_status) }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-5 py-12 text-center">
                                    <x-empty-state icon="clipboard-list" title="No delivery attempts" message="Sent campaigns and provider tests create auditable delivery records." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $deliveries->links() }}</div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.05fr_1.05fr_0.78fr_0.92fr_0.98fr_1.08fr]">
            <article class="dashboard-card p-4">
                <h2 class="text-base font-semibold text-slate-950">Failed Reason Breakdown <span class="font-normal text-slate-500">(Last 30 Days)</span></h2>
                <div class="mt-4 grid items-center gap-4 sm:grid-cols-[120px_1fr] xl:grid-cols-1 2xl:grid-cols-[120px_1fr]">
                    <div class="relative mx-auto h-32 w-32">
                        <canvas data-chart="doughnut" data-labels='@json(collect($failedReasons)->pluck("reason"))' data-values='@json(collect($failedReasons)->pluck("total"))' data-colors='@json($failedColors)'></canvas>
                        <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                            <div><div class="text-lg font-semibold text-slate-950">{{ number_format($stats['failed']) }}</div><div class="text-xs text-slate-500">Total Failed</div></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        @forelse($failedReasons as $index => $reason)
                            <div class="grid grid-cols-[1fr_auto] gap-2 text-xs">
                                <span class="truncate text-slate-600"><span class="mr-2 inline-block size-2 rounded-full" style="background-color: {{ $failedColors[$index % count($failedColors)] }}"></span>{{ $reason->reason }}</span>
                                <span class="text-slate-500">{{ round(($reason->total / $failedTotal) * 100, 1) }}%</span>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No failed deliveries.</p>
                        @endforelse
                    </div>
                </div>
            </article>

            <article class="dashboard-card p-4">
                <h2 class="text-base font-semibold text-slate-950">Delivery Heatmap <span class="font-normal text-slate-500">(Last 7 Days)</span></h2>
                <div class="mt-4 space-y-1">
                    <div class="ml-10 grid grid-cols-12 gap-1 text-[10px] text-slate-400">
                        @foreach(['12A', '4A', '8A', '12P', '4P', '8P'] as $label)
                            <span class="col-span-2 text-center">{{ $label }}</span>
                        @endforeach
                    </div>
                    @foreach($deliveryHeatmap as $row)
                        <div class="grid grid-cols-[32px_1fr] items-center gap-2">
                            <span class="text-xs text-slate-500">{{ $row['label'] }}</span>
                            <div class="grid grid-cols-12 gap-1">
                                @foreach($row['hours'] as $value)
                                    @php($opacity = 0.15 + (($value / $heatMax) * 0.85))
                                    <span class="h-4 rounded-sm bg-emerald-500" style="opacity: {{ $opacity }}"></span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <div class="mt-3 flex items-center justify-end gap-1 text-xs text-slate-500">Less <span class="h-2 w-12 rounded bg-gradient-to-r from-emerald-100 to-emerald-600"></span> More</div>
                </div>
            </article>

            <article class="dashboard-card p-4">
                <h2 class="text-base font-semibold text-slate-950">Retry Pipeline</h2>
                <div class="mt-4 space-y-2">
                    @foreach($retryPipeline as $row)
                        <div class="grid grid-cols-[auto_1fr_auto] items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm">
                            <span class="grid size-8 place-items-center rounded-full ring-1 {{ $row['tone'] }}"><i data-lucide="{{ $row['icon'] }}" class="size-4"></i></span>
                            <span class="text-slate-600">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($row['value']) }}</span>
                        </div>
                    @endforeach
                    <div class="rounded-lg border border-slate-200 px-3 py-2 text-center text-sm text-slate-600">Total in Pipeline {{ number_format(collect($retryPipeline)->sum('value')) }}</div>
                </div>
            </article>

            <article class="dashboard-card p-4">
                <h2 class="text-base font-semibold text-slate-950">Dead-Letter Queue</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Messages</dt><dd class="font-semibold text-slate-900">{{ number_format($stats['failed']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Oldest Message</dt><dd class="text-slate-700">{{ optional($deliveries->getCollection()->where('status', 'failed')->last()?->created_at)->format('M d, h:i A') ?? 'None' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Top Failure</dt><dd class="truncate text-slate-700">{{ $failedReasons[0]->reason ?? 'None' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Auto Reprocess</dt><dd class="text-slate-700">Off</dd></div>
                </dl>
                <a href="{{ route('communications.delivery-logs', ['status' => 'failed']) }}" class="mt-4 inline-flex w-full justify-center rounded-lg border border-violet-200 px-3 py-2 text-sm text-violet-700 hover:bg-violet-50">View Dead-Letter Queue</a>
            </article>

            <article class="dashboard-card p-4">
                <h2 class="text-base font-semibold text-slate-950">Provider Health <span class="font-normal text-slate-500">(Last 30 Days)</span></h2>
                <div class="mt-4 space-y-3">
                    @foreach($providerHealth as $provider)
                        @php($channel = $channels[$provider['channel']] ?? ['icon' => 'radio', 'color' => '#6d4aff'])
                        <div class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-2 text-sm">
                            <i data-lucide="{{ $channel['icon'] }}" class="size-4" style="color: {{ $channel['color'] }}"></i>
                            <span class="truncate text-slate-700">{{ $provider['provider'] }}</span>
                            <span class="text-slate-500">{{ $provider['rate'] }}%</span>
                            <span class="{{ $provider['rate'] >= 95 ? 'text-emerald-600' : 'text-orange-600' }}">{{ $provider['enabled'] ? 'Healthy' : 'Disabled' }}</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('communications.integrations') }}" class="mt-4 inline-flex w-full justify-center gap-2 border-t border-slate-100 pt-3 text-sm text-violet-700">View Provider Diagnostics <i data-lucide="arrow-right" class="size-4"></i></a>
            </article>

            <article class="dashboard-card p-4">
                <h2 class="text-base font-semibold text-slate-950">Communication History Summary <span class="font-normal text-slate-500">(Last 30 Days)</span></h2>
                <div class="mt-4 space-y-3">
                    @foreach($historyRows as $row)
                        <div class="grid grid-cols-[auto_1fr_auto] items-center gap-2 text-sm">
                            <i data-lucide="{{ $row['icon'] }}" class="size-4 text-slate-400"></i>
                            <span class="text-slate-600">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($row['value']) }}</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('communications.delivery-logs') }}" class="mt-4 inline-flex w-full justify-center gap-2 border-t border-slate-100 pt-3 text-sm text-violet-700">View Full History <i data-lucide="arrow-right" class="size-4"></i></a>
            </article>
        </section>

        <section class="dashboard-card p-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-950">Active Incidents <span class="font-normal text-slate-500">({{ count($failedReasons) }})</span></h2>
                <a href="{{ route('communications.delivery-logs', ['status' => 'failed']) }}" class="inline-flex items-center gap-2 text-sm text-violet-700">View All Incidents <i data-lucide="arrow-right" class="size-4"></i></a>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                @forelse($failedReasons as $reason)
                    <article class="rounded-lg border border-rose-100 bg-rose-50/40 p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-8 place-items-center rounded-full bg-white text-rose-600 ring-1 ring-rose-100"><i data-lucide="circle-alert" class="size-4"></i></span>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">{{ Str::headline(Str::limit($reason->reason, 32, '')) }}</div>
                                <div class="mt-1 text-xs text-slate-500">Started: {{ now()->subMinutes(31)->format('M d, h:i A') }}</div>
                                <span class="mt-3 inline-flex rounded bg-orange-500 px-2 py-1 text-xs text-white">Investigating</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <article class="rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-700">No active delivery incidents.</article>
                @endforelse
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
