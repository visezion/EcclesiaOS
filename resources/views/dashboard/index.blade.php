<x-app-layout title="Dashboard">
    @php
        $dashboardSpan = function (int $visible, int $default): int {
            return match ($visible) {
                1 => 12,
                2 => 6,
                3 => 4,
                default => $default,
            };
        };
        $summaryPanelCount = collect([
            $dashboardSections['attendance'] ?? false,
            $dashboardSections['giving'] ?? false,
            $dashboardSections['bookstore'] ?? false,
        ])->filter()->count();
        $operationsPanelCount = collect([
            $dashboardSections['assets'] ?? false,
            $dashboardSections['leadership'] ?? false,
            $dashboardSections['feedback'] ?? false,
            $dashboardSections['events'] ?? false,
        ])->filter()->count();
        $insightsPanelCount = collect([
            $dashboardSections['ministries'] ?? false,
            $dashboardSections['campuses'] ?? false,
            $dashboardSections['insights'] ?? false,
            $dashboardSections['activity'] ?? false,
        ])->filter()->count();
    @endphp

    <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        @foreach ($summaryMetrics as $metric)
            <x-stat-card :metric="$metric" />
        @endforeach
    </div>

    @if (($dashboardSections['attendance'] ?? false) || ($dashboardSections['giving'] ?? false) || ($dashboardSections['bookstore'] ?? false))
    <div class="mt-4 grid gap-4 xl:grid-cols-12">
        @if ($dashboardSections['attendance'] ?? false)
        <x-dashboard-card title="Attendance Trend" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($summaryPanelCount, 4) }};">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <div class="text-3xl font-bold text-slate-950">{{ $attendanceTrend['average'] }}</div>
                    <div class="mt-1 text-sm text-slate-500">Average Attendance</div>
                    <div class="mt-1 text-xs text-emerald-600"><i data-lucide="arrow-up" class="inline size-3"></i> {{ $attendanceTrend['change'] }} vs last 6 months</div>
                </div>
                <select class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">
                    <option>Last 6 Months</option>
                </select>
            </div>
            <div class="h-60">
                <canvas data-chart="attendance" data-labels='@json($attendanceTrend['labels'])' data-values='@json($attendanceTrend['values'])'></canvas>
            </div>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['giving'] ?? false)
        <x-dashboard-card title="Giving Overview" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($summaryPanelCount, 3) }};">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <div class="text-3xl font-bold text-slate-950">{{ $givingOverview['total'] }}</div>
                    <div class="mt-1 text-sm text-slate-500">Total Giving</div>
                    <div class="mt-1 text-xs text-emerald-600"><i data-lucide="arrow-up" class="inline size-3"></i> {{ $givingOverview['change'] }} vs last month</div>
                </div>
                <select class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">
                    <option>This Month</option>
                </select>
            </div>
            <div class="h-60">
                <canvas data-chart="giving" data-labels='@json(collect($givingOverview['categories'])->pluck('label'))' data-values='@json(collect($givingOverview['categories'])->pluck('amount'))'></canvas>
            </div>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['bookstore'] ?? false)
        <x-dashboard-card title="Book Store Snapshot" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($summaryPanelCount, 5) }};" :action="['label' => 'View Store', 'url' => route('bookstore.index')]">
            <div class="grid gap-3 sm:grid-cols-4">
                @foreach ($bookstore['totals'] as $total)
                    <div class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="grid size-10 place-items-center rounded-full bg-blue-100 text-blue-600"><i data-lucide="{{ $total['icon'] }}" class="size-5"></i></div>
                        <div class="min-w-0">
                            <div class="truncate text-[11px] font-bold text-slate-500">{{ $total['label'] }}</div>
                            <div class="text-lg font-black">{{ $total['value'] }}</div>
                            <div class="truncate text-[11px] text-slate-500">{{ $total['note'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_230px]">
                <div class="overflow-x-auto">
                    <div class="mb-2 text-xs font-bold text-slate-700">Top Selling Books</div>
                    <table class="table-compact">
                        <thead><tr><th>#</th><th>Book Title</th><th>Sold</th><th>Revenue</th></tr></thead>
                        <tbody>
                            @foreach ($bookstore['topBooks'] as $book)
                                <tr><td>{{ $loop->iteration }}</td><td>{{ $book['title'] }}</td><td>{{ $book['sold'] }}</td><td>{{ $book['revenue'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    <div class="mb-2 text-xs font-bold text-slate-700">Revenue by Category</div>
                    <div class="grid grid-cols-[120px_1fr] items-center gap-3">
                        <div class="h-32"><canvas data-chart="doughnut" data-labels='@json(collect($bookstore['categories'])->pluck('label'))' data-values='@json(collect($bookstore['categories'])->pluck('value'))'></canvas></div>
                        <div class="space-y-2">
                            @foreach ($bookstore['categories'] as $category)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="truncate">{{ $category['label'] }}</span>
                                    <span class="font-bold">{{ $category['value'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </x-dashboard-card>
        @endif
    </div>
    @endif

    @if (($dashboardSections['assets'] ?? false) || ($dashboardSections['leadership'] ?? false) || ($dashboardSections['feedback'] ?? false) || ($dashboardSections['events'] ?? false))
    <div class="mt-4 grid gap-4 xl:grid-cols-12">
        @if ($dashboardSections['assets'] ?? false)
        <x-dashboard-card title="Asset Inventory Overview" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($operationsPanelCount, 4) }};" :action="['label' => 'View All Assets', 'url' => route('assets.index')]">
            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead><tr><th>Asset Category</th><th>Total</th><th>In Use</th><th>Available</th><th>Maintenance</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($assets as $asset)
                            <tr>
                                <td class="font-semibold text-slate-700">{{ $asset['category'] }}</td>
                                <td>{{ $asset['total'] }}</td>
                                <td>{{ $asset['in_use'] }}</td>
                                <td>{{ $asset['available'] }}</td>
                                <td>{{ $asset['maintenance'] }}</td>
                                <td><x-status-badge :status="$asset['status']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['leadership'] ?? false)
        <x-dashboard-card title="Pastor & Leadership Report" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($operationsPanelCount, 3) }};">
            <div class="space-y-3">
                @foreach ($leadership as $row)
                    <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="grid size-8 shrink-0 place-items-center rounded-full bg-violet-100 text-violet-600"><i data-lucide="{{ $row['icon'] }}" class="size-4"></i></div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-slate-800">{{ $row['label'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['status'] }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-black">{{ $row['value'] }}</div>
                            <x-trend-indicator :value="$row['change']" />
                        </div>
                        <div class="sparkline"><canvas data-chart="sparkline" data-color="#2477f2" data-values='@json($row['sparkline'])'></canvas></div>
                    </div>
                @endforeach
            </div>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['feedback'] ?? false)
        <x-dashboard-card title="Feedback System Overview" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($operationsPanelCount, 3) }};">
            <div class="grid grid-cols-3 gap-3 border-b border-slate-100 pb-4">
                <div><div class="text-xs font-bold text-slate-500">Survey Responses</div><div class="mt-2 text-2xl font-black">{{ $feedback['summary']['responses'] }}</div></div>
                <div><div class="text-xs font-bold text-slate-500">Satisfaction</div><div class="mt-2 flex items-center gap-1 text-2xl font-black text-slate-950"><i data-lucide="star" class="size-5 fill-amber-400 text-amber-400"></i> {{ $feedback['summary']['satisfaction'] }}</div></div>
                <div><div class="text-xs font-bold text-slate-500">NPS</div><div class="mt-2 text-2xl font-black">{{ $feedback['summary']['nps'] }}</div><div class="text-xs font-semibold text-emerald-600">{{ $feedback['summary']['resolved'] }} resolved</div></div>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-[1fr_150px]">
                <div class="space-y-2">
                    @foreach ($feedback['counts'] as $count)
                        <div class="flex justify-between text-sm"><span>{{ $count['label'] }}</span><span class="font-bold">{{ $count['value'] }}</span></div>
                    @endforeach
                </div>
                <div class="h-32"><canvas data-chart="doughnut" data-labels='@json(collect($feedback['sentiment'])->pluck('label'))' data-values='@json(collect($feedback['sentiment'])->pluck('value'))'></canvas></div>
            </div>
            <div class="mt-4 rounded-lg bg-orange-50 p-3 text-sm font-semibold text-orange-700">Follow-up Pending: {{ $feedback['pending'] }}</div>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['events'] ?? false)
        <x-dashboard-card title="Upcoming Services & Events" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($operationsPanelCount, 2) }};" :action="['label' => 'View Calendar', 'url' => route('events.index')]">
            @foreach ($events as $event)
                <x-event-item :event="$event" />
            @endforeach
        </x-dashboard-card>
        @endif
    </div>
    @endif

    @if (($dashboardSections['ministries'] ?? false) || ($dashboardSections['campuses'] ?? false) || ($dashboardSections['insights'] ?? false) || ($dashboardSections['activity'] ?? false))
    <div class="mt-4 grid gap-4 xl:grid-cols-12">
        @if ($dashboardSections['ministries'] ?? false)
        <x-dashboard-card title="Ministry & Department Performance" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($insightsPanelCount, 3) }};" :action="['label' => 'View All', 'url' => route('ministries.index')]">
            <table class="table-compact">
                <thead><tr><th>Ministry</th><th>Members</th><th>Activities</th><th>Impact</th><th>Trend</th></tr></thead>
                <tbody>
                    @foreach ($ministries as $ministry)
                        <tr><td class="font-semibold">{{ $ministry['ministry'] }}</td><td>{{ $ministry['members'] }}</td><td>{{ $ministry['activities'] }}</td><td class="font-bold text-emerald-600">{{ $ministry['impact'] }}</td><td><i data-lucide="trending-up" class="size-4 text-emerald-600"></i></td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['campuses'] ?? false)
        <x-dashboard-card title="Branches & Campuses Overview" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($insightsPanelCount, 3) }};" :action="['label' => 'View All', 'url' => route('campuses.index')]">
            <div class="grid gap-4 md:grid-cols-[1fr_1fr] xl:grid-cols-1 2xl:grid-cols-[1fr_1fr]">
                <div class="map-placeholder relative min-h-48 rounded-lg border border-slate-200">
                    @foreach ($campuses as $campus)
                        <span class="absolute grid size-6 place-items-center rounded-full bg-violet-600 text-white shadow-lg ring-4 ring-white" style="left: {{ $campus['x'] }}%; top: {{ $campus['y'] }}%">
                            <i data-lucide="map-pin" class="size-3"></i>
                        </span>
                    @endforeach
                </div>
                <div class="space-y-3">
                    @foreach ($campuses as $campus)
                        <div class="flex justify-between gap-3 text-sm">
                            <div class="min-w-0"><div class="truncate font-bold">{{ $campus['name'] }}</div><div class="truncate text-xs text-slate-500">{{ $campus['location'] }}</div></div>
                            <div class="text-right"><div class="font-bold">{{ $campus['attendance'] }}</div><div class="text-xs font-semibold text-emerald-600">{{ $campus['status'] }}</div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['insights'] ?? false)
        <x-dashboard-card title="AI Insights & Smart Recommendations" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($insightsPanelCount, 3) }};" :action="['label' => 'View All Insights', 'url' => route('reports.index')]">
            <div class="mb-3 rounded-lg border border-dashed border-violet-200 bg-violet-50 p-3 text-xs text-violet-700">Insights are calculated from current database records.</div>
            <div class="space-y-3">
                @foreach ($insights as $insight)
                    <div class="flex gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-violet-600"><i data-lucide="{{ $insight['icon'] }}" class="size-5"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-slate-500">{{ $insight['title'] }}</div>
                            <div class="truncate text-sm font-black text-slate-900">{{ $insight['value'] }}</div>
                            <div class="text-xs text-slate-500">{{ $insight['detail'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-dashboard-card>
        @endif

        @if ($dashboardSections['activity'] ?? false)
        <x-dashboard-card title="Recent Activity Feed" class="dashboard-auto-span" style="--dashboard-span: {{ $dashboardSpan($insightsPanelCount, 3) }};" :action="['label' => 'View All', 'url' => route('audit-logs.index')]">
            @foreach ($activities as $activity)
                <x-activity-item :activity="$activity" />
            @endforeach
        </x-dashboard-card>
        @endif
    </div>
    @endif

    @if ($dashboardSections['quickActions'] ?? false)
    <x-dashboard-card title="Quick Actions" class="mt-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-8">
            @foreach ($quickActions as $action)
                <x-quick-action :action="$action" />
            @endforeach
        </div>
    </x-dashboard-card>
    @endif
</x-app-layout>
