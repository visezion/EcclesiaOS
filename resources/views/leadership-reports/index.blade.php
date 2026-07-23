<x-app-layout title="Pastor & Leadership Reports" :breadcrumbs="$breadcrumbs">
    @php
        $statusMeta = [
            'draft' => ['label' => 'Draft', 'icon' => 'clock', 'class' => 'bg-slate-100 text-slate-700 ring-slate-200', 'hex' => '#94a3b8'],
            'submitted' => ['label' => 'Submitted', 'icon' => 'send', 'class' => 'bg-blue-50 text-blue-700 ring-blue-100', 'hex' => '#2477f2'],
            'under_review' => ['label' => 'Under Review', 'icon' => 'clock3', 'class' => 'bg-violet-50 text-violet-700 ring-violet-100', 'hex' => '#6d4aff'],
            'approved' => ['label' => 'Approved', 'icon' => 'check-circle-2', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'hex' => '#10b981'],
            'returned' => ['label' => 'Returned', 'icon' => 'rotate-ccw', 'class' => 'bg-orange-50 text-orange-700 ring-orange-100', 'hex' => '#f97316'],
            'rejected' => ['label' => 'Rejected', 'icon' => 'triangle-alert', 'class' => 'bg-rose-50 text-rose-700 ring-rose-100', 'hex' => '#f43f5e'],
        ];
        $priorityClasses = [
            'low' => 'bg-slate-100 text-slate-700',
            'normal' => 'bg-blue-50 text-blue-700',
            'high' => 'bg-orange-50 text-orange-700',
            'urgent' => 'bg-rose-50 text-rose-700',
        ];
        $statCards = [
            ['label' => 'Reports Submitted', 'value' => $stats['submitted'], 'note' => '+ 12% from last week', 'icon' => 'send', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Reports Reviewed', 'value' => $stats['reviewed'], 'note' => '+ 8% from last week', 'icon' => 'check-circle-2', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Pending Review', 'value' => $stats['pending_review'], 'note' => 'awaiting decision', 'icon' => 'clock3', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Requires Action', 'value' => $stats['requires_action'], 'note' => 'returned or rejected', 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Average Review Time', 'value' => $stats['average_review_time'].' days', 'note' => 'submitted to review', 'icon' => 'clock', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
        ];
        $flowTotal = max(collect($flow)->sum('count'), 1);
        $activeTab = $filters['tab'];
        $tabLabels = [
            'overview' => 'Overview',
            'my' => 'My Reports',
            'to-me' => 'Reports To Me',
            'all' => 'All Reports',
            'analytics' => 'Analytics',
            'templates' => 'Templates',
            'settings' => 'Settings',
        ];
        $listTitle = match ($activeTab) {
            'my' => 'My Submitted Reports',
            'to-me' => 'Reports Assigned To Me',
            'all' => 'All Leadership Reports',
            default => 'Recent Reports',
        };
        $flowSegments = [];
        $running = 0;
        foreach ($flow as $row) {
            if ($row['count'] <= 0) {
                continue;
            }
            $start = round(($running / $flowTotal) * 100, 2);
            $running += $row['count'];
            $end = round(($running / $flowTotal) * 100, 2);
            $flowSegments[] = ($statusMeta[$row['status']]['hex'] ?? '#94a3b8').' '.$start.'% '.$end.'%';
        }
        $flowGradient = $flowSegments ? 'conic-gradient('.implode(', ', $flowSegments).')' : '#e2e8f0';
        $trendDatasets = [
            ['label' => 'Submitted', 'values' => $trend['submitted'], 'color' => '#6d4aff'],
            ['label' => 'Reviewed', 'values' => $trend['reviewed'], 'color' => '#10b981'],
            ['label' => 'Approved', 'values' => $trend['approved'], 'color' => '#2477f2'],
            ['label' => 'Returned', 'values' => $trend['returned'], 'color' => '#f97316'],
            ['label' => 'Rejected', 'values' => $trend['rejected'], 'color' => '#f43f5e'],
        ];
        $metricAverages = \App\Models\LeadershipReport::query()
            ->where('church_id', auth()->user()?->church_id)
            ->get()
            ->flatMap(fn ($report) => collect($report->metrics ?? [])->map(fn ($value, $key) => ['key' => $key, 'value' => (int) $value]))
            ->groupBy('key')
            ->map(fn ($rows) => round($rows->avg('value')));
    @endphp

    <div x-data="{ createOpen: {{ $errors->any() ? 'true' : 'false' }}, createStep: 1, createTotal: 8, nextStep() { this.createStep = Math.min(this.createTotal, this.createStep + 1) }, prevStep() { this.createStep = Math.max(1, this.createStep - 1) } }" class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Pastor & Leadership Reports</h1>
                <p class="mt-1 text-sm text-slate-500">Oversee, review, and act on reports from pastors, ministries, departments, and campuses.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('leadership-reports.summary') }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 shadow-sm hover:bg-violet-50">
                        <i data-lucide="chart-column" class="size-4"></i>
                        Generate Summary
                    </button>
                </form>
                <button type="button" @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    New Report
                </button>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if(session('error') || $errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ session('error') ?? $errors->first() }}</div>
        @endif

        <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 text-sm">
            @foreach ($tabLabels as $tab => $label)
                <a href="{{ route('leadership-reports.index', array_merge(request()->except('page'), ['tab' => $tab])) }}" class="{{ $filters['tab'] === $tab ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-600 hover:text-violet-700' }} shrink-0 border-b-2 px-4 py-3 font-semibold">{{ $label }}</a>
            @endforeach
        </nav>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach($statCards as $card)
                <article class="dashboard-card">
                    <div class="flex items-center gap-4">
                        <span class="grid size-12 place-items-center rounded-xl ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-6"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-bold text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                            <div class="mt-1 text-xs font-medium text-emerald-600">{{ $card['note'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        @if(in_array($activeTab, ['overview', 'analytics'], true))
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px_320px]">
            <article class="dashboard-card p-0 xl:col-span-1">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-base font-semibold text-slate-950">Reports Overview</h2>
                    <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">This Week</span>
                </div>
                <div class="grid gap-4 p-4 lg:grid-cols-[250px_1fr]">
                    <div class="flex items-center gap-4">
                        <div class="relative size-44 shrink-0">
                            <div class="absolute inset-0 rounded-full p-[18px]" style="background: {{ $flowGradient }}">
                                <div class="size-full rounded-full bg-white"></div>
                            </div>
                            <div class="absolute inset-0 grid place-items-center text-center">
                                <div>
                                    <div class="text-2xl font-bold text-slate-950">{{ number_format($flowTotal) }}</div>
                                    <div class="text-[10px] font-bold uppercase text-slate-400">Total</div>
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            @foreach($flow as $row)
                                @php($percent = round(($row['count'] / $flowTotal) * 100, 1))
                                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-2 text-xs">
                                    <span class="flex min-w-0 items-center gap-2 text-slate-600"><span class="size-2 rounded-full" style="background: {{ $statusMeta[$row['status']]['hex'] ?? '#94a3b8' }}"></span><span class="truncate">{{ $row['label'] }}</span></span>
                                    <span class="font-semibold text-slate-950">{{ $row['count'] }}</span>
                                    <span class="w-12 text-right text-slate-500">{{ $percent }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-950">Reports Trend</h3>
                            <div class="inline-flex rounded-lg bg-slate-100 p-1 text-xs text-slate-600"><span class="rounded-md bg-white px-3 py-1 text-violet-700 shadow-sm">Weekly</span><span class="px-3 py-1">Monthly</span></div>
                        </div>
                        <div class="h-56 rounded-lg border border-slate-100 bg-white p-3">
                            <canvas data-chart="multi-line" data-labels='@json($trend['labels'])' data-datasets='@json($trendDatasets)'></canvas>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-3 text-[11px] font-semibold text-slate-500">
                            @foreach($trendDatasets as $dataset)
                                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: {{ $dataset['color'] }}"></span>{{ $dataset['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>

            <article class="dashboard-card">
                <h2 class="mb-4 text-base font-semibold text-slate-950">Report Flow Overview</h2>
                <div class="space-y-3">
                    @foreach($flow as $row)
                        @php($meta = $statusMeta[$row['status']] ?? $statusMeta['draft'])
                        <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3">
                            <span class="grid size-10 place-items-center rounded-lg ring-1 {{ $meta['class'] }}"><i data-lucide="{{ $meta['icon'] }}" class="size-5"></i></span>
                            <div class="min-w-0 flex-1"><div class="font-semibold text-slate-950">{{ $row['label'] }}</div><div class="text-xs text-slate-500">Reports in this stage</div></div>
                            <div class="text-lg font-bold text-slate-950">{{ $row['count'] }}</div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="dashboard-card">
                <h2 class="mb-4 text-base font-semibold text-slate-950">Reporting Relationships</h2>
                <div class="space-y-3">
                    @foreach($relationships as $relationship)
                        <div class="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
                            <span class="flex min-w-0 items-center gap-2 text-slate-600"><span class="size-2 rounded-full bg-violet-600"></span><span class="truncate">{{ $relationship['label'] }}</span></span>
                            <span class="font-semibold text-slate-950">{{ number_format($relationship['count']) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 rounded-lg bg-violet-50 p-4 text-sm text-violet-800">Relationships are calculated from assigned reviewers, campuses, and ministry ownership in the reports table.</div>
            </article>
        </section>
        @endif

        @if($activeTab === 'analytics')
            <section class="grid gap-4 lg:grid-cols-4">
                @foreach($metricAverages as $metric => $average)
                    <article class="dashboard-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ Str::headline($metric) }}</div>
                                <div class="mt-2 text-3xl font-bold text-slate-950">{{ $average }}{{ str_contains($metric, 'score') || str_contains($metric, 'coverage') ? '%' : '' }}</div>
                                <div class="mt-1 text-xs text-slate-500">Average across visible reports</div>
                            </div>
                            <span class="grid size-12 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="chart-no-axes-column" class="size-6"></i></span>
                        </div>
                        <div class="mt-4 h-2 rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-violet-600" style="width: {{ min(100, max(4, (int) $average)) }}%"></div>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if(in_array($activeTab, ['overview', 'my', 'to-me', 'all'], true))
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <article class="dashboard-card p-0">
                <form method="GET" action="{{ route('leadership-reports.index') }}" class="flex flex-col gap-3 border-b border-slate-100 p-4 xl:flex-row xl:items-end">
                    <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
                    <div class="flex-1"><h2 class="text-base font-semibold text-slate-950">{{ $listTitle }}</h2><p class="mt-1 text-xs text-slate-500">Database-backed reports awaiting review or action.</p></div>
                    <div class="min-w-64 flex-1">
                        <label class="sr-only" for="leadership-report-search">Search reports</label>
                        <div class="relative">
                            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            <input id="leadership-report-search" name="q" value="{{ $filters['q'] }}" placeholder="Search reports, pastors, campuses..." class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm">
                        </div>
                    </div>
                    <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach(['all' => 'All Statuses'] + collect($statuses)->mapWithKeys(fn($status) => [$status => Str::headline($status)])->all() as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select>
                    <select name="campus" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="all">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) $campus->id === $filters['campus'])>{{ $campus->name }}</option>@endforeach</select>
                    <select name="type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="all">All Types</option>@foreach($types as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ Str::headline($type) }}</option>@endforeach</select>
                    <button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="filter" class="size-4"></i>Filters</button>
                </form>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Report Title</th><th class="px-4 py-3">From</th><th class="px-4 py-3">To</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Submitted</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reports as $report)
                                @php($meta = $statusMeta[$report->status] ?? $statusMeta['draft'])
                                <tr class="{{ $selectedReport?->id === $report->id ? 'bg-violet-50/60' : 'bg-white' }}">
                                    <td class="px-4 py-3"><a href="{{ route('leadership-reports.show', $report) }}" class="font-semibold text-violet-700">{{ $report->title }}</a><div class="text-xs text-slate-500">{{ Str::headline($report->report_type) }} report</div></td>
                                    <td class="px-4 py-3 text-slate-600">{{ $report->submitter?->name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $report->reviewer?->name ?? 'Unassigned' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $report->period_start?->format('M d') }} - {{ $report->period_end?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $meta['class'] }}">{{ $meta['label'] }}</span></td>
                                    <td class="px-4 py-3 text-slate-600">{{ $report->submitted_at?->format('M d, h:i A') ?? 'Draft' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('leadership-reports.show', $report) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-violet-50 hover:text-violet-700" title="View full report"><i data-lucide="eye" class="size-4"></i></a>
                                            @if(in_array($report->status, ['submitted', 'under_review'], true))
                                                <form method="POST" action="{{ route('leadership-reports.review', $report) }}">@csrf @method('PUT')<input type="hidden" name="decision" value="approved"><button class="grid size-8 place-items-center rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50" title="Approve"><i data-lucide="check" class="size-4"></i></button></form>
                                                <form method="POST" action="{{ route('leadership-reports.review', $report) }}">@csrf @method('PUT')<input type="hidden" name="decision" value="returned"><button class="grid size-8 place-items-center rounded-lg border border-orange-200 text-orange-600 hover:bg-orange-50" title="Return"><i data-lucide="rotate-ccw" class="size-4"></i></button></form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No leadership reports match the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500"><span>Showing {{ $reports->firstItem() ?? 0 }} to {{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} reports</span>{{ $reports->links() }}</div>
            </article>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between"><h2 class="text-base font-semibold text-slate-950">Selected Report</h2>@if($selectedReport)<span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClasses[$selectedReport->priority] ?? $priorityClasses['normal'] }}">{{ Str::headline($selectedReport->priority) }}</span>@endif</div>
                    @if($selectedReport)
                        @php($meta = $statusMeta[$selectedReport->status] ?? $statusMeta['draft'])
                        <div class="space-y-4">
                            <div><div class="font-semibold text-slate-950">{{ $selectedReport->title }}</div><div class="mt-1 text-xs text-slate-500">{{ $selectedReport->campus?->name ?? 'All Campuses' }} - {{ $selectedReport->ministry?->name ?? 'General Leadership' }}</div></div>
                            <p class="text-sm leading-6 text-slate-600">{{ $selectedReport->summary }}</p>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                @foreach($selectedReport->metrics ?? [] as $metric => $value)
                                    @if(is_scalar($value))
                                        <div class="rounded-lg bg-slate-50 p-3"><div class="font-semibold text-slate-950">{{ is_numeric($value) ? number_format($value) : $value }}</div><div class="mt-1 text-slate-500">{{ Str::headline($metric) }}</div></div>
                                    @endif
                                @endforeach
                            </div>
                            <div>
                                <div class="mb-2 text-xs font-semibold uppercase text-slate-500">Action Items</div>
                                <div class="space-y-2">@forelse($selectedReport->action_items ?? [] as $item)<div class="flex gap-2 text-sm text-slate-600"><i data-lucide="check-circle-2" class="mt-0.5 size-4 shrink-0 text-emerald-600"></i>{{ $item }}</div>@empty<div class="text-sm text-slate-500">No action items recorded.</div>@endforelse</div>
                            </div>
                            <a href="{{ route('leadership-reports.show', $selectedReport) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-violet-200 px-3 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="eye" class="size-4"></i>Open Full Detail</a>
                            <form method="POST" action="{{ route('leadership-reports.review', $selectedReport) }}" class="space-y-3">
                                @csrf
                                @method('PUT')
                                <textarea name="review_notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Review note or return reason">{{ $selectedReport->review_notes }}</textarea>
                                <div class="grid grid-cols-2 gap-2">
                                    <button name="decision" value="under_review" class="rounded-lg border border-violet-200 px-3 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">Review</button>
                                    <button name="decision" value="approved" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                                    <button name="decision" value="returned" class="rounded-lg border border-orange-200 px-3 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-50">Return</button>
                                    <button name="decision" value="rejected" class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Select a report to review its details.</div>
                    @endif
                </section>

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between"><h2 class="text-base font-semibold text-slate-950">Recent Activity</h2><a href="{{ route('audit-logs.index') }}" class="text-xs font-semibold text-violet-700">View all</a></div>
                    <div class="space-y-4">
                        @forelse($recentActivity as $activity)
                            <div class="flex gap-3 text-sm"><span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="file-text" class="size-4"></i></span><div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">{{ $activity->description }}</div><div class="text-xs text-slate-500">{{ $activity->created_at?->diffForHumans() }}</div></div></div>
                        @empty
                            <div class="text-sm text-slate-500">No leadership report activity yet.</div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>
        @endif

        @if($activeTab === 'templates')
            <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                @foreach($templates as $template)
                    <form method="POST" action="{{ route('leadership-reports.store') }}" class="dashboard-card flex flex-col">
                        @csrf
                        <input type="hidden" name="title" value="{{ $template['name'].' - '.now()->format('M d, Y') }}">
                        <input type="hidden" name="report_type" value="{{ $template['type'] }}">
                        <input type="hidden" name="priority" value="{{ $template['priority'] }}">
                        <input type="hidden" name="assigned_to" value="{{ $reportSettings['default_reviewer_id'] }}">
                        <input type="hidden" name="period_start" value="{{ now()->startOfWeek()->toDateString() }}">
                        <input type="hidden" name="period_end" value="{{ now()->endOfWeek()->toDateString() }}">
                        <input type="hidden" name="summary" value="{{ $template['summary'] }}">
                        <input type="hidden" name="action_items" value="{{ $template['actions'] }}">
                        <input type="hidden" name="attendance_score" value="{{ $template['metrics'][0] }}">
                        <input type="hidden" name="discipleship_score" value="{{ $template['metrics'][1] }}">
                        <input type="hidden" name="care_followups" value="{{ $template['metrics'][2] }}">
                        <input type="hidden" name="volunteer_coverage" value="{{ $template['metrics'][3] }}">
                        <div class="flex items-start gap-3">
                            <span class="grid size-12 place-items-center rounded-xl ring-1 {{ $template['tone'] }}"><i data-lucide="{{ $template['icon'] }}" class="size-6"></i></span>
                            <div>
                                <h2 class="font-semibold text-slate-950">{{ $template['name'] }}</h2>
                                <div class="mt-1 text-xs font-semibold uppercase text-slate-400">{{ Str::headline($template['type']) }} template</div>
                            </div>
                        </div>
                        <p class="mt-4 min-h-20 text-sm leading-6 text-slate-600">{{ $template['description'] }}</p>
                        <div class="mt-4 space-y-2 text-xs text-slate-600">
                            @foreach(explode("\n", $template['actions']) as $action)
                                <div class="flex gap-2"><i data-lucide="check-circle-2" class="size-4 shrink-0 text-emerald-600"></i>{{ $action }}</div>
                            @endforeach
                        </div>
                        <button name="submit" value="0" class="mt-5 rounded-lg border border-violet-200 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">Use Template</button>
                    </form>
                @endforeach
            </section>
        @endif

        @if($activeTab === 'settings')
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <form method="POST" action="{{ route('leadership-reports.settings.update') }}" class="dashboard-card">
                    @csrf
                    @method('PUT')
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Leadership Report Settings</h2>
                            <p class="mt-1 text-sm text-slate-500">Controls report ownership, due dates, reminders, and escalation behavior for this church.</p>
                        </div>
                        <span class="grid size-12 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="settings" class="size-6"></i></span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="space-y-1 text-xs font-semibold text-slate-500">Default Reviewer
                            <select name="default_reviewer_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <option value="">No default reviewer</option>
                                @foreach($reporters as $reporter)
                                    <option value="{{ $reporter->id }}" @selected((string) $reportSettings['default_reviewer_id'] === (string) $reporter->id)>{{ $reporter->name }} - {{ $reporter->title }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1 text-xs font-semibold text-slate-500">Weekly Due Day
                            <select name="weekly_due_day" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                    <option value="{{ $day }}" @selected($reportSettings['weekly_due_day'] === $day)>{{ Str::headline($day) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1 text-xs font-semibold text-slate-500">Escalation Window
                            <input name="escalation_hours" type="number" min="1" max="720" value="{{ $reportSettings['escalation_hours'] }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span class="text-xs font-normal text-slate-400">Hours before overdue reports are escalated.</span>
                        </label>
                        <div class="grid gap-3">
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-700">
                                Auto Reminders
                                <input name="auto_reminders" value="1" type="checkbox" @checked($reportSettings['auto_reminders']) class="rounded border-slate-300 text-violet-600">
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-700">
                                Require Action Items
                                <input name="require_action_items" value="1" type="checkbox" @checked($reportSettings['require_action_items']) class="rounded border-slate-300 text-violet-600">
                            </label>
                        </div>
                    </div>
                    <button class="mt-5 inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>Save Settings</button>
                </form>
                <aside class="space-y-4">
                    <article class="dashboard-card">
                        <h2 class="mb-3 text-base font-semibold text-slate-950">Current Policy</h2>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between gap-3"><span class="text-slate-500">Default reviewer</span><span class="font-semibold text-slate-950">{{ $reporters->firstWhere('id', $reportSettings['default_reviewer_id'])?->name ?? 'Not assigned' }}</span></div>
                            <div class="flex justify-between gap-3"><span class="text-slate-500">Weekly due day</span><span class="font-semibold text-slate-950">{{ Str::headline($reportSettings['weekly_due_day']) }}</span></div>
                            <div class="flex justify-between gap-3"><span class="text-slate-500">Escalation</span><span class="font-semibold text-slate-950">{{ $reportSettings['escalation_hours'] }} hours</span></div>
                            <div class="flex justify-between gap-3"><span class="text-slate-500">Last updated</span><span class="font-semibold text-slate-950">{{ $reportSettings['updated_at'] ?? 'System default' }}</span></div>
                        </div>
                    </article>
                    <form method="POST" action="{{ route('leadership-reports.reminders') }}" class="dashboard-card">
                        @csrf
                        <h2 class="text-base font-semibold text-slate-950">Reminder Queue</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Queue reminders for draft, submitted, and under-review reports using the current policy.</p>
                        <button class="mt-4 w-full rounded-lg border border-violet-200 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">Queue Reminders</button>
                    </form>
                </aside>
            </section>
        @endif

        <section class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <article class="dashboard-card">
                <h2 class="mb-4 text-base font-semibold text-slate-950">Report Workflow Paths</h2>
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach([
                        ['Pastor / Department', 'Submit', 'Senior Pastor Review', 'Decision', 'Complete'],
                        ['Campus Pastor', 'Prepare', 'Submit to District', 'District Review', 'Complete'],
                    ] as $path)
                        <div>
                            <div class="mb-3 text-sm font-semibold text-slate-950">{{ $path[0] }}</div>
                            <div class="flex items-start gap-3 overflow-x-auto">
                                @foreach($path as $step)
                                    @continue($loop->first)
                                    <div class="min-w-24 text-center">
                                        <span class="mx-auto grid size-10 place-items-center rounded-full bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="{{ $loop->last ? 'check-circle-2' : 'send' }}" class="size-5"></i></span>
                                        <div class="mt-2 text-xs font-semibold text-slate-700">{{ $step }}</div>
                                    </div>
                                    @if(! $loop->last)<i data-lucide="arrow-right" class="mt-3 size-4 shrink-0 text-slate-400"></i>@endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
            <article class="dashboard-card">
                <h2 class="mb-4 text-base font-semibold text-slate-950">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="createOpen = true" class="rounded-lg border border-slate-200 p-4 text-center text-sm font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="file-text" class="mx-auto mb-2 size-7 text-violet-600"></i>Create New Report</button>
                    <a href="{{ route('leadership-reports.index', ['tab' => 'templates']) }}" class="rounded-lg border border-slate-200 p-4 text-center text-sm font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="send" class="mx-auto mb-2 size-7 text-blue-600"></i>Request Report</a>
                    <form method="POST" action="{{ route('leadership-reports.reminders') }}">@csrf<button class="w-full rounded-lg border border-slate-200 p-4 text-center text-sm font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="bell" class="mx-auto mb-2 size-7 text-orange-600"></i>Bulk Reminder</button></form>
                    <a href="{{ route('leadership-reports.export') }}" class="rounded-lg border border-slate-200 p-4 text-center text-sm font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="download" class="mx-auto mb-2 size-7 text-violet-600"></i>Export Reports</a>
                </div>
            </article>
        </section>

        <div x-cloak x-show="createOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
            <form method="POST" action="{{ route('leadership-reports.store') }}" class="max-h-[92vh] w-full max-w-7xl overflow-y-auto rounded-xl bg-white shadow-2xl">
                @csrf
                <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">New Leadership Report</h2>
                        <p class="mt-1 text-sm text-slate-500">Step <span x-text="createStep"></span> of 8</p>
                    </div>
                    <button type="button" @click="createOpen = false" class="self-start rounded-lg p-2 text-slate-500 hover:bg-slate-100 xl:self-auto" aria-label="Close"><i data-lucide="x" class="size-5"></i></button>
                </div>

                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="mb-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-violet-600 transition-all" :style="`width: ${(createStep / createTotal) * 100}%`"></div>
                    </div>
                    <div class="grid gap-2 text-xs font-semibold text-slate-500 md:grid-cols-4 xl:grid-cols-8">
                        @foreach([
                            1 => ['Week', 'calendar-days'],
                            2 => ['Scope', 'users-round'],
                            3 => ['Activities', 'list-checks'],
                            4 => ['Attendance', 'clipboard-check'],
                            5 => ['Service', 'hand-heart'],
                            6 => ['Issues', 'triangle-alert'],
                            7 => ['Files', 'paperclip'],
                            8 => ['Review', 'check-circle-2'],
                        ] as $step => [$label, $icon])
                            <button type="button" @click="createStep = {{ $step }}" class="flex items-center gap-2 rounded-lg border px-3 py-2 text-left transition" :class="createStep === {{ $step }} ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'">
                                <span class="grid size-7 place-items-center rounded-full" :class="createStep === {{ $step }} ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-500'"><i data-lucide="{{ $icon }}" class="size-3.5"></i></span>
                                <span>{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section class="min-h-[460px] rounded-xl border border-slate-200 p-5">
                        <div x-show="createStep === 1" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Week Selection</h3>
                                <p class="mt-1 text-sm text-slate-500">Choose the reporting window and title used by leadership reviewers.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="space-y-1 text-xs font-semibold text-slate-500 md:col-span-2">Report Title
                                    <input name="title" value="{{ old('title') }}" placeholder="East Campus Weekly Report" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">
                                    <span class="text-xs font-normal text-slate-400">Use a title that identifies the campus, ministry, and reporting period.</span>
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Period Start
                                    <input name="period_start" type="date" value="{{ old('period_start', now()->startOfWeek()->toDateString()) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Period End
                                    <input name="period_end" type="date" value="{{ old('period_end', now()->endOfWeek()->toDateString()) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <span class="text-xs font-normal text-slate-400">Weekly reports usually end on {{ Str::headline($reportSettings['weekly_due_day']) }}.</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="createStep === 2" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Scope & Review Path</h3>
                                <p class="mt-1 text-sm text-slate-500">Assign ownership, route the report, and set review urgency.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Report Type
                                    <select name="report_type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($types as $type)<option value="{{ $type }}" @selected(old('report_type') === $type)>{{ Str::headline($type) }}</option>@endforeach</select>
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Priority
                                    <select name="priority" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach(['normal', 'high', 'urgent', 'low'] as $priority)<option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ Str::headline($priority) }}</option>@endforeach</select>
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Campus
                                    <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Ministry
                                    <select name="ministry_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">General Leadership</option>@foreach($ministries as $ministry)<option value="{{ $ministry->id }}" @selected((string) old('ministry_id') === (string) $ministry->id)>{{ $ministry->name }}</option>@endforeach</select>
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500 md:col-span-2">Reviewer
                                    <select name="assigned_to" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Assign later</option>@foreach($reporters as $reporter)<option value="{{ $reporter->id }}" @selected((string) old('assigned_to', $reportSettings['default_reviewer_id']) === (string) $reporter->id)>{{ $reporter->name }} - {{ $reporter->title }}</option>@endforeach</select>
                                    <span class="text-xs font-normal text-slate-400">The reviewer receives the report and can approve, return, or reject it.</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="createStep === 3" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Activities & Achievements</h3>
                                <p class="mt-1 text-sm text-slate-500">Summarize what happened, what changed, and what leaders should know.</p>
                            </div>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Report Summary
                                <textarea name="summary" rows="10" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Summarize attendance movement, ministry wins, pastoral care matters, volunteer coverage, and decisions needed.">{{ old('summary') }}</textarea>
                            </label>
                        </div>

                        <div x-show="createStep === 4" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Attendance & Care Numbers</h3>
                                <p class="mt-1 text-sm text-slate-500">Capture the measurable health indicators used in leadership review.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Attendance Score
                                    <input name="attendance_score" type="number" min="0" max="100" value="{{ old('attendance_score', 90) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Care Follow-ups
                                    <input name="care_followups" type="number" min="0" value="{{ old('care_followups', 12) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </label>
                            </div>
                        </div>

                        <div x-show="createStep === 5" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Service Report</h3>
                                <p class="mt-1 text-sm text-slate-500">Record discipleship, serving coverage, and service-specific notes.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Discipleship Score
                                    <input name="discipleship_score" type="number" min="0" max="100" value="{{ old('discipleship_score', 88) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500">Volunteer Coverage
                                    <input name="volunteer_coverage" type="number" min="0" max="100" value="{{ old('volunteer_coverage', 82) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-500 md:col-span-2">Service Notes
                                    <textarea name="service_notes" rows="6" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Service flow, sermon response, worship, media, volunteer readiness, and service issues.">{{ old('service_notes') }}</textarea>
                                </label>
                            </div>
                        </div>

                        <div x-show="createStep === 6" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Challenges & Support</h3>
                                <p class="mt-1 text-sm text-slate-500">Capture blockers, risks, pastoral concerns, and leadership help needed.</p>
                            </div>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Issues Requiring Support
                                <textarea name="issues" rows="8" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="List the issues that need escalation, support, budget, people, or decisions.">{{ old('issues') }}</textarea>
                            </label>
                        </div>

                        <div x-show="createStep === 7" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Plans & Supporting Links</h3>
                                <p class="mt-1 text-sm text-slate-500">Add next steps and links to documents, folders, videos, or planning files.</p>
                            </div>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Plans & Suggestions
                                <textarea name="plans" rows="5" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Recommended next steps, ministry adjustments, decisions requested, and dates.">{{ old('plans') }}</textarea>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Supporting Links
                                <textarea name="supporting_links" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="One URL or document reference per line">{{ old('supporting_links') }}</textarea>
                            </label>
                        </div>

                        <div x-show="createStep === 8" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Review & Submit</h3>
                                <p class="mt-1 text-sm text-slate-500">Save the report as a draft or submit it to the selected reviewer.</p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-lg bg-violet-50 p-4 text-sm text-violet-800"><i data-lucide="shield-check" class="mb-2 size-5"></i>Submitted reports enter the leadership review queue immediately.</div>
                                <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800"><i data-lucide="save" class="mb-2 size-5"></i>Draft reports stay in the report queue until they are ready to submit.</div>
                            </div>
                        </div>
                    </section>

                    <aside class="rounded-xl border border-slate-200 p-5">
                        <h3 class="text-base font-semibold text-slate-950">Report Summary Preview</h3>
                        <p class="mt-1 text-sm text-slate-500">Completion follows the same review path used in the reports table.</p>
                        <div class="mt-5 space-y-3">
                            @foreach([
                                ['Week Selection', 'calendar-days'],
                                ['Scope & Reviewer', 'users-round'],
                                ['Activities', 'list-checks'],
                                ['Attendance', 'clipboard-check'],
                                ['Service', 'hand-heart'],
                                ['Issues', 'triangle-alert'],
                                ['Files & Plans', 'paperclip'],
                                ['Review & Submit', 'check-circle-2'],
                            ] as $index => [$label, $icon])
                                <div class="flex items-center gap-3 rounded-lg border border-slate-100 p-3">
                                    <span class="grid size-9 place-items-center rounded-lg" :class="createStep > {{ $index }} ? 'bg-emerald-50 text-emerald-600' : createStep === {{ $index + 1 }} ? 'bg-violet-50 text-violet-600' : 'bg-slate-50 text-slate-400'"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-slate-800">{{ $label }}</div>
                                        <div class="text-xs text-slate-500" x-text="createStep > {{ $index }} ? 'Ready' : createStep === {{ $index + 1 }} ? 'Current step' : 'Pending'"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </aside>
                </div>

                <div class="flex flex-col gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" @click="createOpen = false" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</button>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="button" x-show="createStep > 1" @click="prevStep()" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Back</button>
                        <button type="button" x-show="createStep < createTotal" @click="nextStep()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white">Continue <i data-lucide="arrow-right" class="size-4"></i></button>
                        <button x-show="createStep === createTotal" name="submit" value="0" class="rounded-lg border border-violet-200 px-4 py-2.5 text-sm font-semibold text-violet-700">Save Draft</button>
                        <button x-show="createStep === createTotal" name="submit" value="1" class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white">Submit Report</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
