<x-app-layout title="{{ $report->title }}" :breadcrumbs="$breadcrumbs">
    @php
        $statusMeta = [
            'draft' => ['label' => 'Draft', 'icon' => 'clock', 'class' => 'bg-slate-100 text-slate-700 ring-slate-200', 'bar' => 'bg-slate-400'],
            'submitted' => ['label' => 'Submitted', 'icon' => 'send', 'class' => 'bg-blue-50 text-blue-700 ring-blue-100', 'bar' => 'bg-blue-500'],
            'under_review' => ['label' => 'Under Review', 'icon' => 'clock3', 'class' => 'bg-violet-50 text-violet-700 ring-violet-100', 'bar' => 'bg-violet-600'],
            'approved' => ['label' => 'Approved', 'icon' => 'check-circle-2', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'bar' => 'bg-emerald-500'],
            'returned' => ['label' => 'Returned', 'icon' => 'rotate-ccw', 'class' => 'bg-orange-50 text-orange-700 ring-orange-100', 'bar' => 'bg-orange-500'],
            'rejected' => ['label' => 'Rejected', 'icon' => 'triangle-alert', 'class' => 'bg-rose-50 text-rose-700 ring-rose-100', 'bar' => 'bg-rose-500'],
        ];
        $priorityClass = [
            'low' => 'bg-slate-100 text-slate-700',
            'normal' => 'bg-blue-50 text-blue-700',
            'high' => 'bg-orange-50 text-orange-700',
            'urgent' => 'bg-rose-50 text-rose-700',
        ][$report->priority] ?? 'bg-blue-50 text-blue-700';
        $meta = $statusMeta[$report->status] ?? $statusMeta['draft'];
        $metrics = collect($report->metrics ?? []);
        $metricCards = [
            ['label' => 'Attendance', 'key' => 'attendance_score', 'icon' => 'clipboard-check', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100', 'suffix' => '%'],
            ['label' => 'Discipleship', 'key' => 'discipleship_score', 'icon' => 'graduation-cap', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100', 'suffix' => '%'],
            ['label' => 'Care Follow-ups', 'key' => 'care_followups', 'icon' => 'hand-heart', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100', 'suffix' => ''],
            ['label' => 'Volunteer Coverage', 'key' => 'volunteer_coverage', 'icon' => 'users-round', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100', 'suffix' => '%'],
        ];
        $numericValues = collect($metricCards)->map(fn ($card) => (int) $metrics->get($card['key'], 0));
        $healthScore = $numericValues->count() > 0 ? round($numericValues->avg()) : 0;
        $supportingLinks = collect($metrics->get('supporting_links', []))->filter();
        $sections = [
            ['title' => 'Activities & Achievements', 'icon' => 'list-checks', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100', 'body' => $report->summary],
            ['title' => 'Service Report', 'icon' => 'hand-heart', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100', 'body' => $metrics->get('service_notes')],
            ['title' => 'Challenges & Support', 'icon' => 'triangle-alert', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100', 'body' => $metrics->get('issues')],
            ['title' => 'Plans & Suggestions', 'icon' => 'target', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100', 'body' => $metrics->get('plans')],
        ];
        $timeline = [
            ['label' => 'Created', 'value' => $report->created_at?->format('M d, Y h:i A'), 'icon' => 'file-text'],
            ['label' => 'Submitted', 'value' => $report->submitted_at?->format('M d, Y h:i A') ?? 'Draft not submitted', 'icon' => 'send'],
            ['label' => 'Due', 'value' => $report->due_at?->format('M d, Y h:i A') ?? 'No due date set', 'icon' => 'clock'],
            ['label' => 'Reviewed', 'value' => $report->reviewed_at?->format('M d, Y h:i A') ?? 'Pending review', 'icon' => 'check-circle-2'],
        ];
    @endphp

    <div class="space-y-5">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="h-1.5 {{ $meta['bar'] }}"></div>
            <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div>
                    <a href="{{ route('leadership-reports.index', ['report' => $report->opaqueId()]) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-violet-700"><i data-lucide="arrow-left" class="size-4"></i>Back to reports</a>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 {{ $meta['class'] }}"><i data-lucide="{{ $meta['icon'] }}" class="size-4"></i>{{ $meta['label'] }}</span>
                        <span class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $priorityClass }}">{{ Str::headline($report->priority) }} Priority</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ Str::headline($report->report_type) }}</span>
                    </div>
                    <h1 class="mt-4 max-w-4xl text-2xl font-semibold leading-tight text-slate-950">{{ $report->title }}</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-500">{{ $report->period_start?->format('M d, Y') }} - {{ $report->period_end?->format('M d, Y') }} leadership report for {{ $report->campus?->name ?? 'all campuses' }}.</p>
                </div>

                <aside class="rounded-xl border border-violet-100 bg-violet-50/50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-violet-500">Health Score</div>
                            <div class="mt-1 text-3xl font-bold text-slate-950">{{ $healthScore }}%</div>
                        </div>
                        <div class="relative grid size-20 place-items-center rounded-full bg-white text-center shadow-sm ring-1 ring-violet-100">
                            <div class="text-sm font-bold text-violet-700">{{ $healthScore }}</div>
                            <div class="text-[9px] font-bold uppercase text-slate-400">Score</div>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 text-sm">
                        <div class="flex justify-between gap-3"><span class="text-slate-500">From</span><span class="font-semibold text-slate-950">{{ $report->submitter?->name ?? 'Unknown' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-slate-500">Reviewer</span><span class="font-semibold text-slate-950">{{ $report->reviewer?->name ?? 'Unassigned' }}</span></div>
                    </div>
                </aside>
            </div>
        </section>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($metricCards as $card)
                @php($value = (int) $metrics->get($card['key'], 0))
                <article class="dashboard-card">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-11 place-items-center rounded-xl ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-bold text-slate-950">{{ number_format($value) }}{{ $card['suffix'] }}</div>
                        </div>
                    </div>
                    <div class="mt-4 h-2 rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-violet-600" style="width: {{ min(100, max(4, $value)) }}%"></div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
            <div class="space-y-5">
                <article class="dashboard-card p-0">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Report Detail</h2>
                            <p class="mt-1 text-sm text-slate-500">Leadership narrative, decision context, and follow-up items.</p>
                        </div>
                        <a href="{{ route('leadership-reports.export') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="download" class="size-4"></i>Export</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($sections as $section)
                            @if(filled($section['body']))
                                <section class="grid gap-4 p-5 md:grid-cols-[48px_1fr]">
                                    <span class="grid size-12 place-items-center rounded-xl ring-1 {{ $section['tone'] }}"><i data-lucide="{{ $section['icon'] }}" class="size-5"></i></span>
                                    <div>
                                        <h3 class="text-base font-semibold text-slate-950">{{ $section['title'] }}</h3>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $section['body'] }}</p>
                                    </div>
                                </section>
                            @endif
                        @endforeach
                    </div>
                </article>

                <article class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Action Items</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ count($report->action_items ?? []) }} items</span>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        @forelse($report->action_items ?? [] as $item)
                            <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50/50 p-3 text-sm text-slate-700">
                                <i data-lucide="check-circle-2" class="mt-0.5 size-4 shrink-0 text-emerald-600"></i>
                                <span>{{ $item }}</span>
                            </div>
                        @empty
                            <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No action items recorded.</div>
                        @endforelse
                    </div>
                </article>

                <article class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Supporting Links</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $supportingLinks->count() }} attached</span>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        @forelse($supportingLinks as $link)
                            @php($isUrl = filter_var($link, FILTER_VALIDATE_URL))
                            @if($isUrl)
                                <a href="{{ $link }}" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="paperclip" class="size-4"></i></span>
                                    <span class="truncate">{{ $link }}</span>
                                </a>
                            @else
                                <div class="flex min-w-0 items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-700">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-50 text-slate-400"><i data-lucide="paperclip" class="size-4"></i></span>
                                    <span class="truncate">{{ $link }}</span>
                                </div>
                            @endif
                        @empty
                            <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No supporting links attached.</div>
                        @endforelse
                    </div>
                </article>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
                <article class="dashboard-card">
                    <h2 class="mb-4 text-base font-semibold text-slate-950">Review Decision</h2>
                    <form method="POST" action="{{ route('leadership-reports.review', $report) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <textarea name="review_notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Review notes, return reason, or final decision">{{ $report->review_notes }}</textarea>
                        <div class="grid grid-cols-2 gap-2">
                            <button name="decision" value="under_review" class="rounded-lg border border-violet-200 px-3 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">Review</button>
                            <button name="decision" value="approved" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                            <button name="decision" value="returned" class="rounded-lg border border-orange-200 px-3 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-50">Return</button>
                            <button name="decision" value="rejected" class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                        </div>
                    </form>
                </article>

                <article class="dashboard-card">
                    <h2 class="mb-4 text-base font-semibold text-slate-950">Assignment</h2>
                    <div class="space-y-3 text-sm">
                        @foreach([
                            ['Submitter', $report->submitter?->name ?? 'Unknown', 'user-round'],
                            ['Reviewer', $report->reviewer?->name ?? 'Unassigned', 'user-check'],
                            ['Campus', $report->campus?->name ?? 'All Campuses', 'map-pin'],
                            ['Ministry', $report->ministry?->name ?? 'General Leadership', 'landmark'],
                        ] as [$label, $value, $icon])
                            <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3">
                                <span class="grid size-8 place-items-center rounded-lg bg-white text-violet-600 ring-1 ring-slate-100"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold uppercase text-slate-400">{{ $label }}</div>
                                    <div class="truncate font-semibold text-slate-950">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="dashboard-card">
                    <h2 class="mb-4 text-base font-semibold text-slate-950">Timeline</h2>
                    <div class="space-y-4">
                        @foreach($timeline as $item)
                            <div class="flex gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $item['icon'] }}" class="size-4"></i></span>
                                <div>
                                    <div class="text-sm font-semibold text-slate-950">{{ $item['label'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $item['value'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Audit Activity</h2>
                        <a href="{{ route('audit-logs.index') }}" class="text-xs font-semibold text-violet-700">View all</a>
                    </div>
                    <div class="space-y-3">
                        @forelse($recentActivity as $activity)
                            <div class="flex gap-3 text-sm">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="file-text" class="size-4"></i></span>
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $activity->description }}</div>
                                    <div class="text-xs text-slate-500">{{ $activity->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No report-specific activity yet.</div>
                        @endforelse
                    </div>
                </article>
            </aside>
        </section>
    </div>
</x-app-layout>
