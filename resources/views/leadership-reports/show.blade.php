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
        $attachments = collect($metrics->get('attachments', []))
            ->filter(fn ($attachment) => is_array($attachment))
            ->values()
            ->map(function (array $attachment, int $index) use ($report): array {
                $mimeType = (string) ($attachment['mime_type'] ?? 'application/octet-stream');
                $size = (int) ($attachment['size'] ?? 0);

                return array_merge($attachment, [
                    'mime_type' => $mimeType,
                    'size_label' => $size >= 1048576
                        ? number_format($size / 1048576, 1).' MB'
                        : number_format(max(1, $size) / 1024, 1).' KB',
                    'url' => route('leadership-reports.attachments.view', [$report, $index]),
                    'is_image' => Str::startsWith($mimeType, 'image/'),
                    'is_pdf' => $mimeType === 'application/pdf',
                    'is_text' => Str::startsWith($mimeType, 'text/') || in_array($mimeType, ['application/csv', 'application/json'], true),
                    'icon' => Str::startsWith($mimeType, 'image/') ? 'image' : ($mimeType === 'application/pdf' ? 'file-text' : 'paperclip'),
                ]);
            });
        $canEditReport = in_array($report->status, ['draft', 'returned'], true);
        $canDeleteReport = $report->status === 'draft' && (int) $report->submitted_by === (int) auth()->id();
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
        $canSaveAsTemplate = (int) $report->submitted_by === (int) auth()->id();
        $templateDefaultName = trim(Str::before($report->title, ' - ')) ?: $report->title;
    @endphp

    <div class="space-y-5" x-data="{ saveTemplateOpen: @js($errors->saveTemplate->any()) }" @keydown.escape.window="saveTemplateOpen = false">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="h-1.5 {{ $meta['bar'] }}"></div>
            <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <a href="{{ route('leadership-reports.index', ['report' => $report->opaqueId()]) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-700">
                            <i data-lucide="arrow-left" class="size-4"></i>
                            Back to reports
                        </a>
                        <div class="flex flex-wrap items-center gap-2">
                            @if($canSaveAsTemplate)
                                <button type="button" @click="saveTemplateOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-3 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                                    <i data-lucide="bookmark-plus" class="size-4"></i>
                                    Save as template
                                </button>
                            @endif
                            @if($canDeleteReport)
                                <form method="POST" action="{{ route('leadership-reports.destroy', $report) }}" onsubmit="return confirm('Delete this draft report?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                        <i data-lucide="trash-2" class="size-4"></i>
                                        Delete Draft
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
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

        @if($canSaveAsTemplate)
            <div x-cloak x-show="saveTemplateOpen" x-transition.opacity class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4" role="dialog" aria-modal="true" aria-labelledby="save-template-title">
                <form method="POST" action="{{ route('leadership-reports.templates.store', $report) }}" @click.outside="saveTemplateOpen = false" class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                    @csrf
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="bookmark-plus" class="size-5"></i></span>
                            <div>
                                <h2 id="save-template-title" class="text-base font-semibold text-slate-950">Save as personal template</h2>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Only you can view, use, or delete this reusable template.</p>
                            </div>
                        </div>
                        <button type="button" @click="saveTemplateOpen = false" class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close save template dialog"><i data-lucide="x" class="size-5"></i></button>
                    </div>

                    <div class="space-y-4 p-5">
                        @if($errors->saveTemplate->any())
                            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->saveTemplate->first() }}</div>
                        @endif
                        <label class="block space-y-1.5 text-xs font-semibold text-slate-600">
                            Template name
                            <input name="template_name" value="{{ old('template_name', $templateDefaultName) }}" maxlength="180" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-900" placeholder="Monthly department leadership report">
                            <span class="block font-normal leading-5 text-slate-400">Use a reusable name without a month or date.</span>
                        </label>
                        <label class="block space-y-1.5 text-xs font-semibold text-slate-600">
                            Description <span class="font-normal text-slate-400">optional</span>
                            <textarea name="template_description" rows="3" maxlength="500" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900" placeholder="When and how this report template should be used.">{{ old('template_description') }}</textarea>
                        </label>
                        <div class="rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-500">
                            <div class="flex items-start gap-2"><i data-lucide="shield-check" class="mt-0.5 size-4 shrink-0 text-emerald-600"></i><span>The report structure, narrative, indicators, scope, and action items will be saved. Uploaded files and old attendance-source links will not be copied.</span></div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
                        <button type="button" @click="saveTemplateOpen = false" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</button>
                        <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="bookmark-check" class="size-4"></i>Save personal template</button>
                    </div>
                </form>
            </div>
        @endif

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if(session('error') || $errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ session('error') ?? $errors->first() }}</div>
        @endif

        @if($canEditReport)
            <form
                method="POST"
                action="{{ route('leadership-reports.update', $report) }}"
                enctype="multipart/form-data"
                id="edit-report"
                x-data="leadershipReportAttachments({
                    max_files: 8,
                    existing_files: @js($attachments->map(fn ($attachment, $index) => [
                        'id' => 'existing-'.$index,
                        'name' => $attachment['original_name'] ?? 'Report attachment',
                        'size' => (int) ($attachment['size'] ?? 0),
                        'sizeLabel' => $attachment['size_label'],
                        'type' => $attachment['mime_type'],
                        'url' => $attachment['url'],
                        'isImage' => $attachment['is_image'],
                        'isPdf' => $attachment['is_pdf'],
                        'isText' => $attachment['is_text'],
                        'icon' => $attachment['icon'],
                        'existing' => true,
                    ])->values()->all()),
                })"
                @keydown.escape.window="closePreview()"
                class="grid gap-5 scroll-mt-28 xl:grid-cols-[minmax(0,1fr)_380px]"
            >
                @csrf
                @method('PUT')

                <section class="dashboard-card overflow-hidden p-0">
                    <div class="relative z-10 border-b border-violet-100 bg-white px-5 py-4 shadow-sm">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-violet-600">
                                    <i data-lucide="pencil" class="size-4"></i>
                                    Edit Draft
                                </div>
                                <h2 class="mt-2 text-xl font-semibold text-slate-950">Report editing workspace</h2>
                                <p class="mt-1 text-sm text-slate-500">Complete each section, review supporting evidence, then save or submit for leadership review.</p>
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
                        <section class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="flex items-start gap-3 md:col-span-2 xl:col-span-4">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="settings-2" class="size-5"></i></span>
                                <div><h3 class="text-base font-semibold text-slate-950">1. Report identity & routing</h3><p class="mt-1 text-xs leading-5 text-slate-500">Confirm the reporting window, report classification, ministry scope, and accountable reviewer.</p></div>
                            </div>
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
                            <x-searchable-select
                                name="campus_id"
                                label="Campus"
                                empty-label="All Campuses"
                                placeholder="Search campuses"
                                :selected="$report->campus_id"
                                :options="$campuses->map(fn ($campus) => [
                                    'value' => $campus->id,
                                    'label' => $campus->name,
                                    'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
                                    'initials' => Str::substr($campus->name, 0, 2),
                                ])->values()"
                                class="text-xs font-semibold text-slate-500"
                            />
                            <x-searchable-select
                                name="ministry_id"
                                label="Ministry"
                                empty-label="General Leadership"
                                placeholder="Search ministries"
                                :selected="$report->ministry_id"
                                :options="$ministries->map(fn ($ministry) => [
                                    'value' => $ministry->id,
                                    'label' => $ministry->name,
                                    'meta' => trim(($ministry->campus?->name ?? 'All campuses').' - '.($ministry->leader?->first_name ? trim($ministry->leader->first_name.' '.$ministry->leader->last_name) : 'No leader')),
                                    'initials' => Str::substr($ministry->name, 0, 2),
                                ])->values()"
                                class="text-xs font-semibold text-slate-500"
                            />
                            <x-searchable-select
                                name="assigned_to"
                                label="Reviewer"
                                empty-label="Assign later"
                                placeholder="Search reviewer by name, title, or email"
                                :selected="$report->assigned_to"
                                :options="$reporters->map(fn ($reporter) => [
                                    'value' => $reporter->id,
                                    'label' => $reporter->name,
                                    'meta' => trim(($reporter->title ?: 'Reviewer').' - '.($reporter->email ?: 'No email')),
                                    'avatar' => $reporter->avatar_src,
                                    'initials' => Str::of($reporter->name)->explode(' ')->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join(''),
                                ])->values()"
                                class="text-xs font-semibold text-slate-500 md:col-span-2"
                            />
                        </section>

                        <section class="grid gap-4 rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-100"><i data-lucide="list-checks" class="size-5"></i></span>
                                <div><h3 class="text-base font-semibold text-slate-950">2. Executive overview & achievements</h3><p class="mt-1 text-xs leading-5 text-slate-500">Give leaders a concise, evidence-based picture of outcomes, movement, concerns, and decisions required.</p></div>
                            </div>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">Report Summary
                                <textarea name="summary" rows="12" class="{{ $textareaClass }} leading-6">{{ old('summary', $report->summary) }}</textarea>
                                <span class="{{ $hintClass }}">Include verified results, key achievements, meaningful variances, and leadership decisions—not activity alone.</span>
                            </label>
                        </section>

                        <section class="grid gap-4 rounded-xl border border-slate-200 bg-gradient-to-br from-white to-violet-50/40 p-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="flex items-start gap-3 md:col-span-2 xl:col-span-4">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><i data-lucide="activity" class="size-5"></i></span>
                                <div><h3 class="text-base font-semibold text-slate-950">3. Ministry health indicators</h3><p class="mt-1 text-xs leading-5 text-slate-500">Enter verified scores or totals. Values remain editable until the report is submitted.</p></div>
                            </div>
                            @foreach($metricCards as $card)
                                <label class="rounded-lg border border-white bg-white p-3 text-xs font-semibold text-slate-500 shadow-sm ring-1 ring-slate-100">
                                    <span class="mb-2 flex items-center gap-2"><span class="grid size-7 place-items-center rounded-lg {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-3.5"></i></span>{{ $card['label'] }}</span>
                                    <input name="{{ $card['key'] }}" type="number" min="0" max="{{ $card['key'] === 'care_followups' ? 100000 : 100 }}" value="{{ old($card['key'], $metrics->get($card['key'], 0)) }}" class="{{ $fieldClass }}">
                                </label>
                            @endforeach
                        </section>

                        <section class="grid gap-4 rounded-xl border border-slate-200 p-4 xl:grid-cols-2">
                            <div class="flex items-start gap-3 xl:col-span-2">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-orange-50 text-orange-600 ring-1 ring-orange-100"><i data-lucide="clipboard-list" class="size-5"></i></span>
                                <div><h3 class="text-base font-semibold text-slate-950">4. Delivery, challenges & forward plan</h3><p class="mt-1 text-xs leading-5 text-slate-500">Capture service delivery, blockers, recommendations, evidence links, and accountable next actions.</p></div>
                            </div>
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
                                <textarea name="action_items" rows="7" class="{{ $textareaClass }} leading-6">{{ old('action_items', $actionItemsText) }}</textarea>
                                <span class="{{ $hintClass }}">Use one action per line and include the owner, due date, and expected outcome.</span>
                            </label>
                        </section>

                        <section class="overflow-hidden rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50/70 via-white to-blue-50/60">
                            <div class="border-b border-violet-100 p-4 sm:p-5">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-600 text-white shadow-sm"><i data-lucide="paperclip" class="size-5"></i></span>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-base font-semibold text-slate-950">5. Supporting files & in-page review</h3>
                                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-violet-700 ring-1 ring-violet-100"><span x-text="existingFiles.length + newFiles.length"></span> / <span x-text="maxFiles"></span> files</span>
                                            </div>
                                            <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">Review images, PDFs, text, and CSV files without leaving the editor. Office files remain available to open or download securely.</p>
                                        </div>
                                    </div>
                                    <label class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700" :class="remainingSlots() === 0 ? 'pointer-events-none opacity-50' : ''">
                                        <i data-lucide="upload" class="size-4"></i>Add supporting files
                                        <input x-ref="editAttachmentInput" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.webp" class="sr-only" @change="addFiles($event)">
                                    </label>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
                                    <div class="h-2 overflow-hidden rounded-full bg-white ring-1 ring-violet-100"><div class="h-full rounded-full bg-violet-600 transition-all" :style="`width: ${Math.min(100, ((existingFiles.length + newFiles.length) / maxFiles) * 100)}%`"></div></div>
                                    <div class="text-xs font-semibold text-slate-500"><span x-text="remainingSlots()"></span> upload slot<span x-show="remainingSlots() !== 1">s</span> remaining</div>
                                </div>
                                <div x-cloak x-show="fileError" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" x-text="fileError"></div>
                            </div>

                            <div class="space-y-5 p-4 sm:p-5">
                                <div x-show="existingFiles.length > 0">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <div><h4 class="text-sm font-semibold text-slate-900">Attached evidence</h4><p class="mt-0.5 text-xs text-slate-500">Secure files already saved with this report.</p></div>
                                        <span class="text-xs font-semibold text-slate-400" x-text="existingFiles.length + ' saved'"></span>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <template x-for="file in existingFiles" :key="file.id">
                                            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                                <template x-if="file.isImage"><img :src="file.url" :alt="file.name" class="h-32 w-full bg-slate-100 object-cover"></template>
                                                <div class="p-3.5">
                                                    <div class="flex items-start gap-3">
                                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i x-bind:data-lucide="file.icon" class="size-4"></i></span>
                                                        <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold text-slate-900" x-text="file.name"></div><div class="mt-1 truncate text-xs text-slate-500"><span x-text="file.sizeLabel"></span> · <span x-text="file.type || 'Document'"></span></div></div>
                                                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase text-emerald-700">Saved</span>
                                                    </div>
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        <button type="button" @click="reviewFile(file)" class="inline-flex items-center gap-1.5 rounded-md bg-violet-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"><i data-lucide="eye" class="size-3.5"></i>Review here</button>
                                                        <a :href="file.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"><i data-lucide="external-link" class="size-3.5"></i>Open file</a>
                                                    </div>
                                                </div>
                                            </article>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="newFiles.length > 0">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <div><h4 class="text-sm font-semibold text-slate-900">Ready to upload</h4><p class="mt-0.5 text-xs text-slate-500">Review selected files before saving this draft.</p></div>
                                        <span class="text-xs font-semibold text-violet-600" x-text="newFiles.length + ' selected'"></span>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <template x-for="(file, index) in newFiles" :key="file.id">
                                            <article class="overflow-hidden rounded-xl border border-violet-200 bg-white shadow-sm">
                                                <template x-if="file.isImage"><img :src="file.url" :alt="file.name" class="h-32 w-full bg-slate-100 object-cover"></template>
                                                <div class="p-3.5">
                                                    <div class="flex items-start gap-3">
                                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600"><i x-bind:data-lucide="file.icon" class="size-4"></i></span>
                                                        <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold text-slate-900" x-text="file.name"></div><div class="mt-1 truncate text-xs text-slate-500"><span x-text="file.sizeLabel"></span> · <span x-text="file.type || 'Document'"></span></div></div>
                                                        <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold uppercase text-blue-700">New</span>
                                                    </div>
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        <button type="button" @click="reviewFile(file)" class="inline-flex items-center gap-1.5 rounded-md bg-violet-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"><i data-lucide="eye" class="size-3.5"></i>Review here</button>
                                                        <button type="button" @click="removeFile(index)" class="inline-flex items-center gap-1.5 rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"><i data-lucide="x" class="size-3.5"></i>Remove</button>
                                                    </div>
                                                </div>
                                            </article>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="existingFiles.length === 0 && newFiles.length === 0" class="rounded-xl border-2 border-dashed border-violet-200 bg-white/70 px-5 py-10 text-center">
                                    <span class="mx-auto grid size-12 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="file-up" class="size-6"></i></span>
                                    <h4 class="mt-3 text-sm font-semibold text-slate-900">Add evidence to strengthen this report</h4>
                                    <p class="mx-auto mt-1 max-w-lg text-xs leading-5 text-slate-500">Upload attendance exports, approved finance summaries, photos, reports, plans, spreadsheets, presentations, or other supporting evidence.</p>
                                </div>
                            </div>
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

                <div x-cloak x-show="previewOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-3 sm:p-6" @click.self="closePreview()">
                    <section x-show="previewOpen" x-transition class="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-white/20" role="dialog" aria-modal="true" aria-label="Supporting file review">
                        <header class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="scan-search" class="size-5"></i></span>
                                <div class="min-w-0">
                                    <div class="text-[11px] font-bold uppercase tracking-wider text-violet-600">Supporting file review</div>
                                    <h3 class="truncate text-base font-semibold text-slate-950" x-text="activeFile?.name || 'File preview'"></h3>
                                    <p class="mt-0.5 truncate text-xs text-slate-500"><span x-text="activeFile?.sizeLabel || ''"></span><span x-show="activeFile?.type"> · </span><span x-text="activeFile?.type || ''"></span></p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <a x-show="activeFile?.url" :href="activeFile?.url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="external-link" class="size-4"></i>Open separately</a>
                                <button type="button" @click="closePreview()" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-900" aria-label="Close file preview"><i data-lucide="x" class="size-4"></i></button>
                            </div>
                        </header>

                        <div class="min-h-0 flex-1 overflow-auto bg-slate-100 p-3 sm:p-5">
                            <template x-if="activeFile?.isImage">
                                <div class="grid min-h-[55vh] place-items-center rounded-xl bg-slate-950 p-3"><img :src="activeFile.url" :alt="activeFile.name" class="max-h-[72vh] max-w-full rounded-lg object-contain shadow-2xl"></div>
                            </template>
                            <template x-if="activeFile?.isPdf || activeFile?.isText">
                                <iframe :src="activeFile.url" :title="activeFile.name" class="h-[72vh] w-full rounded-xl border border-slate-200 bg-white shadow-sm"></iframe>
                            </template>
                            <template x-if="activeFile && !activeFile.isImage && !activeFile.isPdf && !activeFile.isText">
                                <div class="grid min-h-[55vh] place-items-center rounded-xl border border-slate-200 bg-white p-6 text-center">
                                    <div>
                                        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="file-text" class="size-8"></i></span>
                                        <h4 class="mt-4 text-lg font-semibold text-slate-950">Preview is not available for this file type</h4>
                                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Microsoft Office and other specialist document formats must be opened in their associated application. The file remains securely attached to this report.</p>
                                        <a :href="activeFile.url" target="_blank" rel="noopener" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="external-link" class="size-4"></i>Open file</a>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <footer class="flex flex-col gap-2 border-t border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <span class="inline-flex items-center gap-2"><i data-lucide="shield-check" class="size-4 text-emerald-600"></i>Private report evidence · access follows Leadership Reports permissions.</span>
                            <span>Press Esc or use Close to return to editing.</span>
                        </footer>
                    </section>
                </div>
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

        @if($metrics->get('attendance_source') === 'recorded')
            <section class="dashboard-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Recorded Attendance Source</h2>
                        <p class="mt-1 text-sm text-slate-500">Attendance score was calculated from selected event attendance sessions in this report period.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm sm:min-w-[260px]">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-xs font-semibold uppercase text-slate-400">Present</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ number_format((int) $metrics->get('attendance_total')) }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-xs font-semibold uppercase text-slate-400">Expected</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ ((int) $metrics->get('attendance_expected')) > 0 ? number_format((int) $metrics->get('attendance_expected')) : 'No target' }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid gap-2">
                    @foreach(collect($metrics->get('attendance_sessions', [])) as $source)
                        <div class="grid gap-2 rounded-lg border border-slate-200 p-3 text-sm sm:grid-cols-[1fr_auto] sm:items-center">
                            <div>
                                <div class="font-semibold text-slate-950">{{ $source['title'] ?? 'Attendance Session' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ filled($source['date'] ?? null) ? \Illuminate\Support\Carbon::parse($source['date'])->format('M d, Y') : 'Date not recorded' }}</div>
                            </div>
                            <div class="text-xs text-slate-500 sm:text-right">
                                <span class="font-semibold text-slate-900">{{ number_format((int) ($source['present'] ?? 0)) }}</span> present
                                @if((int) ($source['expected'] ?? 0) > 0)
                                    of <span class="font-semibold text-slate-900">{{ number_format((int) $source['expected']) }}</span> expected
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

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
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Supporting Files</h2>
                            <p class="mt-1 text-sm text-slate-500">Open uploaded evidence directly while reviewing this report.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $attachments->count() }} files</span>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        @forelse($attachments as $attachmentIndex => $attachment)
                            <article class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                @if($attachment['is_image'])
                                    <img src="{{ $attachment['url'] }}" alt="{{ $attachment['original_name'] ?? 'Report attachment' }}" loading="lazy" class="h-36 w-full bg-slate-100 object-cover">
                                @endif
                                <div class="flex items-start gap-3 p-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $attachment['icon'] }}" class="size-4"></i></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-slate-900">{{ $attachment['original_name'] ?? 'Report attachment' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $attachment['size_label'] }} · {{ $attachment['mime_type'] }}</div>
                                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-violet-50 px-2.5 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100"><i data-lucide="eye" class="size-3.5"></i>Review file</a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500 md:col-span-2">No supporting files uploaded.</div>
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
                @if($canReviewLeadershipReports && in_array($report->status, ['submitted', 'under_review'], true))
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
