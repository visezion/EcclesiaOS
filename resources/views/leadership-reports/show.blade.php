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
        $canEditReport = in_array($report->status, ['draft', 'returned'], true);
        $actionItemsText = implode("\n", $report->action_items ?? []);
        $supportingLinksText = $supportingLinks->implode("\n");
        $fieldClass = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900';
        $textareaClass = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900';
        $hintClass = 'block text-xs font-normal leading-5 text-slate-400';
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
        $readiness = [
            ['label' => 'Reporting window', 'ready' => filled($report->period_start) && filled($report->period_end)],
            ['label' => 'Narrative summary', 'ready' => filled($report->summary)],
            ['label' => 'Reviewer assigned', 'ready' => filled($report->assigned_to)],
            ['label' => 'Action items', 'ready' => count($report->action_items ?? []) > 0],
        ];
        $readyCount = collect($readiness)->where('ready', true)->count();
    @endphp

    <div class="space-y-5">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="h-1.5 {{ $meta['bar'] }}"></div>
            <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0">
                    <a href="{{ route('leadership-reports.index', ['report' => $report->opaqueId()]) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-700">
                        <i data-lucide="arrow-left" class="size-4"></i>
                        Back to reports
                    </a>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 {{ $meta['class'] }}"><i data-lucide="{{ $meta['icon'] }}" class="size-4"></i>{{ $meta['label'] }}</span>
                        <span class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $priorityClass }}">{{ Str::headline($report->priority) }} Priority</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ Str::headline($report->report_type) }}</span>
                    </div>
                    <h1 class="mt-4 max-w-4xl text-2xl font-semibold leading-tight text-slate-950">{{ $report->title }}</h1>
                    <div class="mt-4 grid gap-3 text-sm text-slate-600 md:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase text-slate-400">Period</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $report->period_start?->format('M d') }} - {{ $report->period_end?->format('M d, Y') }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase text-slate-400">Scope</div>
                            <div class="mt-1 truncate font-semibold text-slate-950">{{ $report->campus?->name ?? 'All Campuses' }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase text-slate-400">Reviewer</div>
                            <div class="mt-1 truncate font-semibold text-slate-950">{{ $report->reviewer?->name ?? 'Unassigned' }}</div>
                        </div>
                    </div>
                </div>

                <aside class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase text-violet-500">Report Health</div>
                            <div class="mt-1 text-3xl font-bold text-slate-950">{{ $healthScore }}%</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $readyCount }} of {{ count($readiness) }} submission checks ready</div>
                        </div>
                        <div class="grid size-20 shrink-0 place-items-center rounded-full bg-white text-center shadow-sm ring-1 ring-violet-100">
                            <i data-lucide="activity" class="size-6 text-violet-600"></i>
                        </div>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-white">
                        <div class="h-full rounded-full bg-violet-600" style="width: {{ min(100, max(4, $healthScore)) }}%"></div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        @foreach($readiness as $item)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="grid size-6 place-items-center rounded-full {{ $item['ready'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' }}">
                                    <i data-lucide="{{ $item['ready'] ? 'check' : 'minus' }}" class="size-3.5"></i>
                                </span>
                                <span class="{{ $item['ready'] ? 'text-slate-700' : 'text-slate-500' }}">{{ $item['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </aside>
            </div>
        </section>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if(session('error') || $errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ session('error') ?? $errors->first() }}</div>
        @endif

        @if($canEditReport)
            <form method="POST" action="{{ route('leadership-reports.update', $report) }}" id="edit-report" class="grid gap-5 scroll-mt-24 xl:grid-cols-[minmax(0,1fr)_340px]">
                @csrf
                @method('PUT')

                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-violet-600">
                                    <i data-lucide="pencil" class="size-4"></i>
                                    Edit Draft
                                </div>
                                <h2 class="mt-2 text-lg font-semibold text-slate-950">Report workspace</h2>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button name="submit" value="0" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                                    <i data-lucide="save" class="size-4"></i>
                                    Save Draft
                                </button>
                                <button name="submit" value="1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                                    <i data-lucide="send" class="size-4"></i>
                                    Submit Report
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 p-5">
                        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <label class="space-y-1 text-xs font-semibold text-slate-500 xl:col-span-2">Report Title
                                <input name="title" value="{{ old('title', $report->title) }}" class="{{ $fieldClass }}">
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Period Start
                                <input name="period_start" type="date" value="{{ old('period_start', $report->period_start?->toDateString()) }}" class="{{ $fieldClass }}">
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Period End
                                <input name="period_end" type="date" value="{{ old('period_end', $report->period_end?->toDateString()) }}" class="{{ $fieldClass }}">
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Report Type
                                <select name="report_type" class="{{ $fieldClass }}">@foreach($types as $type)<option value="{{ $type }}" @selected(old('report_type', $report->report_type) === $type)>{{ Str::headline($type) }}</option>@endforeach</select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Priority
                                <select name="priority" class="{{ $fieldClass }}">@foreach(['normal', 'high', 'urgent', 'low'] as $priority)<option value="{{ $priority }}" @selected(old('priority', $report->priority) === $priority)>{{ Str::headline($priority) }}</option>@endforeach</select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Campus
                                <select name="campus_id" class="{{ $fieldClass }}"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('campus_id', $report->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Ministry
                                <select name="ministry_id" class="{{ $fieldClass }}"><option value="">General Leadership</option>@foreach($ministries as $ministry)<option value="{{ $ministry->id }}" @selected((string) old('ministry_id', $report->ministry_id) === (string) $ministry->id)>{{ $ministry->name }}</option>@endforeach</select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500 md:col-span-2">Reviewer
                                <select name="assigned_to" class="{{ $fieldClass }}"><option value="">Assign later</option>@foreach($reporters as $reporter)<option value="{{ $reporter->id }}" @selected((string) old('assigned_to', $report->assigned_to) === (string) $reporter->id)>{{ $reporter->name }} - {{ $reporter->title }}</option>@endforeach</select>
                            </label>
                        </section>

                        <section class="grid gap-4">
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Report Summary
                                <textarea name="summary" rows="8" class="{{ $textareaClass }}">{{ old('summary', $report->summary) }}</textarea>
                            </label>
                        </section>

                        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @foreach($metricCards as $card)
                                <label class="space-y-1 text-xs font-semibold text-slate-500">{{ $card['label'] }}
                                    <input name="{{ $card['key'] }}" type="number" min="0" max="{{ $card['key'] === 'care_followups' ? 100000 : 100 }}" value="{{ old($card['key'], $metrics->get($card['key'], 0)) }}" class="{{ $fieldClass }}">
                                </label>
                            @endforeach
                        </section>

                        <section class="grid gap-4 xl:grid-cols-2">
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Service Notes
                                <textarea name="service_notes" rows="5" class="{{ $textareaClass }}">{{ old('service_notes', $metrics->get('service_notes')) }}</textarea>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Issues Requiring Support
                                <textarea name="issues" rows="5" class="{{ $textareaClass }}">{{ old('issues', $metrics->get('issues')) }}</textarea>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Plans & Suggestions
                                <textarea name="plans" rows="5" class="{{ $textareaClass }}">{{ old('plans', $metrics->get('plans')) }}</textarea>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Supporting Links
                                <textarea name="supporting_links" rows="5" class="{{ $textareaClass }}">{{ old('supporting_links', $supportingLinksText) }}</textarea>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500 xl:col-span-2">Action Items
                                <textarea name="action_items" rows="5" class="{{ $textareaClass }}">{{ old('action_items', $actionItemsText) }}</textarea>
                            </label>
                        </section>
                    </div>
                </section>

                <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
                    <section class="dashboard-card">
                        <h2 class="text-base font-semibold text-slate-950">Submit Readiness</h2>
                        <div class="mt-4 space-y-3">
                            @foreach($readiness as $item)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                    <span class="text-slate-700">{{ $item['label'] }}</span>
                                    <span class="grid size-7 place-items-center rounded-full {{ $item['ready'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' }}"><i data-lucide="{{ $item['ready'] ? 'check' : 'minus' }}" class="size-4"></i></span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <button name="submit" value="1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                                <i data-lucide="send" class="size-4"></i>
                                Submit Report
                            </button>
                            <button name="submit" value="0" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                                <i data-lucide="save" class="size-4"></i>
                                Save Draft
                            </button>
                        </div>
                    </section>

                    <section class="dashboard-card">
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
                    </section>
                </aside>
            </form>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($metricCards as $card)
                @php($value = (int) $metrics->get($card['key'], 0))
                <article class="dashboard-card">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-11 place-items-center rounded-xl ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase text-slate-400">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-bold text-slate-950">{{ number_format($value) }}{{ $card['suffix'] }}</div>
                        </div>
                    </div>
                    <div class="mt-4 h-2 rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-violet-600" style="width: {{ min(100, max(4, $value)) }}%"></div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-5">
                <article class="dashboard-card p-0">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Report Detail</h2>
                            <p class="mt-1 text-sm text-slate-500">Leadership narrative, decision context, and follow-up items.</p>
                        </div>
                        <a href="{{ route('leadership-reports.export') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700">
                            <i data-lucide="download" class="size-4"></i>
                            Export
                        </a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($sections as $section)
                            @if(filled($section['body']))
                                <section class="grid gap-4 p-5 md:grid-cols-[44px_1fr]">
                                    <span class="grid size-11 place-items-center rounded-xl ring-1 {{ $section['tone'] }}"><i data-lucide="{{ $section['icon'] }}" class="size-5"></i></span>
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
                @if(in_array($report->status, ['submitted', 'under_review'], true))
                    <article class="dashboard-card">
                        <h2 class="mb-4 text-base font-semibold text-slate-950">Review Decision</h2>
                        <form method="POST" action="{{ route('leadership-reports.review', $report) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <textarea name="review_notes" rows="4" class="{{ $textareaClass }}" placeholder="Review notes, return reason, or final decision">{{ $report->review_notes }}</textarea>
                            <div class="grid grid-cols-2 gap-2">
                                <button name="decision" value="under_review" class="rounded-lg border border-violet-200 px-3 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">Review</button>
                                <button name="decision" value="approved" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                                <button name="decision" value="returned" class="rounded-lg border border-orange-200 px-3 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-50">Return</button>
                                <button name="decision" value="rejected" class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                            </div>
                        </form>
                    </article>
                @endif

                @unless($canEditReport)
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
                @endunless

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
