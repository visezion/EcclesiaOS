<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\Church;
use App\Models\LeadershipReport;
use App\Models\LeadershipReportTemplate;
use App\Models\Ministry;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Communications\ZenderWhatsAppNotifier;
use App\Support\Csv;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LeadershipReportController extends Controller
{
    private const STATUSES = ['draft', 'submitted', 'under_review', 'approved', 'returned', 'rejected'];

    private const TYPES = ['weekly', 'monthly', 'ministry', 'campus', 'pastoral', 'incident', 'strategic'];

    public function index(Request $request): View
    {
        $this->authorizeReports($request);

        $canViewAllLeadershipReports = $this->canViewAllLeadershipReports($request);
        $filters = [
            'tab' => (string) $request->query('tab', 'overview'),
            'status' => (string) $request->query('status', 'all'),
            'campus' => (string) $request->query('campus', 'all'),
            'type' => (string) $request->query('type', 'all'),
            'q' => trim((string) $request->query('q', '')),
            'report' => (string) $request->query('report', ''),
        ];

        if ($filters['tab'] === 'all' && ! $canViewAllLeadershipReports) {
            $filters['tab'] = 'overview';
        }

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

        $reportSettings = $this->reportSettings($request);
        $reporters = $this->visibleReporters($request)->orderBy('name')->get();
        if ($reportSettings['default_reviewer_id'] !== null && ! $reporters->contains('id', $reportSettings['default_reviewer_id'])) {
            $reportSettings['default_reviewer_id'] = null;
        }

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
            'canViewAllLeadershipReports' => $canViewAllLeadershipReports,
            'canReviewLeadershipReports' => $this->canReviewLeadershipReports($request),
            'canManageReviewerRoles' => $this->canManageReviewerRoles($request),
            'templates' => $this->templates(),
            'personalTemplates' => $this->personalTemplates($request)
                ->with(['campus', 'ministry'])
                ->latest()
                ->get(),
            'reportSettings' => $reportSettings,
            'campuses' => $this->visibleCampuses($request)->orderBy('name')->get(),
            'ministries' => $this->visibleMinistries($request)->orderBy('name')->get(),
            'reporters' => $reporters,
            'reviewerRoles' => Role::query()
                ->withCount(['users as eligible_users_count' => fn ($query) => $query->where('church_id', $this->churchId($request))])
                ->orderBy('name')
                ->get(),
            'attendanceSources' => $this->attendanceSources($request),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Pastor & Leadership Reports', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger, ZenderWhatsAppNotifier $notifier): RedirectResponse
    {
        $this->authorizeReports($request);

        $status = $request->boolean('submit') ? 'submitted' : 'draft';
        $payload = $this->reportPayload($request, $status);

        if ($request->boolean('save_as_template')) {
            $templateFields = $request->validate([
                'personal_template_name' => ['nullable', 'string', 'max:180'],
                'personal_template_description' => ['nullable', 'string', 'max:500'],
            ]);
            $templateName = trim((string) ($templateFields['personal_template_name'] ?? ''));
            if ($templateName === '') {
                $templateName = trim(Str::before($payload['title'], ' - ')) ?: $payload['title'];
            }

            $template = LeadershipReportTemplate::query()->create([
                'church_id' => $this->churchId($request),
                'user_id' => $request->user()->id,
                'campus_id' => $payload['campus_id'],
                'ministry_id' => $payload['ministry_id'],
                'assigned_to' => $payload['assigned_to'],
                'name' => $templateName,
                'description' => trim((string) ($templateFields['personal_template_description'] ?? '')) ?: null,
                'report_type' => $payload['report_type'],
                'priority' => $payload['priority'],
                'summary' => $payload['summary'],
                'metrics' => $this->reusableTemplateMetrics($payload['metrics']),
                'action_items' => $payload['action_items'],
            ]);

            $activityLogger->log('Leadership Reports', 'leadership_report_template_created', $template->name.' was saved from the new report workspace.', $template, [
                'resource' => 'Leadership Report Template',
                'status' => 'private',
                'source' => 'new_report',
            ], $request);

            return redirect()
                ->route('leadership-reports.index', ['tab' => 'templates'])
                ->with('status', 'New report saved as a personal template. No report was created.');
        }

        $report = LeadershipReport::query()->create($payload + [
            'church_id' => $this->churchId($request),
            'submitted_by' => $request->user()->id,
        ]);
        $this->storeAttachments($request, $report);

        $activityLogger->log('Leadership Reports', 'leadership_report_created', $report->title.' was created.', $report, ['resource' => 'Leadership Report', 'status' => $status], $request);
        $notifier->notify(
            (int) $report->church_id,
            "Leadership report update: {$report->title} was created.\n\nType: {$report->report_type}\nStatus: {$report->status}\nPriority: {$report->priority}",
            'LeadershipReportCreated',
            (int) $report->campus_id,
            $report->ministry_id !== null ? (int) $report->ministry_id : null,
            "Leadership report created: {$report->title}",
        );

        if ($status === 'draft' && $request->boolean('from_template')) {
            return redirect()
                ->to(route('leadership-reports.show', $report).'#edit-report')
                ->with('status', 'Template draft created. Review and edit it before submitting.');
        }

        return redirect()->route('leadership-reports.index', ['report' => $report->opaqueId()])->with('status', 'Leadership report saved.');
    }

    public function update(Request $request, LeadershipReport $leadershipReport, ActivityLogger $activityLogger, ZenderWhatsAppNotifier $notifier): RedirectResponse
    {
        $this->authorizeReports($request);

        $report = $this->visibleReports($request)
            ->whereKey($leadershipReport->id)
            ->firstOrFail();

        abort_unless(in_array($report->status, ['draft', 'returned'], true), 403);

        $status = $request->boolean('submit') ? 'submitted' : 'draft';
        $payload = $this->reportPayload($request, $status, $report);

        if ($status === 'submitted' && $report->submitted_at === null) {
            $payload['submitted_at'] = now();
        }

        $report->update($payload);
        $this->storeAttachments($request, $report);

        $activityLogger->log('Leadership Reports', 'leadership_report_updated', $report->title.' was updated.', $report, ['resource' => 'Leadership Report', 'status' => $status], $request);
        $notifier->notify(
            (int) $report->church_id,
            "Leadership report update: {$report->title} was updated.\n\nType: {$report->report_type}\nStatus: {$report->status}\nPriority: {$report->priority}",
            'LeadershipReportUpdated',
            (int) $report->campus_id,
            $report->ministry_id !== null ? (int) $report->ministry_id : null,
            "Leadership report updated: {$report->title}",
        );

        return redirect()->route('leadership-reports.show', $report)->with('status', $status === 'submitted' ? 'Leadership report submitted.' : 'Leadership report draft updated.');
    }

    public function destroy(Request $request, LeadershipReport $leadershipReport, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $report = $this->visibleReports($request)
            ->whereKey($leadershipReport->id)
            ->firstOrFail();

        abort_unless($report->status === 'draft' && (int) $report->submitted_by === (int) $request->user()->id, 403);

        $title = $report->title;

        foreach (data_get($report->metrics, 'attachments', []) as $attachment) {
            $path = is_array($attachment) ? ($attachment['path'] ?? null) : null;
            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }
        }

        $activityLogger->log('Leadership Reports', 'leadership_report_deleted', $title.' draft was deleted.', $report, [
            'resource' => 'Leadership Report',
            'status' => 'deleted',
        ], $request);

        $report->delete();

        return redirect()->route('leadership-reports.index', ['tab' => 'my'])->with('status', 'Draft report deleted.');
    }

    public function saveAsTemplate(Request $request, LeadershipReport $leadershipReport, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $report = $this->visibleReports($request)
            ->whereKey($leadershipReport->id)
            ->firstOrFail();

        abort_unless((int) $report->submitted_by === (int) $request->user()->id, 403);

        $validated = $request->validateWithBag('saveTemplate', [
            'template_name' => ['required', 'string', 'max:180'],
            'template_description' => ['nullable', 'string', 'max:500'],
        ]);

        $template = LeadershipReportTemplate::query()->create([
            'church_id' => $this->churchId($request),
            'user_id' => $request->user()->id,
            'campus_id' => $report->campus_id,
            'ministry_id' => $report->ministry_id,
            'assigned_to' => $report->assigned_to,
            'name' => trim($validated['template_name']),
            'description' => trim((string) ($validated['template_description'] ?? '')) ?: null,
            'report_type' => $report->report_type,
            'priority' => $report->priority,
            'summary' => $report->summary,
            'metrics' => $this->reusableTemplateMetrics($report->metrics ?? []),
            'action_items' => $report->action_items ?? [],
        ]);

        $activityLogger->log('Leadership Reports', 'leadership_report_template_created', $template->name.' was saved as a personal report template.', $template, [
            'resource' => 'Leadership Report Template',
            'status' => 'private',
            'source_report_id' => $report->id,
        ], $request);

        return redirect()
            ->route('leadership-reports.index', ['tab' => 'templates'])
            ->with('status', 'Personal template saved. Only you can view and use it.');
    }

    public function usePersonalTemplate(Request $request, LeadershipReportTemplate $leadershipReportTemplate, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $template = $this->personalTemplates($request)
            ->whereKey($leadershipReportTemplate->id)
            ->firstOrFail();
        [$periodStart, $periodEnd, $periodLabel] = $this->personalTemplatePeriod($template->report_type);

        $campusId = $template->campus_id !== null && $this->visibleCampuses($request)->whereKey($template->campus_id)->exists()
            ? $template->campus_id
            : null;
        $ministryId = $template->ministry_id !== null && $this->visibleMinistries($request)->whereKey($template->ministry_id)->exists()
            ? $template->ministry_id
            : null;
        $reviewerId = $template->assigned_to !== null && $this->visibleReporters($request)->whereKey($template->assigned_to)->exists()
            ? $template->assigned_to
            : null;

        $report = LeadershipReport::query()->create([
            'church_id' => $this->churchId($request),
            'campus_id' => $campusId,
            'ministry_id' => $ministryId,
            'submitted_by' => $request->user()->id,
            'assigned_to' => $reviewerId,
            'title' => $template->name.' - '.$periodLabel,
            'report_type' => $template->report_type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
            'priority' => $template->priority,
            'summary' => $template->summary,
            'metrics' => $template->metrics ?? [],
            'action_items' => $template->action_items ?? [],
        ]);

        $activityLogger->log('Leadership Reports', 'leadership_report_template_used', $template->name.' created a new private draft.', $report, [
            'resource' => 'Leadership Report',
            'status' => 'draft',
            'template_id' => $template->id,
        ], $request);

        return redirect()
            ->to(route('leadership-reports.show', $report).'#edit-report')
            ->with('status', 'Personal template opened as a new draft.');
    }

    public function destroyPersonalTemplate(Request $request, LeadershipReportTemplate $leadershipReportTemplate, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);

        $template = $this->personalTemplates($request)
            ->whereKey($leadershipReportTemplate->id)
            ->firstOrFail();
        $name = $template->name;

        $activityLogger->log('Leadership Reports', 'leadership_report_template_deleted', $name.' personal report template was deleted.', $template, [
            'resource' => 'Leadership Report Template',
            'status' => 'deleted',
        ], $request);

        $template->delete();

        return redirect()
            ->route('leadership-reports.index', ['tab' => 'templates'])
            ->with('status', 'Personal template deleted.');
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
            'canReviewLeadershipReports' => $this->canReviewLeadershipReports($request),
            'types' => self::TYPES,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Pastor & Leadership Reports', 'url' => route('leadership-reports.index')],
                ['label' => $report->title, 'url' => null],
            ],
        ]);
    }

    public function viewAttachment(Request $request, LeadershipReport $leadershipReport, int $attachment): StreamedResponse
    {
        $this->authorizeReports($request);

        $report = $this->visibleReports($request)
            ->whereKey($leadershipReport->id)
            ->firstOrFail();
        $attachments = array_values(array_filter(
            data_get($report->metrics, 'attachments', []),
            fn ($item): bool => is_array($item),
        ));
        $file = $attachments[$attachment] ?? null;
        $path = is_array($file) ? ($file['path'] ?? null) : null;

        abort_unless(is_string($path) && $path !== '' && Storage::disk('local')->exists($path), 404);

        $name = basename((string) ($file['original_name'] ?? 'report-attachment'));
        $mimeType = (string) ($file['mime_type'] ?? 'application/octet-stream');
        $disposition = Str::startsWith($mimeType, ['image/', 'application/pdf', 'text/']) ? 'inline' : 'attachment';

        return Storage::disk('local')->response($path, $name, ['Content-Type' => $mimeType], $disposition);
    }

    public function review(Request $request, LeadershipReport $leadershipReport, ActivityLogger $activityLogger, ZenderWhatsAppNotifier $notifier): RedirectResponse
    {
        $this->authorizeReports($request);
        abort_unless($this->canReviewLeadershipReports($request), 403);
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
        $notifier->notify(
            (int) $leadershipReport->church_id,
            "Leadership report review: {$leadershipReport->title} is now ".str_replace('_', ' ', $validated['decision']).".\n\nType: {$leadershipReport->report_type}\nReviewed by: ".$request->user()->name,
            'LeadershipReportReviewed',
            (int) $leadershipReport->campus_id,
            $leadershipReport->ministry_id !== null ? (int) $leadershipReport->ministry_id : null,
            "Leadership report reviewed: {$leadershipReport->title}",
        );

        return redirect()->route('leadership-reports.index', ['report' => $leadershipReport->opaqueId()])->with('status', 'Report review updated.');
    }

    public function generateSummary(Request $request, ActivityLogger $activityLogger, ZenderWhatsAppNotifier $notifier): RedirectResponse
    {
        $this->authorizeReports($request);
        $stats = $this->stats($request);
        $summary = $stats['pending_review'].' reports need review, '.$stats['requires_action'].' require action, and average review time is '.$stats['average_review_time'].' days.';

        $activityLogger->log('Leadership Reports', 'leadership_summary_generated', 'Leadership report summary was generated.', null, ['resource' => 'Leadership Summary', 'status' => 'success'], $request);
        $notifier->notify(
            $this->churchId($request),
            "Leadership report summary: {$summary}",
            'LeadershipReportSummary',
            $request->user()?->campus_id !== null ? (int) $request->user()->campus_id : null,
            null,
            'Leadership report summary',
        );

        return back()->with('status', 'Summary generated: '.$summary);
    }

    public function sendReminders(Request $request, ActivityLogger $activityLogger, ZenderWhatsAppNotifier $notifier): RedirectResponse
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
        $notifier->notify(
            $this->churchId($request),
            number_format($pending).' leadership report reminders queued.',
            'LeadershipReportReminders',
            $request->user()?->campus_id !== null ? (int) $request->user()->campus_id : null,
            null,
            'Leadership report reminders',
        );

        return back()->with('status', number_format($pending).' leadership report reminders queued.');
    }

    public function updateSettings(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeReports($request);
        $canReviewLeadershipReports = $this->canReviewLeadershipReports($request);

        $validated = $request->validate([
            'default_reviewer_id' => ['nullable', 'exists:users,id'],
            'reviewer_role_filter_present' => ['nullable', 'boolean'],
            'reviewer_role_ids' => ['nullable', 'array'],
            'reviewer_role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
            'weekly_due_day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'auto_reminders' => ['nullable', 'boolean'],
            'require_action_items' => ['nullable', 'boolean'],
            'escalation_hours' => [$canReviewLeadershipReports ? 'required' : 'nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $roleFilterCanBeUpdated = $this->canManageReviewerRoles($request) && $request->boolean('reviewer_role_filter_present');
        $reviewerRoleIds = $roleFilterCanBeUpdated
            ? collect($validated['reviewer_role_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all()
            : $this->reviewerRoleIds($request);

        if (! empty($validated['default_reviewer_id'])) {
            abort_unless($this->visibleReporters($request, $reviewerRoleIds)->whereKey($validated['default_reviewer_id'])->exists(), 403);
        }

        $user = $request->user();
        $settings = $user->account_settings ?? [];
        $currentReportSettings = $this->reportSettings($request);
        $settings['leadership_reports'] = [
            'default_reviewer_id' => $validated['default_reviewer_id'] ?? null,
            'weekly_due_day' => $validated['weekly_due_day'],
            'auto_reminders' => $request->boolean('auto_reminders'),
            'require_action_items' => $request->boolean('require_action_items'),
            'escalation_hours' => $canReviewLeadershipReports
                ? (int) $validated['escalation_hours']
                : (int) $currentReportSettings['escalation_hours'],
            'updated_by' => $user->name,
            'updated_at' => now()->toDateTimeString(),
        ];
        $user->forceFill(['account_settings' => $settings])->save();

        if ($roleFilterCanBeUpdated) {
            $church = Church::query()->findOrFail($this->churchId($request));
            $churchSettings = $church->settings ?? [];
            $churchReportSettings = data_get($churchSettings, 'leadership_reports', []);
            if (! is_array($churchReportSettings)) {
                $churchReportSettings = [];
            }
            $churchReportSettings['reviewer_role_ids'] = $reviewerRoleIds;
            $churchReportSettings['reviewer_roles_updated_by'] = $user->name;
            $churchReportSettings['reviewer_roles_updated_at'] = now()->toDateTimeString();
            data_set($churchSettings, 'leadership_reports', $churchReportSettings);
            $church->forceFill(['settings' => $churchSettings])->save();
        }

        $activityLogger->log('Leadership Reports', 'leadership_report_settings_updated', 'Leadership report settings were updated.', $user, [
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
            Csv::write($handle, ['Title', 'Type', 'Campus', 'Ministry', 'From', 'To', 'Period', 'Priority', 'Status', 'Submitted', 'Reviewed']);
            foreach ($rows as $report) {
                Csv::write($handle, [
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

        if (! $this->hasBroadLeadershipCampusScope($request) && $request->user()?->campus_id !== null) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        return $query;
    }

    private function personalTemplates(Request $request)
    {
        return LeadershipReportTemplate::query()
            ->where('church_id', $this->churchId($request))
            ->where('user_id', $request->user()->id);
    }

    private function reusableTemplateMetrics(array $metrics): array
    {
        unset(
            $metrics['attachments'],
            $metrics['attendance_session_ids'],
            $metrics['attendance_sessions'],
            $metrics['attendance_source'],
            $metrics['attendance_total'],
            $metrics['attendance_expected'],
        );

        return $metrics;
    }

    private function visibleCampuses(Request $request)
    {
        $query = Campus::query()->where('church_id', $this->churchId($request));

        if (! $this->hasBroadLeadershipCampusScope($request) && $request->user()?->campus_id !== null) {
            $query->whereKey($request->user()->campus_id);
        }

        return $query;
    }

    private function visibleMinistries(Request $request)
    {
        $query = Ministry::query()->where('church_id', $this->churchId($request));

        if (! $this->hasBroadLeadershipCampusScope($request) && $request->user()?->campus_id !== null) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        return $query;
    }

    private function visibleReporters(Request $request, ?array $reviewerRoleIds = null)
    {
        $query = User::query()->where('church_id', $this->churchId($request));

        $reviewerRoleIds ??= $this->reviewerRoleIds($request);
        if ($reviewerRoleIds !== []) {
            $query->whereHas('roles', fn ($roles) => $roles->whereIn('roles.id', $reviewerRoleIds));
        }

        if (! $this->hasBroadLeadershipCampusScope($request) && $request->user()?->campus_id !== null) {
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

    private function reportPayload(Request $request, string $status, ?LeadershipReport $report = null): array
    {
        $existingAttachmentCount = count(array_filter(
            data_get($report?->metrics, 'attachments', []),
            fn ($attachment): bool => is_array($attachment),
        ));
        $availableAttachmentSlots = max(0, 8 - $existingAttachmentCount);

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
            'attendance_session_ids' => ['nullable', 'array'],
            'attendance_session_ids.*' => ['integer', 'exists:attendance_sessions,id'],
            'attendance_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discipleship_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'care_followups' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'volunteer_coverage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'service_notes' => ['nullable', 'string', 'max:3000'],
            'issues' => ['nullable', 'string', 'max:3000'],
            'plans' => ['nullable', 'string', 'max:3000'],
            'supporting_links' => ['nullable', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:'.$availableAttachmentSlots],
            'attachments.*' => ['file', 'max:15360', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,webp'],
            'action_items' => ['nullable', 'string', 'max:3000'],
        ]);

        $user = $request->user();
        if (! $this->hasBroadLeadershipCampusScope($request) && $user?->campus_id !== null) {
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

        $attendanceMetrics = $this->attendanceMetrics($request, $validated);

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
                'attendance_score' => $attendanceMetrics['score'],
                'attendance_source' => $attendanceMetrics['source'],
                'attendance_total' => $attendanceMetrics['total'],
                'attendance_expected' => $attendanceMetrics['expected'],
                'attendance_sessions' => $attendanceMetrics['sessions'],
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
                'attachments' => array_values(array_filter(
                    data_get($report?->metrics, 'attachments', []),
                    fn ($attachment): bool => is_array($attachment),
                )),
            ],
            'action_items' => collect(preg_split('/\r\n|\r|\n/', (string) ($validated['action_items'] ?? '')))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function storeAttachments(Request $request, LeadershipReport $report): void
    {
        $files = $request->file('attachments', []);
        if (! is_array($files) || $files === []) {
            return;
        }

        $metrics = $report->metrics ?? [];
        $attachments = array_values(array_filter(
            data_get($metrics, 'attachments', []),
            fn ($attachment): bool => is_array($attachment),
        ));

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $storedName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
            $path = $file->storeAs('leadership-reports/'.$report->church_id.'/'.$report->id, $storedName, 'local');

            $attachments[] = [
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()?->id,
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        data_set($metrics, 'attachments', $attachments);
        $report->forceFill(['metrics' => $metrics])->save();
    }

    private function attendanceSources(Request $request)
    {
        return $this->attendanceSourceQuery($request)
            ->whereHas('eventSession', fn ($query) => $query->whereDate('session_date', '<=', now()->toDateString()))
            ->latest('opens_at')
            ->limit(120)
            ->get()
            ->map(function (AttendanceSession $attendanceSession): array {
                $eventSession = $attendanceSession->eventSession;
                $present = (int) ($attendanceSession->present_records_count ?? 0);
                $expected = (int) ($attendanceSession->expected_attendance ?? 0);

                return [
                    'id' => $attendanceSession->id,
                    'title' => $eventSession?->title ?? $attendanceSession->title,
                    'event' => $eventSession?->event?->title,
                    'date' => $eventSession?->session_date?->toDateString() ?? $attendanceSession->opens_at?->toDateString(),
                    'date_label' => $eventSession?->session_date?->format('M d, Y') ?? $attendanceSession->opens_at?->format('M d, Y'),
                    'campus_id' => $attendanceSession->campus_id,
                    'campus' => $attendanceSession->campus?->name ?? $eventSession?->campus?->name ?? 'All Campuses',
                    'present' => $present,
                    'expected' => $expected,
                    'score' => $expected > 0 ? min(100, (int) round(($present / $expected) * 100)) : min(100, $present),
                    'status' => $attendanceSession->status,
                ];
            })
            ->values();
    }

    private function attendanceSourceQuery(Request $request)
    {
        $query = AttendanceSession::query()
            ->with(['eventSession.event', 'eventSession.campus', 'campus'])
            ->withCount([
                'records',
                'records as present_records_count' => fn ($records) => $records->whereIn('status', ['present', 'late']),
            ])
            ->where('church_id', $this->churchId($request))
            ->whereHas('eventSession');

        if (! $this->hasBroadLeadershipCampusScope($request) && $request->user()?->campus_id !== null) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        return $query;
    }

    private function attendanceMetrics(Request $request, array $validated): array
    {
        $ids = collect($validated['attendance_session_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [
                'score' => (int) ($validated['attendance_score'] ?? 0),
                'source' => 'manual',
                'total' => null,
                'expected' => null,
                'sessions' => [],
            ];
        }

        $periodStart = Carbon::parse($validated['period_start'])->toDateString();
        $periodEnd = Carbon::parse($validated['period_end'])->toDateString();
        $sessions = $this->attendanceSourceQuery($request)
            ->whereIn('id', $ids->all())
            ->whereHas('eventSession', fn ($query) => $query
                ->whereBetween('session_date', [$periodStart, $periodEnd])
                ->whereDate('session_date', '<=', now()->toDateString()))
            ->when(! empty($validated['campus_id']), fn ($query) => $query->where('campus_id', $validated['campus_id']))
            ->get();

        abort_unless($sessions->count() === $ids->count(), 422, 'Selected attendance must match the report period and scope.');

        $total = $sessions->sum(fn (AttendanceSession $session): int => (int) ($session->present_records_count ?? 0));
        $expected = $sessions->sum(fn (AttendanceSession $session): int => (int) ($session->expected_attendance ?? 0));

        return [
            'score' => $expected > 0 ? min(100, (int) round(($total / $expected) * 100)) : min(100, (int) $total),
            'source' => 'recorded',
            'total' => (int) $total,
            'expected' => (int) $expected,
            'sessions' => $sessions
                ->map(fn (AttendanceSession $session): array => [
                    'id' => $session->id,
                    'title' => $session->eventSession?->title ?? $session->title,
                    'date' => $session->eventSession?->session_date?->toDateString(),
                    'present' => (int) ($session->present_records_count ?? 0),
                    'expected' => (int) ($session->expected_attendance ?? 0),
                ])
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
            'templates' => count($this->templates()) + $this->personalTemplates($request)->count(),
            'settings' => 1,
        ];
    }

    private function personalTemplatePeriod(string $reportType): array
    {
        $today = now()->startOfDay();

        if ($reportType === 'weekly') {
            $start = $today->copy()->startOfWeek();
            $end = $today->copy()->endOfWeek();
            $label = 'Week of '.$start->format('M d, Y');
        } elseif ($reportType === 'strategic') {
            $start = $today->copy()->startOfQuarter();
            $end = $today->copy()->endOfQuarter();
            $label = 'Q'.$start->quarter.' '.$start->year;
        } elseif ($reportType === 'incident') {
            $start = $today->copy();
            $end = $today->copy();
            $label = $start->format('M d, Y');
        } else {
            $start = $today->copy()->startOfMonth();
            $end = $today->copy()->endOfMonth();
            $label = $start->format('F Y');
        }

        return [$start->toDateString(), $end->toDateString(), $label];
    }

    private function templates(): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $quarterStart = now()->startOfQuarter();
        $quarterEnd = now()->endOfQuarter();
        $today = now()->startOfDay();
        $sampleFirstDue = now()->addDays(5)->format('M d, Y');
        $sampleSecondDue = now()->addDays(10)->format('M d, Y');
        $sampleReviewDate = now()->addMonth()->startOfMonth()->addDays(4)->format('M d, Y');

        return [
            [
                'name' => 'Weekly Campus Operations Report',
                'title' => 'Weekly Campus Operations Report - Week of '.$weekStart->format('M d, Y'),
                'type' => 'weekly',
                'priority' => 'normal',
                'icon' => 'calendar-check',
                'tone' => 'bg-violet-50 text-violet-600 ring-violet-100',
                'cadence' => 'Weekly',
                'audience' => 'Campus leadership',
                'period_start' => $weekStart->toDateString(),
                'period_end' => $weekEnd->toDateString(),
                'description' => 'A complete weekly operating review for attendance, services, ministry delivery, people care, volunteers, risks, decisions, and the next seven days.',
                'sections' => ['Executive overview', 'Attendance', 'Services', 'Care', 'Volunteers', 'Risks', 'Next actions'],
                'summary' => <<<'TEXT'
EXECUTIVE OVERVIEW
- Overall campus health: [Green / Amber / Red] — explain the rating.
- Most important outcome this week: [Describe the measurable ministry result].
- Most important concern: [State the issue, impact, and urgency].
- Leadership decision required: [Decision, owner, and required date].

ATTENDANCE & ENGAGEMENT
- Total attendance and comparison with target, last week, and the same period last year: [Add figures and variance].
- First-time guests, returning guests, salvations, memberships, and next-step responses: [Add verified totals].
- Groups, classes, and discipleship participation: [Add participation and completion movement].

MINISTRY & PEOPLE UPDATE
- Key achievements by department: [Worship, children, youth, hospitality, media, security, care, and administration].
- Pastoral care and follow-up status: [Open, completed, overdue, and escalated cases—exclude unnecessary confidential details].
- Volunteer readiness: [Required roles, filled roles, absences, substitutions, and training needs].

FINANCIAL & OPERATIONAL SNAPSHOT
- Giving or budget movement relevant to operations: [Add approved figures or reference finance report].
- Facilities, equipment, safety, or technology matters: [Status, impact, and owner].
TEXT,
                'service_notes' => <<<'TEXT'
SERVICES & PROGRAM DELIVERY
- Services and programs held: [Name, date/time, campus, attendance, and ministry owner].
- Worship and production: [Start time, flow, audio, video, livestream, presentation, and equipment performance].
- Message response: [Prayer, salvation, rededication, counseling, baptism, membership, or next-step responses].
- Guest experience: [Welcome, check-in, seating, accessibility, hospitality, parking, and follow-up quality].
- Children and youth safeguarding: [Check-in compliance, ratios, incidents, and parent communication].
- What worked well: [Specific practice to repeat].
- What should change next week: [Specific improvement and responsible leader].
TEXT,
                'issues' => <<<'TEXT'
RISKS, BLOCKERS & SUPPORT REQUIRED
- Critical issue: [Problem] | Impact: [People/ministry/financial/operational] | Owner: [Name] | Needed by: [Date].
- Staffing or volunteer gap: [Role, number needed, service affected, and interim plan].
- Pastoral or member care escalation: [Category and required response—protect confidential information].
- Facility, safety, compliance, or technology risk: [Risk level, temporary control, and permanent resolution].
- Budget or procurement request: [Amount/asset, ministry purpose, approval needed, and deadline].
- Cross-campus or senior leadership support requested: [Specific support and expected outcome].
TEXT,
                'plans' => <<<'TEXT'
NEXT 7 DAYS
- Service readiness: Confirm schedule, speakers, worship plan, volunteers, rooms, equipment, and contingency owners.
- People follow-up: Assign all guest, member care, counseling, absence, and new-believer follow-ups.
- Ministry priorities: Identify the three highest-impact activities and the measurable result expected from each.
- Leadership rhythm: Confirm team meeting, one-to-ones, coaching, and unresolved decision reviews.
- Communications: Confirm announcements, member messages, event promotion, and owner approval dates.
- Evidence to attach: Attendance export, service plan, volunteer roster, incident record, photos, or supporting documents where appropriate.
TEXT,
                'actions' => "Owner: [Name] | Due: [Date] | Reconcile attendance and engagement totals against the approved source\nOwner: [Name] | Due: [Date] | Complete all overdue guest, member care, and pastoral follow-ups\nOwner: [Name] | Due: [Date] | Fill priority volunteer gaps and confirm the final service roster\nOwner: [Name] | Due: [Date] | Resolve or escalate the highest operational, facility, safety, or technology risk\nOwner: [Name] | Due: [Date] | Obtain the leadership decision or approval identified in this report\nOwner: [Name] | Due: [Date] | Confirm next week's ministry plan and communicate responsibilities",
                'metrics' => [0, 0, 0, 0],
            ],
            [
                'name' => 'Monthly Executive Performance Report',
                'title' => 'Monthly Executive Performance Report - '.$monthStart->format('F Y'),
                'type' => 'monthly',
                'priority' => 'high',
                'icon' => 'chart-no-axes-combined',
                'tone' => 'bg-blue-50 text-blue-600 ring-blue-100',
                'cadence' => 'Monthly',
                'audience' => 'Executive leadership',
                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->toDateString(),
                'description' => 'A month-end leadership pack covering ministry outcomes, people growth, operational performance, stewardship, strategic progress, risks, and decisions.',
                'sections' => ['Executive summary', 'KPI trends', 'Ministry outcomes', 'Stewardship', 'People health', 'Risks', 'Decisions'],
                'summary' => <<<'TEXT'
EXECUTIVE SUMMARY
- Overall monthly performance: [Green / Amber / Red] — explain using verified outcomes.
- Mission impact: [Describe the strongest evidence of spiritual growth, community impact, or member care].
- Performance against monthly priorities: [Completed / On track / At risk / Deferred, with reasons].
- Significant movement from the previous month: [Attendance, engagement, discipleship, care, volunteers, giving, or operations].
- Top three achievements: [Outcome, evidence, responsible team, and strategic value].
- Top three concerns: [Issue, current impact, forecast impact, and mitigation].
- Executive decisions requested: [Decision, recommendation, alternatives, cost, and deadline].

MONTHLY SCORECARD
- Attendance: [Actual, target, variance, month-over-month, year-over-year].
- Discipleship: [Groups/classes/mentoring participation, completion, and next steps].
- Care: [Cases opened, completed, overdue, escalated, and response time].
- Volunteers: [Required, active, new, inactive, retention, and coverage].
- Giving/budget reference: [Actual, budget, variance, restricted funds, and forecast—attach approved finance report].
- Events and programs: [Delivered, attendance, outcome, cost, and follow-up status].
TEXT,
                'service_notes' => <<<'TEXT'
MINISTRY & OPERATIONAL PERFORMANCE
- Department results: Record each ministry's objective, result, evidence, variance, and corrective action.
- Weekend/service quality: Summarize consistency, response, guest experience, production, safeguarding, and recurring issues.
- Member journey: Report movement through visitor, follow-up, membership, baptism, service, groups, and leadership pathways.
- People and leadership: Report staffing changes, volunteer pipeline, training, succession, morale, capacity, and accountability.
- Communications and digital reach: Report campaigns, reach, engagement, response, website/media performance, and next actions.
- Facilities and systems: Report availability, maintenance, incidents, security, compliance, data quality, and technology uptime.
- Partnerships and community impact: Report activity, beneficiaries, outcomes, cost, and next commitment.
TEXT,
                'issues' => <<<'TEXT'
EXECUTIVE RISKS & EXCEPTIONS
- Strategic risk: [Description, likelihood, impact, trigger, mitigation, owner, review date].
- Financial exception: [Budget line, variance, explanation, corrective action, and approval required].
- People/capacity risk: [Role or team, impact, temporary coverage, recruitment/development plan].
- Ministry performance exception: [Target missed, root cause, recovery plan, and expected recovery date].
- Compliance, safeguarding, reputational, data, or security concern: [Restricted summary and escalation route].
- Dependency requiring executive support: [Decision, funding, relationship, policy, or cross-team coordination].
TEXT,
                'plans' => <<<'TEXT'
NEXT MONTH & FORECAST
- Three organization-wide priorities: [Outcome, KPI, accountable leader, milestone, and deadline].
- Ministry recovery plans: [Area below target, intervention, owner, and review date].
- People plan: [Recruitment, volunteer mobilization, training, coaching, succession, and wellbeing actions].
- Stewardship plan: [Budget controls, procurement, fundraising/giving, asset, or efficiency actions].
- Calendar readiness: [Major services, events, communications, facilities, safeguarding, and contingency preparation].
- 30/60/90-day outlook: [Expected opportunities, pressures, decisions, and resource requirements].
- Evidence to attach: KPI dashboard, attendance analysis, approved finance report, ministry scorecards, risk register, and action tracker.
TEXT,
                'actions' => "Executive owner: [Name] | Due: [Date] | Validate the monthly scorecard and explain every material variance\nExecutive owner: [Name] | Due: [Date] | Approve or revise the three priorities for the next reporting month\nExecutive owner: [Name] | Due: [Date] | Assign mitigation owners for all high or critical risks\nExecutive owner: [Name] | Due: [Date] | Decide each funding, staffing, policy, or cross-campus request\nExecutive owner: [Name] | Due: [Date] | Review underperforming ministries and approve recovery milestones\nExecutive owner: [Name] | Due: [Date] | Communicate approved decisions and update the organization action tracker",
                'metrics' => [0, 0, 0, 0],
            ],
            [
                'name' => 'Ministry/Department Health & Impact Report',
                'title' => 'Ministry/Department Health & Impact Report - '.$monthStart->format('F Y'),
                'type' => 'ministry',
                'priority' => 'high',
                'icon' => 'users-round',
                'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
                'cadence' => 'Monthly',
                'audience' => 'Ministry leaders',
                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->toDateString(),
                'description' => 'A detailed ministry review linking participation, discipleship outcomes, leader and volunteer health, member care, delivery quality, and resource requirements.',
                'sections' => ['Purpose & outcomes', 'Participation', 'Discipleship', 'Team health', 'Care', 'Resources', 'Improvement plan'],
                'summary' => <<<'TEXT'
MINISTRY PURPOSE & OUTCOME SUMMARY
- Ministry/campus and reporting leader: [Name, role, and scope].
- Purpose served this period: [How activity supported the church mission and member journey].
- Overall ministry health: [Green / Amber / Red] — explain with evidence.
- Primary objective: [Target] | Actual result: [Result] | Variance: [Difference and cause].
- Participation: [Unique people, total contacts, new participants, returning participants, attendance trend].
- Discipleship impact: [Spiritual growth, classes, mentoring, group movement, decisions, testimonies, and next steps].
- Top achievements: [Outcome, evidence, responsible leader, and people impacted].
- Matters requiring senior leadership attention: [Decision, resources, care, policy, or escalation].
TEXT,
                'service_notes' => <<<'TEXT'
MINISTRY DELIVERY & TEAM HEALTH
- Programs/services delivered: [Name, date, objective, attendance, outcome, and follow-up].
- Program quality: [Preparation, content, pastoral value, accessibility, safeguarding, and participant feedback].
- Leadership team: [Active leaders, vacancies, one-to-ones, coaching, accountability, and succession readiness].
- Volunteer team: [Required, scheduled, attended, absent, new, trained, inactive, and retention concerns].
- Member care: [Needs identified, referrals, completed follow-ups, overdue follow-ups, and owner].
- Collaboration: [Other ministries/campuses involved, handoffs completed, and unresolved dependencies].
- Resources used: [Rooms, equipment, transport, materials, communications, and approved budget reference].
- Data quality: [Registers complete, attendance verified, consent/compliance complete, and evidence attached].
TEXT,
                'issues' => <<<'TEXT'
MINISTRY RISKS & SUPPORT REQUESTS
- Participation concern: [Affected group, trend, root cause, and recovery action].
- Leadership or volunteer gap: [Role, capability needed, impact, interim coverage, and recruitment/training request].
- Pastoral care concern: [Restricted summary, escalation owner, and follow-up deadline].
- Program quality or safeguarding issue: [Issue, immediate control, reporting status, and accountable leader].
- Budget/resource constraint: [Item, cost or capacity, ministry impact, alternatives, and approval needed].
- Cross-ministry dependency: [Team, requested contribution, deadline, and consequence if delayed].
TEXT,
                'plans' => <<<'TEXT'
MINISTRY IMPROVEMENT PLAN
- Next-period objective: [Specific outcome and ministry KPI].
- Participation and follow-up: [Invitation, communication, onboarding, retention, and re-engagement actions].
- Discipleship pathway: [Next-step content, mentoring, group placement, baptism/membership/service pathways].
- Team development: [Recruitment, orientation, training, coaching, succession, and appreciation actions].
- Program calendar: [Key dates, owners, rooms, equipment, safeguarding, communications, and contingency plan].
- Resource request: [People, budget, facility, system, or leadership decision with justification].
- Evidence to attach: Attendance/register, ministry calendar, volunteer roster, budget reference, feedback, and action tracker.
TEXT,
                'actions' => "Ministry owner: [Name] | Due: [Date] | Verify participation and discipleship results against source records\nMinistry owner: [Name] | Due: [Date] | Assign and complete all open participant and pastoral care follow-ups\nMinistry owner: [Name] | Due: [Date] | Close priority leadership and volunteer coverage gaps\nMinistry owner: [Name] | Due: [Date] | Deliver the next team training, coaching, or succession milestone\nMinistry owner: [Name] | Due: [Date] | Resolve or escalate the highest ministry risk or resource constraint\nMinistry owner: [Name] | Due: [Date] | Confirm the next-period calendar, measurable objective, and communication plan",
                'metrics' => [0, 0, 0, 0],
            ],
            [
                'name' => 'Ministry/Department Leadership Report',
                'title' => 'Community Outreach Department Leadership Report - '.$monthStart->format('F Y'),
                'type' => 'ministry',
                'priority' => 'high',
                'icon' => 'clipboard-check',
                'tone' => 'bg-cyan-50 text-cyan-700 ring-cyan-100',
                'cadence' => 'Completed sample',
                'audience' => 'Ministry & department leaders',
                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->toDateString(),
                'description' => 'A fully completed Community Outreach Department report with realistic leaders, verified outcomes, team health, risks, decisions, and dated actions.',
                'sections' => ['Leadership overview', 'Performance scorecard', 'People served', 'Team health', 'Stewardship', 'Risks', 'Dated actions'],
                'is_sample' => true,
                'summary' => <<<TEXT
MINISTRY/DEPARTMENT LEADERSHIP OVERVIEW
Department: Community Outreach Department
Campus: Downtown Campus
Department Lead: Grace Okafor
Reporting Period: {$monthStart->format('F d, Y')} to {$monthEnd->format('F d, Y')}
Overall Health: Green with focused attention required for weekday volunteer coverage

EXECUTIVE SUMMARY
The Community Outreach Department served 412 people through food support, neighborhood visits, employment coaching, and family care referrals. This was 37 people above the monthly target of 375 and represented a 14 percent increase from the previous month. Eighty-seven percent of registered participants attended at least one follow-up activity, and 46 first-time guests gave consent for continued contact.

The strongest outcome was the employment readiness partnership led by Samuel Adeyemi. Twenty-four participants completed the four-week workshop, 18 attended interviews, and 9 received confirmed job offers before the end of the reporting period. The main operational concern is weekday transport and welcome-desk coverage, where four of twelve scheduled shifts required same-day substitutions.

LEADERSHIP TEAM
Grace Okafor, Department Lead: strategy, pastoral oversight, safeguarding, and executive reporting
Daniel Mensah, Volunteer Coordinator: recruitment, scheduling, training, and attendance reconciliation
Miriam Cole, Care Follow-up Lead: participant care, referrals, consent records, and case closure
Samuel Adeyemi, Operations Coordinator: logistics, facilities, transport, suppliers, and partner coordination
Leah Brooks, Discipleship Coordinator: prayer follow-up, small-group placement, mentoring, and next steps

PERFORMANCE SCORECARD
People served: 412 against a target of 375, exceeding target by 9.9 percent
First-time participants: 68, including 46 who consented to follow-up
Returning participants: 273
Community partners engaged: 11 active partners, including 3 new partners
Care cases opened: 31
Care cases completed: 23
Care cases still active: 8, with no overdue high-risk cases
Discipleship conversations: 74
Small-group placements: 19
Volunteer requirement: 62 active volunteers
Volunteer availability: 52 active volunteers, producing 84 percent coverage
Volunteer retention: 91 percent
Participant satisfaction: 4.6 out of 5 from 126 verified responses

LEADERSHIP ASSESSMENT
Mission alignment is strong. Programs are reaching the intended families, follow-up quality has improved, and the partnership pipeline is healthy. The department can sustain current weekend delivery, but weekday services need six additional trained volunteers and one backup driver. No safeguarding, financial control, or reputational breach was recorded during the reporting period.
TEXT,
                'service_notes' => <<<TEXT
PROGRAM DELIVERY AND IMPACT

Community Food Support
Four distribution days served 286 households and delivered 1,148 food parcels. Registration accuracy reached 98 percent after Daniel Mensah introduced a two-stage verification process. Average waiting time improved from 31 minutes to 19 minutes. Seven households requiring urgent care were referred to Miriam Cole and contacted within 24 hours.

Employment Readiness Programme
Twenty-four participants completed workshops covering CV preparation, interview practice, digital applications, and workplace confidence. Eighteen participants attended verified interviews and nine received job offers. Samuel Adeyemi secured two employer partners for next month and arranged a dedicated interview clothing bank.

Neighborhood Care Visits
Six volunteer teams completed 83 household visits. Teams recorded 29 prayer requests, 14 practical support needs, and 6 referrals for pastoral follow-up. Every visit used the approved consent and safeguarding process. Leah Brooks placed 19 participants into local small groups and scheduled five baptism conversations.

TEAM HEALTH AND CAPACITY
The department has 52 active volunteers against a requirement of 62. Forty-seven volunteers completed required safeguarding training, and the remaining five are booked for the next session. Team pulse feedback averaged 4.3 out of 5. Two volunteers requested a temporary reduction in duties due to family commitments, and revised schedules are in place. Grace Okafor completed one-to-one reviews with all four team coordinators.

COMMUNICATION AND COLLABORATION
The communications team published three campaign stories that generated 126 enquiries and 68 registrations. The Children and Families Ministry supported childcare during two employment workshops. The Finance team confirmed all programme expenses against approved cost centres. Partner communication was completed within two business days in 94 percent of cases.

STEWARDSHIP AND RESOURCES
Approved monthly budget: 18,500
Actual expenditure: 17,240
Favourable variance: 1,260
Food and household support: 10,620
Transport and logistics: 2,780
Training and programme materials: 2,110
Communications and printing: 980
Volunteer care and refreshments: 750
All purchases were supported by approved requests and receipts. No unapproved commitment or cash-handling exception was identified.
TEXT,
                'issues' => <<<TEXT
RISKS, ISSUES, AND LEADERSHIP SUPPORT

1. Weekday volunteer coverage
Risk level: Medium
Owner: Daniel Mensah
Current position: Coverage is 84 percent, and four weekday shifts required same-day substitutions.
Impact: Repeated gaps may increase participant waiting time and place pressure on trained team leaders.
Mitigation: Recruit six volunteers, run one accelerated orientation, and introduce a standby rota.
Leadership support: Approve promotion of the volunteer campaign during the next two Sunday services.

2. Backup transport capacity
Risk level: Medium
Owner: Samuel Adeyemi
Current position: The department relies on one primary van and one volunteer driver for weekday collections.
Impact: A vehicle or driver failure could delay food collection and neighborhood delivery.
Mitigation: A local transport company has offered a discounted standby arrangement.
Decision required: Approve a monthly standby ceiling of 900 by {$sampleFirstDue}.

3. Active family care cases
Risk level: Low and controlled
Owner: Miriam Cole
Current position: Eight cases remain active; three involve housing support, three involve employment support, and two involve ongoing pastoral care.
Mitigation: Every case has an assigned owner and next-contact date. No high-risk case is overdue.

4. Volunteer safeguarding completion
Risk level: Low
Owner: Daniel Mensah
Current position: Five active volunteers still need annual refresher training.
Mitigation: Training is booked, and the volunteers will not serve in unsupervised roles until completion is recorded.

5. Data consistency across partner referrals
Risk level: Low
Owner: Miriam Cole
Current position: Two partners used outdated referral categories during the first week.
Mitigation: Updated guidance was issued, affected records were corrected, and a partner briefing is scheduled before the next reporting period.
TEXT,
                'plans' => <<<TEXT
NEXT-PERIOD MINISTRY/DEPARTMENT PLAN

Priority 1: Increase weekday volunteer coverage
Outcome: Raise coverage from 84 percent to at least 92 percent.
Actions: Recruit six volunteers, complete orientation, assign mentors, and publish the standby rota.
Accountable leader: Daniel Mensah
Measurement: Required shifts filled, training completion, absence rate, and substitution rate.

Priority 2: Strengthen participant follow-up
Outcome: Contact 95 percent of consenting first-time participants within 48 hours and close at least 24 care actions.
Actions: Daily queue review, automated reminders, case-owner confirmation, and Friday exception review.
Accountable leader: Miriam Cole
Measurement: Contact completion, average response time, open cases, overdue cases, and referrals completed.

Priority 3: Expand employment outcomes
Outcome: Enroll 30 participants, achieve 25 completions, secure 20 interviews, and confirm 10 job placements.
Actions: Add two employer briefings, provide digital application support, and operate the interview clothing bank.
Accountable leader: Samuel Adeyemi
Measurement: Enrollment, completion, interview, placement, and 30-day retention totals.

Priority 4: Deepen discipleship connections
Outcome: Complete 80 spiritual-care conversations and place 25 people into a group, mentoring relationship, or next-step class.
Actions: Train six follow-up volunteers, add a next-step desk to distribution days, and complete weekly handoff checks.
Accountable leader: Leah Brooks
Measurement: Conversations, placements, first attendance, second attendance, and pastoral escalations.

Priority 5: Improve operational resilience
Outcome: Confirm backup transport, maintain 98 percent registration accuracy, and complete all safety checks before each activity.
Actions: Finalize the transport agreement, perform weekly equipment checks, and test the service disruption contact tree.
Accountable leader: Grace Okafor
Measurement: Transport availability, register errors, completed safety checks, incidents, and recovery time.

NEXT LEADERSHIP REVIEW
Grace Okafor will present progress, exceptions, financial variance, and required decisions at the department review on {$sampleReviewDate}. The evidence pack will include attendance exports, referral status, volunteer training records, budget reconciliation, partner feedback, and the updated action tracker.
TEXT,
                'actions' => "Daniel Mensah | Due: {$sampleFirstDue} | Recruit six weekday volunteers and publish the trained standby rota\nSamuel Adeyemi | Due: {$sampleFirstDue} | Finalize the backup transport agreement within the approved monthly ceiling\nMiriam Cole | Due: {$sampleFirstDue} | Contact every consenting first-time participant and update all active care records\nLeah Brooks | Due: {$sampleSecondDue} | Train six follow-up volunteers and confirm 25 discipleship pathway placements\nGrace Okafor | Due: {$sampleSecondDue} | Review safeguarding completion, budget variance, and partner data quality\nGrace Okafor | Due: {$sampleReviewDate} | Present the department scorecard, evidence pack, risks, and next decisions to executive leadership",
                'metrics' => [87, 79, 23, 84],
            ],
            [
                'name' => 'Campus Leadership & Governance Report',
                'title' => 'Campus Leadership & Governance Report - '.$monthStart->format('F Y'),
                'type' => 'campus',
                'priority' => 'high',
                'icon' => 'building-2',
                'tone' => 'bg-indigo-50 text-indigo-600 ring-indigo-100',
                'cadence' => 'Monthly',
                'audience' => 'Campus & central office',
                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->toDateString(),
                'description' => 'A campus-wide governance report for ministry performance, people and pastoral health, stewardship, facilities, compliance, risks, and head-office decisions.',
                'sections' => ['Campus scorecard', 'Ministries', 'People & care', 'Stewardship', 'Facilities', 'Compliance', 'HQ requests'],
                'summary' => <<<'TEXT'
CAMPUS LEADERSHIP OVERVIEW
- Campus and lead pastor/administrator: [Name and reporting scope].
- Overall campus health: [Green / Amber / Red] — explain with verified evidence.
- Mission impact this month: [Salvations, discipleship, care, community impact, leaders raised, or testimonies].
- Performance against campus goals: [Goal, target, actual, variance, and corrective action].
- Attendance and engagement movement: [Services, groups, children/youth, volunteers, guests, and member journey].
- Ministry portfolio status: [Healthy, watch, intervention required—explain each exception].
- People health: [Staff, leaders, volunteers, wellbeing, vacancies, performance, and succession].
- Decisions or support required from central leadership: [Request, recommendation, cost, impact, and deadline].
TEXT,
                'service_notes' => <<<'TEXT'
CAMPUS OPERATIONS & GOVERNANCE
- Services and programs: [Schedule reliability, quality, response, guest experience, follow-up, and safeguarding].
- Ministry performance: [Key result and status for every active ministry or department].
- Staffing and volunteers: [Headcount, vacancies, attendance, capacity, training, conduct, and wellbeing].
- Stewardship: [Giving trend reference, expenditure against approved budget, cash controls, procurement, and asset use].
- Facilities: [Building condition, maintenance, utilities, accessibility, cleanliness, security, fire/safety, and planned works].
- Technology and data: [Systems availability, internet, media, access control, data accuracy, privacy, and cybersecurity concerns].
- Governance and compliance: [Required meetings, registers, approvals, policies, incidents, licenses, insurance, and audit actions].
- Community and stakeholder relationships: [Partners, authorities, neighborhood concerns, and commitments].
TEXT,
                'issues' => <<<'TEXT'
CAMPUS RISK & ESCALATION REGISTER
- Critical ministry or pastoral risk: [Summary, people affected, control, owner, and review date].
- Staffing/volunteer capacity risk: [Gap, service impact, interim cover, and requested support].
- Financial or control exception: [Amount/process, cause, corrective control, and approval required].
- Facility, safety, security, or compliance risk: [Severity, immediate action, formal reporting status, and resolution cost/date].
- Reputational or community concern: [Stakeholders, current response, communication owner, and escalation].
- Central-office dependency: [Decision, funding, specialist support, policy clarification, or shared service required].
TEXT,
                'plans' => <<<'TEXT'
CAMPUS 30-DAY ACTION PLAN
- Mission and ministry priorities: [Three outcomes, KPIs, owners, and milestone dates].
- People plan: [Recruitment, volunteer mobilization, training, performance, wellbeing, and succession].
- Pastoral care plan: [Priority follow-ups, referral pathways, safeguarding, and closure targets].
- Stewardship plan: [Budget controls, approvals, giving engagement, procurement, and asset actions].
- Facilities plan: [Preventive maintenance, repairs, safety checks, vendors, cost, and completion dates].
- Governance plan: [Meetings, reports, policy actions, audits, compliance deadlines, and evidence owners].
- Evidence to attach: Campus scorecard, attendance data, ministry reports, approved finance summary, risk register, maintenance log, and action tracker.
TEXT,
                'actions' => "Campus lead: [Name] | Due: [Date] | Validate the campus scorecard and all ministry exception statuses\nCampus lead: [Name] | Due: [Date] | Complete or assign every overdue pastoral and member follow-up\nCampus administrator: [Name] | Due: [Date] | Resolve high-priority facility, safety, compliance, and asset actions\nFinance owner: [Name] | Due: [Date] | Reconcile budget exceptions and obtain required approvals\nPeople owner: [Name] | Due: [Date] | Confirm staffing, volunteer coverage, training, and succession actions\nCentral leadership: [Name] | Due: [Date] | Decide the campus requests and communicate approved next steps",
                'metrics' => [0, 0, 0, 0],
            ],
            [
                'name' => 'Pastoral Care & Member Wellbeing Brief',
                'title' => 'Pastoral Care & Member Wellbeing Brief - Week of '.$weekStart->format('M d, Y'),
                'type' => 'pastoral',
                'priority' => 'urgent',
                'icon' => 'hand-heart',
                'tone' => 'bg-rose-50 text-rose-600 ring-rose-100',
                'cadence' => 'Weekly',
                'audience' => 'Authorized pastoral team',
                'period_start' => $weekStart->toDateString(),
                'period_end' => $weekEnd->toDateString(),
                'description' => 'A privacy-conscious pastoral care brief for triage, ownership, referrals, wellbeing trends, safeguarding escalation, and timely follow-up.',
                'sections' => ['Care overview', 'Triage', 'Follow-ups', 'Referrals', 'Safeguarding', 'Team capacity', 'Next review'],
                'summary' => <<<'TEXT'
PASTORAL CARE OVERVIEW — RESTRICTED
Record only information necessary for authorized pastoral coordination. Do not include detailed medical, counseling, safeguarding, or personal information that belongs in a restricted case-management record.

- Overall care demand: [New, active, completed, overdue, and escalated cases].
- Priority distribution: [Critical / High / Routine, using internal case references rather than sensitive details].
- Main care categories: [Bereavement, illness, family, practical support, counseling, prayer, safeguarding, absence, or other].
- Response performance: [Average first response, overdue follow-ups, and unresolved handoffs].
- Positive outcomes: [Cases stabilized/closed, successful referrals, restored contact, or practical support completed].
- Emerging wellbeing pattern: [Non-identifying trend requiring ministry or leadership attention].
- Immediate leadership attention required: [Restricted summary, authority needed, and deadline].
TEXT,
                'service_notes' => <<<'TEXT'
CARE COORDINATION & FOLLOW-UP
- New care contacts: [Count, priority, source, assigned owner, and response status].
- Active follow-ups: [Count due, completed, overdue, and reassigned].
- Visitation and practical support: [Planned/completed counts, owner, safety considerations, and next contact].
- Counseling and specialist referrals: [Referral type, consent/status, and follow-up date—do not record confidential session content].
- Prayer and ministry support: [Non-sensitive summary, response provided, and next step].
- Member absence/re-engagement: [People contacted, response, care need, and ministry handoff].
- Care team health: [Availability, caseload pressure, supervision, debriefing, and training needs].
- Documentation quality: [Consent, case reference, notes, owner, next date, and closure reason complete].
TEXT,
                'issues' => <<<'TEXT'
PASTORAL RISKS & ESCALATIONS — RESTRICTED
- Immediate safety or safeguarding concern: Follow the church safeguarding/emergency policy first; record only the case reference, escalation status, and authorized owner here.
- High-risk or overdue care case: [Reference, risk level, responsible pastor, required action, and deadline].
- Consent, privacy, boundary, or record-access concern: [Issue, temporary control, and safeguarding/data owner].
- Specialist referral gap: [Support required, referral route, cost/availability constraint, and interim pastoral plan].
- Care team capacity concern: [Caseload, unavailable roles, supervision need, and requested support].
- Practical assistance request: [Category, approved process, responsible ministry, and decision required].
TEXT,
                'plans' => <<<'TEXT'
CARE PLAN FOR THE NEXT 7 DAYS
- Critical cases: Confirm authorized owner, contact frequency, escalation route, and next review time.
- Overdue follow-ups: Reassign where necessary and set a verified completion deadline.
- Visits and calls: Confirm schedule, paired-working/safety needs, consent, transport, and documentation owner.
- Referrals: Confirm consent, provider/ministry handoff, pastoral continuity, and review date.
- Member re-engagement: Coordinate with ministry/group leaders using only necessary information.
- Care team support: Schedule supervision, debriefing, prayer, training, and workload review.
- Evidence to attach: Use only appropriately restricted case summaries, referral confirmations, or approved care logs; avoid unnecessary sensitive documents.
TEXT,
                'actions' => "Care lead: [Name] | Due: [Date/time] | Assign an authorized owner and next-contact date to every open case\nCare lead: [Name] | Due: [Date/time] | Complete or formally reassign every overdue care follow-up\nSafeguarding lead: [Name] | Due: [Date/time] | Review all safeguarding or immediate-safety escalations under policy\nPastoral owner: [Name] | Due: [Date] | Confirm consent and completion status for every external or specialist referral\nTeam lead: [Name] | Due: [Date] | Review caseload, supervision, wellbeing, and coverage for the pastoral care team\nReviewer: [Name] | Due: [Date] | Confirm restricted records are complete, appropriately stored, and access-controlled",
                'metrics' => [0, 0, 0, 0],
            ],
            [
                'name' => 'Incident, Safety & Safeguarding Report',
                'title' => 'Incident, Safety & Safeguarding Report - '.$today->format('M d, Y'),
                'type' => 'incident',
                'priority' => 'urgent',
                'icon' => 'shield-alert',
                'tone' => 'bg-orange-50 text-orange-700 ring-orange-100',
                'cadence' => 'As required',
                'audience' => 'Authorized response team',
                'period_start' => $today->toDateString(),
                'period_end' => $today->toDateString(),
                'description' => 'A controlled incident framework for immediate response, factual chronology, people affected, notifications, evidence, risk controls, investigation, and closure.',
                'sections' => ['Immediate response', 'Facts & timeline', 'People affected', 'Notifications', 'Risk controls', 'Investigation', 'Closure'],
                'summary' => <<<'TEXT'
INCIDENT SUMMARY — RESTRICTED
If anyone is in immediate danger, contact emergency services and follow church safeguarding, safety, security, and legal reporting procedures before completing this report.

- Incident reference and classification: [Reference | Safety / Safeguarding / Security / Medical / Facility / Data / Conduct / Other].
- Date, time, and exact location: [Verified details].
- Reported by and incident lead: [Names/roles and contact route].
- Factual summary: [What was observed or reported; separate facts from assumptions].
- People affected: [Use minimum necessary identifiers and record support provided].
- Immediate severity: [Critical / High / Moderate / Low] and rationale.
- Current status: [Open / Stabilized / Under investigation / Referred / Closed].
- Immediate actions taken: [Emergency response, first aid, area isolation, safeguarding action, system shutdown, or other control].
- External/internal notifications made: [Authority/leader, time, person who notified, and reference].
TEXT,
                'service_notes' => <<<'TEXT'
FACTUAL TIMELINE & RESPONSE LOG
- [Time] — Incident observed/reported by [Person/role]; factual observation: [Details].
- [Time] — Immediate response initiated by [Person/role]; action: [Details].
- [Time] — People moved, supported, treated, contacted, or safeguarded: [Minimum necessary summary].
- [Time] — Area, equipment, account, record, or evidence secured by [Owner].
- [Time] — Leader, safeguarding lead, insurer, authority, emergency service, parent/guardian, or data officer notified: [Reference].
- Witnesses: [Names/contact held in restricted record; statements requested/received].
- Evidence preserved: [Photos, CCTV, forms, logs, equipment, emails, messages, or medical/authority reference; storage owner].
- Service/ministry impact: [Cancellation, delay, closure, alternative arrangement, and communication].
TEXT,
                'issues' => <<<'TEXT'
ONGOING RISK, COMPLIANCE & SUPPORT
- Remaining risk: [Hazard/threat, people exposed, likelihood, impact, and temporary control].
- Safeguarding concern: Follow policy and mandatory-reporting requirements; state only escalation status and authorized owner.
- Medical/legal/insurance/regulatory action: [Required filing, responsible person, reference, and deadline].
- Evidence integrity or privacy concern: [Access restriction, retention, chain of custody, and data owner].
- Communication/reputation risk: [Approved spokesperson, audience, holding message status, and restrictions].
- Operational continuity need: [Alternative room/system/team, duration, cost, and approval].
- Specialist support required: [Safeguarding, legal, medical, security, facilities, HR, IT, counseling, or insurer].
TEXT,
                'plans' => <<<'TEXT'
INVESTIGATION, RECOVERY & CLOSURE PLAN
- Investigation owner and scope: [Questions to answer, evidence required, interviews, and target date].
- Corrective controls: [Immediate, short-term, and permanent actions with owners].
- Support for affected people: [Pastoral, medical, safeguarding, practical, or specialist follow-up].
- Reporting obligations: [Internal policy, authority, insurer, regulator, parent/guardian, or data notification and deadline].
- Service recovery: [Area/equipment/system reopening criteria, approval owner, and communication].
- Lessons learned review: [Meeting date, participants, root-cause method, and policy/training updates].
- Closure criteria: [Risks controlled, actions complete, reports filed, people followed up, evidence retained, and authorized sign-off].
TEXT,
                'actions' => "Incident lead: [Name] | Due: [Date/time] | Confirm immediate safety, safeguarding, medical, and security controls\nAuthorized owner: [Name] | Due: [Date/time] | Complete all required internal and external notifications\nEvidence owner: [Name] | Due: [Date/time] | Secure records and evidence with correct access and retention controls\nInvestigator: [Name] | Due: [Date] | Complete the factual investigation and root-cause findings\nAction owner: [Name] | Due: [Date] | Implement temporary and permanent corrective controls\nApprover: [Name] | Due: [Date] | Verify follow-up, lessons learned, reporting obligations, and formal closure",
                'metrics' => [0, 0, 0, 0],
            ],
            [
                'name' => 'Strategic Initiative & Transformation Report',
                'title' => 'Strategic Initiative & Transformation Report - Q'.now()->quarter.' '.now()->year,
                'type' => 'strategic',
                'priority' => 'normal',
                'icon' => 'target',
                'tone' => 'bg-cyan-50 text-cyan-700 ring-cyan-100',
                'cadence' => 'Quarterly',
                'audience' => 'Executive sponsors',
                'period_start' => $quarterStart->toDateString(),
                'period_end' => $quarterEnd->toDateString(),
                'description' => 'An executive initiative review connecting strategic objectives to measurable outcomes, milestones, adoption, benefits, budget, dependencies, risks, and sponsor decisions.',
                'sections' => ['Strategic case', 'Outcomes & KPIs', 'Milestones', 'Benefits', 'Resources', 'Risks', 'Sponsor decisions'],
                'summary' => <<<'TEXT'
STRATEGIC INITIATIVE OVERVIEW
- Initiative, executive sponsor, accountable owner, and delivery team: [Names/roles].
- Strategic objective supported: [Church strategy, mission outcome, or operating priority].
- Problem/opportunity statement: [Why this initiative exists and who benefits].
- Overall status: [Green / Amber / Red] — explain against approved scope, schedule, cost, quality, and outcomes.
- Reporting-period achievements: [Completed outcomes and evidence, not only activity].
- KPI/benefit performance: [Baseline, target, current result, variance, and forecast].
- Milestone status: [Completed, due next, delayed, and recovery date].
- Budget/resource position: [Approved, committed, spent, forecast, people capacity, and variance].
- Executive decision required: [Decision, recommendation, alternatives, trade-offs, and deadline].
TEXT,
                'service_notes' => <<<'TEXT'
DELIVERY, ADOPTION & BENEFITS
- Workstream status: [For each workstream: owner, deliverable, completion %, evidence, and next milestone].
- Scope: [Approved changes, pending change requests, out-of-scope requests, and impact].
- Schedule: [Critical path, dependencies, delayed milestones, and recovery confidence].
- Resources: [Team capacity, vendor/partner status, specialist gaps, and procurement].
- Stakeholder engagement: [Leaders, ministries, campuses, members, communications, feedback, and concerns].
- Adoption/readiness: [Training, process, policy, data, technology, communications, support, and readiness score].
- Benefits realization: [Expected benefit, current evidence, owner, measurement source, and realization date].
- Governance: [Steering meetings, decisions, change control, documentation, assurance, and audit trail].
TEXT,
                'issues' => <<<'TEXT'
STRATEGIC RISKS, ISSUES & DEPENDENCIES
- Risk/issue: [Description] | Probability: [L/M/H] | Impact: [L/M/H] | Owner: [Name] | Mitigation: [Action/date].
- Schedule or critical-path pressure: [Cause, affected milestone, options, and recommended recovery].
- Budget/resource pressure: [Variance, capacity gap, alternatives, sponsor action required].
- Scope or quality concern: [Requested change, benefit/cost impact, and change-control status].
- Adoption or stakeholder resistance: [Affected audience, evidence, intervention, and owner].
- Technology, data, compliance, safeguarding, or vendor dependency: [Current control and escalation].
- Decision overdue: [Decision, accountable sponsor, delay impact, and final decision date].
TEXT,
                'plans' => <<<'TEXT'
NEXT DELIVERY PERIOD
- Outcomes to complete: [Specific deliverable, acceptance criteria, owner, and date].
- Milestones: [Next three milestones, dependencies, readiness, and evidence required].
- Benefits: [Measure to improve, intervention, data source, owner, and expected movement].
- Adoption: [Stakeholder engagement, communications, training, pilot, rollout, and support actions].
- Resources: [People, budget, vendor, procurement, facility, or system requirements].
- Governance: [Sponsor decisions, steering reviews, assurance gates, change requests, and reporting dates].
- Evidence to attach: Business case, roadmap, KPI dashboard, budget summary, risk log, decision log, change requests, and implementation plan.
TEXT,
                'actions' => "Initiative owner: [Name] | Due: [Date] | Validate KPI results, benefits evidence, and forecast against source data\nWorkstream owner: [Name] | Due: [Date] | Complete the next milestone and its documented acceptance criteria\nRisk owner: [Name] | Due: [Date] | Implement mitigation for every high strategic risk or issue\nSponsor: [Name] | Due: [Date] | Decide all overdue scope, funding, resource, or policy requests\nAdoption owner: [Name] | Due: [Date] | Deliver stakeholder communication, training, and readiness actions\nGovernance owner: [Name] | Due: [Date] | Update the roadmap, budget, risk, change, and decision logs",
                'metrics' => [0, 0, 0, 0],
            ],
        ];
    }

    private function reportSettings(Request $request): array
    {
        $settings = data_get($request->user()?->account_settings, 'leadership_reports');
        $legacyChurchSettings = data_get(Church::query()->find($this->churchId($request))?->settings, 'leadership_reports', []);

        if (! is_array($settings)) {
            $settings = is_array($legacyChurchSettings) ? $legacyChurchSettings : [];
        }

        return [
            'default_reviewer_id' => $settings['default_reviewer_id'] ?? null,
            'weekly_due_day' => $settings['weekly_due_day'] ?? 'friday',
            'auto_reminders' => (bool) ($settings['auto_reminders'] ?? true),
            'require_action_items' => (bool) ($settings['require_action_items'] ?? true),
            'escalation_hours' => (int) ($settings['escalation_hours'] ?? 72),
            'reviewer_role_ids' => $this->reviewerRoleIds($request),
            'updated_by' => $settings['updated_by'] ?? 'System default',
            'updated_at' => $settings['updated_at'] ?? null,
        ];
    }

    private function reviewerRoleIds(Request $request): array
    {
        $roleIds = data_get(
            Church::query()->find($this->churchId($request))?->settings,
            'leadership_reports.reviewer_role_ids',
            [],
        );

        return collect(is_array($roleIds) ? $roleIds : [])
            ->filter(fn ($roleId): bool => is_numeric($roleId))
            ->map(fn ($roleId): int => (int) $roleId)
            ->unique()
            ->values()
            ->all();
    }

    private function relationships(Request $request): array
    {
        $base = $this->visibleReports($request);

        return [
            ['label' => 'Reporter -> Reviewer', 'count' => (clone $base)->whereNotNull('assigned_to')->count()],
            ['label' => 'Campus -> Headquarters', 'count' => (clone $base)->whereNotNull('campus_id')->count()],
            ['label' => 'Ministry -> Leader', 'count' => (clone $base)->whereNotNull('ministry_id')->count()],
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

    private function hasBroadLeadershipCampusScope(Request $request): bool
    {
        $user = $request->user();

        return $user?->isSuperAdministrator()
            || $user?->campus_id === null
            || $user?->hasAnyRole(['Church Administrator', 'Senior Pastor'])
            || $user?->hasPermission('manage campuses');
    }

    private function canViewAllLeadershipReports(Request $request): bool
    {
        return $this->hasBroadLeadershipCampusScope($request);
    }

    private function canReviewLeadershipReports(Request $request): bool
    {
        $user = $request->user();

        return $user?->isSuperAdministrator() || (bool) $user?->hasPermission('review leadership reports');
    }

    private function canManageReviewerRoles(Request $request): bool
    {
        $user = $request->user();

        return $user?->isSuperAdministrator() || (bool) $user?->hasPermission('manage settings');
    }

    private function decodeReportId(string $opaqueId): ?int
    {
        return (new LeadershipReport)->resolveRouteBinding($opaqueId)?->id;
    }
}
