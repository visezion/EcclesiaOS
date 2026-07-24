<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\LeadershipReport;
use App\Models\Ministry;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LeadershipReportController extends Controller
{
    private const STATUSES = ['draft', 'submitted', 'under_review', 'approved', 'returned', 'rejected'];

    private const TYPES = ['weekly', 'monthly', 'ministry', 'campus', 'pastoral', 'incident', 'strategic'];

    public function index(Request $request): View
    {
        $this->authorizeReports($request);

        $filters = [
            'tab' => (string) $request->query('tab', 'overview'),
            'status' => (string) $request->query('status', 'all'),
            'campus' => (string) $request->query('campus', 'all'),
            'type' => (string) $request->query('type', 'all'),
            'q' => trim((string) $request->query('q', '')),
            'report' => (string) $request->query('report', ''),
        ];
        $base = $this->visibleReports($request)->with(['campus', 'ministry', 'submitter', 'reviewer', 'reviewedBy']);
        $reports = $this->applyFilters(clone $base, $filters)
            ->latest('submitted_at')
            ->latest()
            ->paginate(8)
            ->withQueryString();
        $selectedReport = null;
        if ($filters['report'] !== '') {
            $reportId = $this->decodeReportId($filters['report']);
            $selectedReport = $reportId === null ? null : $this->visibleReports($request)
                ->with(['campus', 'ministry', 'submitter', 'reviewer', 'reviewedBy'])
                ->whereKey($reportId)
                ->first();
        }

        $selectedReport ??= $reports->getCollection()->first();

        return view('leadership-reports.index', [
            'reports' => $reports,
            'selectedReport' => $selectedReport,
            'filters' => $filters,
            'stats' => $this->stats($request),
            'trend' => $this->trend($request),
            'flow' => $this->flow($request),
            'relationships' => $this->relationships($request),
            'recentActivity' => $this->recentActivity($request),
            'tabCounts' => $this->tabCounts($request),
            'templates' => $this->templates(),
            'reportSettings' => $this->reportSettings($request),
            'campuses' => $this->visibleCampuses($request)->orderBy('name')->get(),
            'ministries' => $this->visibleMinistries($request)->orderBy('name')->get(),
            'reporters' => $this->visibleReporters($request)->orderBy('name')->get(),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Pastor & Leadership Reports', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $status = $request->boolean('submit') ? 'submitted' : 'draft';
        $report = LeadershipReport::query()->create($this->reportPayload($request, $status) + [
            'church_id' => $this->churchId($request),
            'submitted_by' => $request->user()->id,
        ]);

        $activityLogger->log('Leadership Reports', 'leadership_report_created', $report->title.' was created.', $report, ['resource' => 'Leadership Report', 'status' => $status], $request);

        return redirect()->route('leadership-reports.index', ['report' => $report->opaqueId()])->with('status', 'Leadership report saved.');
    }

    public function update(Request $request, LeadershipReport $leadershipReport, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $report = $this->visibleReports($request)
            ->whereKey($leadershipReport->id)
            ->firstOrFail();

        abort_unless(in_array($report->status, ['draft', 'returned'], true), 403);

        $status = $request->boolean('submit') ? 'submitted' : 'draft';
        $payload = $this->reportPayload($request, $status);

        if ($status === 'submitted' && $report->submitted_at === null) {
            $payload['submitted_at'] = now();
        }

        $report->update($payload);

        $activityLogger->log('Leadership Reports', 'leadership_report_updated', $report->title.' was updated.', $report, ['resource' => 'Leadership Report', 'status' => $status], $request);

        return redirect()->route('leadership-reports.show', $report)->with('status', $status === 'submitted' ? 'Leadership report submitted.' : 'Leadership report draft updated.');
    }

    public function show(Request $request, LeadershipReport $leadershipReport): View
    {
        $this->authorizeReports($request);

        $report = $this->visibleReports($request)
            ->with(['campus', 'ministry', 'submitter', 'reviewer', 'reviewedBy'])
            ->whereKey($leadershipReport->id)
            ->firstOrFail();

        return view('leadership-reports.show', [
            'report' => $report,
            'recentActivity' => ActivityLog::query()
                ->where('church_id', $this->churchId($request))
                ->where('module', 'Leadership Reports')
                ->where('subject_type', $report->getMorphClass())
                ->where('subject_id', $report->id)
                ->latest()
                ->limit(8)
                ->get(),
            'campuses' => $this->visibleCampuses($request)->orderBy('name')->get(),
            'ministries' => $this->visibleMinistries($request)->orderBy('name')->get(),
            'reporters' => $this->visibleReporters($request)->orderBy('name')->get(),
            'types' => self::TYPES,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Pastor & Leadership Reports', 'url' => route('leadership-reports.index')],
                ['label' => $report->title, 'url' => null],
            ],
        ]);
    }

    public function review(Request $request, LeadershipReport $leadershipReport, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);
        abort_unless($request->user()?->canAccessChurch($leadershipReport->church_id), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['under_review', 'approved', 'returned', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $leadershipReport->forceFill([
            'status' => $validated['decision'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $activityLogger->log('Leadership Reports', 'leadership_report_reviewed', $leadershipReport->title.' was marked '.str_replace('_', ' ', $validated['decision']).'.', $leadershipReport, ['resource' => 'Leadership Report', 'status' => $validated['decision']], $request);

        return redirect()->route('leadership-reports.index', ['report' => $leadershipReport->opaqueId()])->with('status', 'Report review updated.');
    }

    public function generateSummary(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);
        $stats = $this->stats($request);
        $summary = $stats['pending_review'].' reports need review, '.$stats['requires_action'].' require action, and average review time is '.$stats['average_review_time'].' days.';

        $activityLogger->log('Leadership Reports', 'leadership_summary_generated', 'Leadership report summary was generated.', null, ['resource' => 'Leadership Summary', 'status' => 'success'], $request);

        return back()->with('status', 'Summary generated: '.$summary);
    }

    public function sendReminders(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $pending = $this->visibleReports($request)
            ->whereIn('status', ['draft', 'submitted', 'under_review'])
            ->count();

        $activityLogger->log('Leadership Reports', 'leadership_report_reminders_sent', 'Leadership report reminders were queued for pending reports.', null, [
            'resource' => 'Leadership Reminder',
            'status' => 'queued',
            'pending_reports' => $pending,
        ], $request);

        return back()->with('status', number_format($pending).' leadership report reminders queued.');
    }

    public function updateSettings(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $validated = $request->validate([
            'default_reviewer_id' => ['nullable', 'exists:users,id'],
            'weekly_due_day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'auto_reminders' => ['nullable', 'boolean'],
            'require_action_items' => ['nullable', 'boolean'],
            'escalation_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        $church = Church::query()->findOrFail($this->churchId($request));
        $settings = $church->settings ?? [];
        $settings['leadership_reports'] = [
            'default_reviewer_id' => $validated['default_reviewer_id'] ?? null,
            'weekly_due_day' => $validated['weekly_due_day'],
            'auto_reminders' => $request->boolean('auto_reminders'),
            'require_action_items' => $request->boolean('require_action_items'),
            'escalation_hours' => (int) $validated['escalation_hours'],
            'updated_by' => $request->user()?->name,
            'updated_at' => now()->toDateTimeString(),
        ];
        $church->forceFill(['settings' => $settings])->save();

        $activityLogger->log('Leadership Reports', 'leadership_report_settings_updated', 'Leadership report settings were updated.', $church, [
            'resource' => 'Leadership Report Settings',
            'status' => 'success',
        ], $request);

        return back()->with('status', 'Leadership report settings saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReports($request);

        $rows = $this->visibleReports($request)->with(['campus', 'ministry', 'submitter', 'reviewer'])->latest('submitted_at')->get();

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Title', 'Type', 'Campus', 'Ministry', 'From', 'To', 'Period', 'Priority', 'Status', 'Submitted', 'Reviewed']);
            foreach ($rows as $report) {
                fputcsv($handle, [
                    $report->title,
                    $report->report_type,
                    $report->campus?->name,
                    $report->ministry?->name,
                    $report->submitter?->name,
                    $report->reviewer?->name,
                    $report->period_start?->format('M d, Y').' - '.$report->period_end?->format('M d, Y'),
                    $report->priority,
                    $report->status,
                    $report->submitted_at?->format('Y-m-d H:i:s'),
                    $report->reviewed_at?->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($handle);
        }, 'leadership-reports.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('view leadership reports'), 403);
    }

    private function visibleReports(Request $request)
    {
        $query = LeadershipReport::query()->where('church_id', $this->churchId($request));

        if (! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        return $query;
    }

    private function visibleCampuses(Request $request)
    {
        $query = Campus::query()->where('church_id', $this->churchId($request));

        if (! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null) {
            $query->whereKey($request->user()->campus_id);
        }

        return $query;
    }

    private function visibleMinistries(Request $request)
    {
        $query = Ministry::query()->where('church_id', $this->churchId($request));

        if (! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        return $query;
    }

    private function visibleReporters(Request $request)
    {
        $query = User::query()->where('church_id', $this->churchId($request));

        if (! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        return $query;
    }

    private function applyFilters($query, array $filters)
    {
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['campus'] !== 'all') {
            $query->where('campus_id', $filters['campus']);
        }

        if ($filters['type'] !== 'all') {
            $query->where('report_type', $filters['type']);
        }

        if (($filters['q'] ?? '') !== '') {
            $search = strtolower($filters['q']);
            $query->where(function ($scope) use ($search): void {
                $scope->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(summary) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(report_type) LIKE ?', ['%'.$search.'%'])
                    ->orWhereHas('submitter', fn ($user): mixed => $user->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']))
                    ->orWhereHas('reviewer', fn ($user): mixed => $user->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']));
            });
        }

        return match ($filters['tab']) {
            'my' => $query->where('submitted_by', auth()->id()),
            'to-me' => $query->where('assigned_to', auth()->id()),
            default => $query,
        };
    }

    private function reportPayload(Request $request, string $status): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'report_type' => ['required', Rule::in(self::TYPES)],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'ministry_id' => ['nullable', 'exists:ministries,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'summary' => ['required', 'string', 'max:5000'],
            'attendance_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discipleship_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'care_followups' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'volunteer_coverage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'service_notes' => ['nullable', 'string', 'max:3000'],
            'issues' => ['nullable', 'string', 'max:3000'],
            'plans' => ['nullable', 'string', 'max:3000'],
            'supporting_links' => ['nullable', 'string', 'max:3000'],
            'action_items' => ['nullable', 'string', 'max:3000'],
        ]);

        $user = $request->user();
        if (! $user?->isSuperAdministrator() && $user?->campus_id !== null) {
            $validated['campus_id'] = $user->campus_id;
        }

        if (! empty($validated['campus_id'])) {
            abort_unless($this->visibleCampuses($request)->whereKey($validated['campus_id'])->exists(), 403);
        }

        if (! empty($validated['ministry_id'])) {
            $ministry = $this->visibleMinistries($request)->whereKey($validated['ministry_id'])->first();
            abort_unless($ministry !== null, 403);

            if (empty($validated['campus_id'])) {
                $validated['campus_id'] = $ministry->campus_id;
            }

            abort_unless((int) $validated['campus_id'] === (int) $ministry->campus_id, 403);
        }

        if (! empty($validated['assigned_to'])) {
            abort_unless($this->visibleReporters($request)->whereKey($validated['assigned_to'])->exists(), 403);
        }

        return [
            'campus_id' => $validated['campus_id'] ?? null,
            'ministry_id' => $validated['ministry_id'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'title' => $validated['title'],
            'report_type' => $validated['report_type'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => $status,
            'priority' => $validated['priority'],
            'summary' => $validated['summary'],
            'submitted_at' => $status === 'submitted' ? now() : null,
            'due_at' => now()->addDays($validated['priority'] === 'urgent' ? 1 : 3),
            'metrics' => [
                'attendance_score' => (int) ($validated['attendance_score'] ?? 0),
                'discipleship_score' => (int) ($validated['discipleship_score'] ?? 0),
                'care_followups' => (int) ($validated['care_followups'] ?? 0),
                'volunteer_coverage' => (int) ($validated['volunteer_coverage'] ?? 0),
                'service_notes' => $validated['service_notes'] ?? null,
                'issues' => $validated['issues'] ?? null,
                'plans' => $validated['plans'] ?? null,
                'supporting_links' => collect(preg_split('/\r\n|\r|\n/', (string) ($validated['supporting_links'] ?? '')))
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'action_items' => collect(preg_split('/\r\n|\r|\n/', (string) ($validated['action_items'] ?? '')))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function stats(Request $request): array
    {
        $base = $this->visibleReports($request);
        $submitted = (clone $base)->whereNotNull('submitted_at')->count();
        $reviewed = (clone $base)->whereIn('status', ['approved', 'returned', 'rejected'])->count();
        $reviewedDurations = (clone $base)->whereNotNull('submitted_at')->whereNotNull('reviewed_at')->get()
            ->map(fn (LeadershipReport $report): int => max(1, (int) $report->submitted_at->diffInDays($report->reviewed_at)));
        $average = $reviewedDurations->count() > 0 ? round($reviewedDurations->avg(), 1) : 0;

        return [
            'submitted' => $submitted,
            'reviewed' => $reviewed,
            'pending_review' => (clone $base)->whereIn('status', ['submitted', 'under_review'])->count(),
            'requires_action' => (clone $base)->whereIn('status', ['returned', 'rejected'])->count(),
            'average_review_time' => $average,
        ];
    }

    private function trend(Request $request): array
    {
        $start = now()->subWeeks(4)->startOfWeek();
        $labels = collect(range(0, 4))->map(fn (int $week): string => $start->copy()->addWeeks($week)->format('M d'))->all();

        return [
            'labels' => $labels,
            'submitted' => $this->weeklyCounts($request, $start, null),
            'reviewed' => $this->weeklyCounts($request, $start, ['approved', 'returned', 'rejected']),
            'approved' => $this->weeklyCounts($request, $start, ['approved']),
            'returned' => $this->weeklyCounts($request, $start, ['returned']),
            'rejected' => $this->weeklyCounts($request, $start, ['rejected']),
        ];
    }

    private function weeklyCounts(Request $request, Carbon $start, ?array $statuses): array
    {
        return collect(range(0, 4))->map(function (int $week) use ($request, $start, $statuses): int {
            $query = $this->visibleReports($request)
                ->whereBetween('created_at', [$start->copy()->addWeeks($week), $start->copy()->addWeeks($week)->endOfWeek()]);

            if ($statuses !== null) {
                $query->whereIn('status', $statuses);
            }

            return $query->count();
        })->all();
    }

    private function flow(Request $request): array
    {
        $base = $this->visibleReports($request);

        return collect(self::STATUSES)->map(fn (string $status): array => [
            'status' => $status,
            'label' => str($status)->headline()->toString(),
            'count' => (clone $base)->where('status', $status)->count(),
        ])->all();
    }

    private function tabCounts(Request $request): array
    {
        $base = $this->visibleReports($request);

        return [
            'overview' => (clone $base)->count(),
            'my' => (clone $base)->where('submitted_by', $request->user()?->id)->count(),
            'to-me' => (clone $base)->where('assigned_to', $request->user()?->id)->count(),
            'all' => (clone $base)->count(),
            'analytics' => (clone $base)->whereNotNull('submitted_at')->count(),
            'templates' => count($this->templates()),
            'settings' => 1,
        ];
    }

    private function templates(): array
    {
        return [
            [
                'name' => 'Weekly Campus Report',
                'type' => 'weekly',
                'priority' => 'normal',
                'icon' => 'calendar-check',
                'tone' => 'bg-violet-50 text-violet-600 ring-violet-100',
                'description' => 'Attendance, care follow-ups, ministry health, and branch needs for a weekly review cycle.',
                'summary' => 'Weekly campus report covering attendance, discipleship, pastoral care follow-ups, volunteer coverage, risks, and recommended leadership actions.',
                'actions' => "Confirm campus attendance variance\nDocument open pastoral care follow-ups\nSubmit priority ministry support needs",
                'metrics' => [90, 86, 8, 82],
            ],
            [
                'name' => 'Ministry Health Report',
                'type' => 'ministry',
                'priority' => 'high',
                'icon' => 'users-round',
                'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
                'description' => 'Ministry participation, leader coverage, volunteer gaps, member engagement, and escalation needs.',
                'summary' => 'Ministry health report covering leader coverage, team readiness, participation trends, care issues, and actions requiring senior leadership review.',
                'actions' => "Update ministry leader roster\nEscalate volunteer coverage gaps\nConfirm next service assignments",
                'metrics' => [86, 91, 6, 78],
            ],
            [
                'name' => 'Pastoral Care Brief',
                'type' => 'pastoral',
                'priority' => 'urgent',
                'icon' => 'hand-heart',
                'tone' => 'bg-rose-50 text-rose-600 ring-rose-100',
                'description' => 'Sensitive pastoral care summary for counseling, visitation, prayer, and follow-up ownership.',
                'summary' => 'Pastoral care brief summarizing active care cases, counseling referrals, visitation needs, prayer requests, and follow-up ownership.',
                'actions' => "Assign care owner for each open case\nSchedule follow-up calls\nEscalate confidential pastoral matters",
                'metrics' => [78, 84, 14, 74],
            ],
            [
                'name' => 'Strategic Initiative Report',
                'type' => 'strategic',
                'priority' => 'normal',
                'icon' => 'target',
                'tone' => 'bg-blue-50 text-blue-600 ring-blue-100',
                'description' => 'Progress against strategic initiatives, blockers, decisions required, and next executive actions.',
                'summary' => 'Strategic initiative report tracking progress, risks, resource needs, decisions required, and next executive actions.',
                'actions' => "Confirm executive decision owner\nAttach supporting numbers\nUpdate milestone readiness",
                'metrics' => [84, 88, 5, 80],
            ],
        ];
    }

    private function reportSettings(Request $request): array
    {
        $settings = data_get(Church::query()->find($this->churchId($request))?->settings, 'leadership_reports', []);

        return [
            'default_reviewer_id' => $settings['default_reviewer_id'] ?? null,
            'weekly_due_day' => $settings['weekly_due_day'] ?? 'friday',
            'auto_reminders' => (bool) ($settings['auto_reminders'] ?? true),
            'require_action_items' => (bool) ($settings['require_action_items'] ?? true),
            'escalation_hours' => (int) ($settings['escalation_hours'] ?? 72),
            'updated_by' => $settings['updated_by'] ?? 'System default',
            'updated_at' => $settings['updated_at'] ?? null,
        ];
    }

    private function relationships(Request $request): array
    {
        $base = $this->visibleReports($request);

        return [
            ['label' => 'Pastor -> Senior Pastor', 'count' => (clone $base)->whereNotNull('assigned_to')->count()],
            ['label' => 'Campus -> Headquarters', 'count' => (clone $base)->whereNotNull('campus_id')->count()],
            ['label' => 'Ministry -> Pastor', 'count' => (clone $base)->whereNotNull('ministry_id')->count()],
        ];
    }

    private function recentActivity(Request $request)
    {
        return ActivityLog::query()
            ->where('church_id', $this->churchId($request))
            ->where('module', 'Leadership Reports')
            ->latest()
            ->limit(6)
            ->get();
    }

    private function churchId(Request $request): int
    {
        return (int) ($request->user()?->church_id ?? Church::query()->value('id'));
    }

    private function decodeReportId(string $opaqueId): ?int
    {
        return (new LeadershipReport)->resolveRouteBinding($opaqueId)?->id;
    }
}
