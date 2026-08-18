<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Asset;
use App\Models\CareTask;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Event;
use App\Models\AttendanceSession;
use App\Models\BookstoreOrder;
use App\Models\CommunicationCampaign;
use App\Models\CounsellingBooking;
use App\Models\Family;
use App\Models\Facility;
use App\Models\FinancialAssistanceRequest;
use App\Models\Program;
use App\Models\SupportTicket;
use App\Models\Workflow;
use App\Models\LeadershipReport;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Str;
use RuntimeException;

final class ChurchCopilotService
{
    public function __construct(private readonly AiProviderClient $client) {}

    public function ask(User $user, string $question): array
    {
        $intent = $this->intent($question);
        $context = match ($intent) {
            'capabilities' => ['title' => 'Copilot capabilities'],
            'executive_summary' => $this->appOverview($user),
            'module_analysis' => $this->moduleAnalysis($user),
            'event_success' => $this->eventSuccess($user, $question),
            'membership_analysis' => $this->membershipAnalysis($user),
            'member_behavior' => $this->memberBehavior($user, $question),
            'attendance_summary' => $this->attendanceSummary($user, $question),
            'giving_trends' => $this->givingTrends($user, $question),
            'reports_attention' => $this->reportsAttention($user),
            default => ['title' => 'Supported report and analysis areas', 'available_analyses' => $this->suggestedQuestions(), 'note' => 'This Copilot is limited to reports, metrics, trends, comparisons, and analysis. No operational records were supplied for this request.'],
        };
        $answer = match ($intent) {
            'capabilities' => "## I can help with church reports and analysis\n\n- Build an executive summary from available metrics\n- Analyze membership and attendance trends\n- Compare giving by campus and period\n- Review leadership reports, priorities, and due dates\n- Explain patterns, changes, and areas needing attention\n\nI only use verified, permission-filtered records. I do not create, edit, send, or manage operational records.",
            'unknown' => "## Reports and analytics only\n\nI can help with executive summaries, metrics, trends, comparisons, and leadership-report analysis. I cannot handle operational requests or unrelated general questions here.",
            default => $this->client->answer($this->church($user), $question, $context),
        };
        $answer = trim($answer) !== '' ? $answer : $this->fallbackAnswer($intent, $context);

        return ['intent' => $intent, 'answer' => $answer, 'context' => $context, 'source_count' => $this->countContext($context)];
    }

    public function suggestedQuestions(): array
    {
        return ['Give me an executive report for the church.', 'Analyze all modules and show the main metrics.', 'Analyze event success and attendance.', 'Analyze member behavior and engagement.', 'Analyze attendance trends for the last 31 days.', 'Compare giving trends by campus.', 'Which leadership reports need attention?', 'What are the main trends and risks in our reports?'];
    }

    private function intent(string $question): string
    {
        $q = Str::lower($question);

        return match (true) {
            str_contains($q, 'what can you do') || str_contains($q, 'help') || str_contains($q, 'capabilit') => 'capabilities',
            str_contains($q, 'all module') || str_contains($q, 'every module') || str_contains($q, 'whole system') || str_contains($q, 'system-wide') || str_contains($q, 'everything') => 'module_analysis',
            str_contains($q, 'event') && (str_contains($q, 'success') || str_contains($q, 'performance') || str_contains($q, 'outcome') || str_contains($q, 'attendance')) => 'event_success',
            str_contains($q, 'behavior') || str_contains($q, 'behaviour') || str_contains($q, 'engagement') || str_contains($q, 'participation') => 'member_behavior',
            str_contains($q, 'executive') || str_contains($q, 'overview') || str_contains($q, 'dashboard') || str_contains($q, 'church status') => 'executive_summary',
            str_contains($q, 'member') || str_contains($q, 'membership') || str_contains($q, 'people') => 'membership_analysis',
            str_contains($q, 'attendance') || str_contains($q, 'attended') || str_contains($q, 'check-in') || str_contains($q, 'check in') => 'attendance_summary',
            str_contains($q, 'giving') || str_contains($q, 'donation') || (str_contains($q, 'campus') && str_contains($q, 'trend')) => 'giving_trends',
            str_contains($q, 'report') || str_contains($q, 'analysis') || str_contains($q, 'analyz') || str_contains($q, 'trend') || str_contains($q, 'metric') || str_contains($q, 'kpi') || str_contains($q, 'insight') || str_contains($q, 'risk') => 'reports_attention',
            default => 'unknown',
        };
    }

    private function membershipAnalysis(User $user): array
    {
        $this->authorizeAny($user, ['manage members', 'view reports']);
        $members = $this->memberScope($user);
        $joined = (clone $members)->where('joined_at', '>=', now()->subMonths(12)->startOfMonth())->get(['joined_at'])->groupBy(fn (Member $member) => $member->joined_at?->format('Y-m'))->map->count();

        return ['title' => 'Membership analysis', 'total_members' => $members->count(), 'status_breakdown' => (clone $members)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all(), 'joined_by_month' => $joined->all(), 'period' => 'last 12 months'];
    }

    private function memberBehavior(User $user, string $question = ''): array
    {
        $this->authorizeAny($user, ['manage members', 'manage attendance', 'view reports']);
        $members = $this->memberScope($user)->get(['id', 'status']);
        $attendance = AttendanceRecord::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('service_date', '>=', now()->subDays(90)->toDateString())->whereNotNull('member_id')->selectRaw('member_id, COUNT(*) as visits')->groupBy('member_id')->pluck('visits', 'member_id');

        return ['title' => 'Member behavior and engagement analysis', 'period' => 'last 90 days', 'members_total' => $members->count(), 'status_breakdown' => $members->groupBy('status')->map->count()->all(), 'engagement' => ['no_recorded_attendance' => $members->whereNotIn('id', $attendance->keys())->count(), 'occasional_1_to_3_visits' => $attendance->filter(fn ($visits): bool => $visits >= 1 && $visits <= 3)->count(), 'regular_4_or_more_visits' => $attendance->filter(fn ($visits): bool => $visits >= 4)->count()], 'attendance_visits_total' => (int) $attendance->sum()];
    }

    private function eventSuccess(User $user, string $question = ''): array
    {
        $this->authorizeAny($user, ['manage events', 'manage attendance', 'view reports']);
        $sessions = AttendanceSession::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('opens_at', '>=', now()->subMonths(12))->with(['eventSession.event', 'records'])->latest('opens_at')->limit(100)->get();
        $rows = $sessions->map(function (AttendanceSession $session): array {
            $expected = (int) ($session->expected_attendance ?? 0);
            $actual = $session->records->whereNotNull('member_id')->unique('member_id')->count();
            return ['event' => $session->eventSession?->event?->title ?? $session->title, 'date' => $session->opens_at?->toDateString(), 'expected' => $expected, 'attended' => $actual, 'attendance_rate' => $expected > 0 ? round($actual / $expected * 100, 1) : null, 'status' => $session->status];
        })->values()->all();

        return ['title' => 'Event success and attendance analysis', 'period' => 'last 12 months', 'events_analyzed' => count($rows), 'sessions' => $rows];
    }

    private function moduleAnalysis(User $user): array
    {
        $modules = [
            'members' => [Member::class, ['manage members', 'view reports']],
            'families' => [Family::class, ['manage members', 'view reports']],
            'events' => [Event::class, ['manage events', 'view reports']],
            'programs' => [Program::class, ['manage events', 'view reports']],
            'attendance_sessions' => [AttendanceSession::class, ['manage attendance', 'view reports']],
            'attendance_records' => [AttendanceRecord::class, ['manage attendance', 'view reports']],
            'giving' => [Donation::class, ['view finance', 'manage finance', 'view ministry finance']],
            'leadership_reports' => [LeadershipReport::class, ['view leadership reports', 'view reports']],
            'ministries' => [Ministry::class, ['manage ministries', 'view reports']],
            'volunteers' => [Volunteer::class, ['manage volunteers', 'view reports']],
            'assets' => [Asset::class, ['manage assets', 'view reports']],
            'facilities' => [Facility::class, ['manage facilities', 'view reports']],
            'communications' => [CommunicationCampaign::class, ['manage communications', 'view reports']],
            'care_tasks' => [CareTask::class, ['manage members', 'view reports']],
            'prayer_requests' => [PrayerRequest::class, ['manage prayer', 'view reports']],
            'counselling' => [CounsellingBooking::class, ['manage counselling', 'view reports']],
            'financial_assistance' => [FinancialAssistanceRequest::class, ['manage financial assistance', 'view reports']],
            'support_tickets' => [SupportTicket::class, ['manage support', 'view reports']],
            'workflows' => [Workflow::class, ['manage workflows', 'view reports']],
            'bookstore_orders' => [BookstoreOrder::class, ['manage bookstore', 'view reports']],
        ];
        $result = [];
        foreach ($modules as $name => [$model, $permissions]) {
            if ($user->isSuperAdministrator() || $user->hasAnyPermission($permissions)) {
                $result[$name] = $model::query()->where('church_id', $user->church_id)->count();
            }
        }

        return ['title' => 'Cross-module church analytics overview', 'modules' => $result, 'note' => 'Only modules visible to the current user are included. Counts are read-only inventory metrics; ask for a specific module to receive deeper trend analysis.'];
    }

    private function totalMembers(User $user): array
    {
        $this->authorizeAny($user, ['manage members']);

        return ['title' => 'Total members', 'total_members' => $this->memberScope($user)->count()];
    }

    private function appOverview(User $user): array
    {
        $counts = [];
        if ($user->isSuperAdministrator() || $user->hasAnyPermission(['manage members'])) {
            $counts['members'] = $this->memberScope($user)->count();
        }
        if ($user->isSuperAdministrator() || $user->hasAnyPermission(['manage attendance'])) {
            $counts['attendance_records_last_30_days'] = AttendanceRecord::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('service_date', '>=', now()->subDays(30)->toDateString())->count();
        }
        if ($user->isSuperAdministrator() || $user->hasAnyPermission(['view finance', 'manage finance', 'view ministry finance'])) {
            $counts['giving_last_12_months'] = Donation::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('received_at', '>=', now()->subMonths(12)->startOfMonth())->sum('amount');
        }

        return ['title' => 'Permission-filtered executive report metrics', 'counts' => $counts, 'note' => 'Metrics are limited to reportable modules the current user can access.'];
    }

    private function upcomingEvents(User $user): array
    {
        $this->authorizeAny($user, ['manage events']);
        $rows = Event::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->whereBetween('starts_at', [now(), now()->addDays(60)])->with('campus')->orderBy('starts_at')->limit(50)->get()->map(fn (Event $event) => ['title' => $event->title, 'starts_at' => $event->starts_at?->toDateTimeString(), 'venue' => $event->venue, 'campus' => $event->campus?->name, 'status' => $event->status])->all();

        return ['title' => 'Upcoming events in the next 60 days', 'events' => $rows];
    }

    private function attendanceSummary(User $user, string $question = ''): array
    {
        $this->authorizeAny($user, ['manage attendance']);
        $thisMonth = str_contains(Str::lower($question), 'this month') || str_contains(Str::lower($question), 'current month') || str_contains(Str::lower($question), 'this moth');
        $from = $thisMonth ? now()->startOfMonth()->toDateString() : now()->subDays(31)->toDateString();
        $label = $thisMonth ? 'this month' : 'the last 31 days';
        $rows = AttendanceRecord::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('service_date', '>=', $from)->get()->groupBy(fn (AttendanceRecord $record) => (string) $record->service_date)->sortKeysDesc()->take(12)->map(fn ($records, $date) => ['date' => $date, 'records' => $records->count(), 'members_present' => $records->whereNotNull('member_id')->unique('member_id')->count()])->values()->all();

        return ['title' => 'Attendance summary for '.$label, 'rows' => $rows, 'period' => $label];
    }

    private function prayerRequests(User $user): array
    {
        $this->authorizeAny($user, ['manage prayer']);
        $rows = PrayerRequest::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->whereNotIn('status', ['resolved', 'closed'])->latest()->limit(50)->get()->map(fn (PrayerRequest $request) => ['title' => $request->title, 'status' => $request->status, 'confidential' => (bool) $request->is_confidential, 'followed_up_at' => $request->followed_up_at?->toDateString()])->all();

        return ['title' => 'Prayer requests requiring follow-up', 'requests' => $rows, 'note' => 'Confidential request details are intentionally excluded.'];
    }

    private function careTasks(User $user): array
    {
        $this->authorizeAny($user, ['manage members', 'manage communications']);
        $rows = CareTask::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->whereNotIn('status', ['resolved', 'completed', 'closed'])->with(['member', 'campus', 'assignedUser'])->orderBy('due_at')->limit(50)->get()->map(fn (CareTask $task) => ['type' => $task->type, 'priority' => $task->priority, 'status' => $task->status, 'due_at' => $task->due_at?->toDateString(), 'member' => $task->member ? $task->member->first_name.' '.$task->member->last_name : null, 'campus' => $task->campus?->name, 'assigned_to' => $task->assignedUser?->name])->all();

        return ['title' => 'Open pastoral care tasks', 'tasks' => $rows];
    }

    private function volunteers(User $user): array
    {
        $this->authorizeAny($user, ['manage volunteers', 'manage ministries']);
        $rows = Volunteer::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('status', 'active')->with(['member', 'ministry'])->limit(100)->get()->map(fn (Volunteer $volunteer) => ['name' => $volunteer->member ? $volunteer->member->first_name.' '.$volunteer->member->last_name : null, 'ministry' => $volunteer->ministry?->name, 'role' => $volunteer->role])->all();

        return ['title' => 'Active volunteers', 'volunteers' => $rows];
    }

    private function assets(User $user): array
    {
        $this->authorizeAny($user, ['manage assets', 'manage facilities']);
        $rows = Asset::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where(fn ($q) => $q->whereIn('condition', ['poor', 'damaged', 'needs_repair'])->orWhereIn('status', ['maintenance', 'unavailable']))->with(['category', 'campus'])->limit(50)->get()->map(fn (Asset $asset) => ['name' => $asset->name, 'status' => $asset->status, 'condition' => $asset->condition, 'category' => $asset->category?->name, 'campus' => $asset->campus?->name])->all();

        return ['title' => 'Assets needing attention', 'assets' => $rows];
    }

    private function ministries(User $user): array
    {
        $this->authorizeAny($user, ['manage ministries']);
        $rows = Ministry::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->with('campus')->orderBy('name')->limit(100)->get()->map(fn (Ministry $ministry) => ['name' => $ministry->name, 'status' => $ministry->status ?? null, 'campus' => $ministry->campus?->name])->all();

        return ['title' => 'Church ministries', 'ministries' => $rows];
    }

    private function fallbackAnswer(string $intent, array $context): string
    {
        return match ($intent) {
            default => 'I could not generate a report from the verified records. Try an executive summary, trend analysis, comparison, or leadership-report question.',
        };
    }

    private function missedServices(User $user): array
    {
        $this->authorizeAny($user, ['manage members', 'manage attendance']);
        $scope = $this->memberScope($user);
        $dates = AttendanceRecord::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->select('service_date')->distinct()->latest('service_date')->limit(3)->pluck('service_date');
        if ($dates->count() < 3) {
            return ['title' => 'Missed services', 'service_dates' => $dates->map(fn ($d) => (string) $d)->values()->all(), 'members' => [], 'note' => 'Fewer than three recorded service dates are available.'];
        }
        $attended = AttendanceRecord::query()->whereIn('service_date', $dates)->whereNotNull('member_id')->pluck('member_id')->unique();

        return ['title' => 'Members absent from each of the last three recorded services', 'service_dates' => $dates->map(fn ($d) => (string) $d)->values()->all(), 'members' => $scope->whereNotIn('id', $attended)->limit(100)->get()->map(fn (Member $m) => ['name' => $m->first_name.' '.$m->last_name, 'campus' => $m->campus?->name, 'status' => $m->status])->all()];
    }

    private function givingTrends(User $user, string $question = ''): array
    {
        $this->authorizeAny($user, ['manage finance', 'view finance', 'view ministry finance']);
        $period = $this->reportPeriod($question);
        $rows = Donation::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->whereBetween('received_at', [$period['from'], $period['to']])->with('campus')->get()->groupBy(fn (Donation $d) => $d->campus?->name ?? 'Unassigned')->map(fn ($items, $campus) => ['campus' => $campus, 'total' => round((float) $items->sum('amount'), 2), 'monthly_average' => round((float) $items->sum('amount') / $period['months'], 2), 'donations' => $items->count()])->values()->all();

        return ['title' => 'Giving trends by campus for '.$period['label'], 'rows' => $rows, 'period' => $period['label']];
    }

    private function reportPeriod(string $question): array
    {
        $q = Str::lower($question);
        if (str_contains($q, 'last month')) {
            $from = now()->subMonth()->startOfMonth();
            return ['from' => $from, 'to' => $from->copy()->endOfMonth(), 'months' => 1, 'label' => 'last month'];
        }
        if (str_contains($q, 'this month') || str_contains($q, 'current month') || str_contains($q, 'this moth')) {
            return ['from' => now()->startOfMonth(), 'to' => now(), 'months' => 1, 'label' => 'this month'];
        }
        if (str_contains($q, 'this year') || str_contains($q, 'current year')) {
            return ['from' => now()->startOfYear(), 'to' => now(), 'months' => max(1, now()->month), 'label' => 'this year'];
        }

        return ['from' => now()->subMonths(12)->startOfMonth(), 'to' => now(), 'months' => 12, 'label' => 'the last 12 months'];
    }

    private function reportsAttention(User $user): array
    {
        $this->authorizeAny($user, ['view leadership reports', 'view reports']);
        $rows = LeadershipReport::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->whereIn('status', ['submitted', 'under_review', 'returned'])->where(fn ($q) => $q->whereIn('priority', ['high', 'urgent'])->orWhere('due_at', '<', now()))->with('campus')->latest()->limit(50)->get()->map(fn (LeadershipReport $r) => ['title' => $r->title, 'campus' => $r->campus?->name, 'status' => $r->status, 'priority' => $r->priority, 'due_at' => $r->due_at?->toDateString()])->all();

        return ['title' => 'Leadership reports requiring attention', 'reports' => $rows];
    }

    private function newMemberFollowup(User $user): array
    {
        $this->authorizeAny($user, ['manage members', 'manage communications']);
        $members = $this->memberScope($user)->where('joined_at', '>=', now()->subDays(30)->startOfDay())->whereIn('status', ['active', 'new', 'follow-up'])->with('campus')->latest('joined_at')->limit(100)->get()->map(fn (Member $m) => ['name' => $m->first_name.' '.$m->last_name, 'campus' => $m->campus?->name, 'joined_at' => $m->joined_at?->toDateString(), 'status' => $m->status])->all();

        return ['title' => 'New members joined in the last 30 days', 'members' => $members, 'suggested_action' => 'Review the list and approve any communication before sending.'];
    }

    private function memberScope(User $user)
    {
        return Member::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->with('campus');
    }

    private function broad(User $user): bool
    {
        return $user->isSuperAdministrator() || $user->campus_id === null || $user->hasAnyRole(['Church Administrator', 'Senior Pastor']) || $user->hasPermission('manage campuses');
    }

    private function authorizeAny(User $user, array $permissions): void
    {
        if (! $user->isSuperAdministrator() && ! $user->hasAnyPermission($permissions)) {
            throw new RuntimeException('You do not have permission to access the data needed for this question.');
        }
    }

    private function church(User $user): Church
    {
        return Church::query()->findOrFail($user->church_id);
    }

    private function countContext(array $context): int
    {
        return (int) ($context['source_count'] ?? ($context['total_members'] ?? (isset($context['counts']) ? array_sum($context['counts']) : collect(['members', 'rows', 'reports', 'events', 'requests', 'tasks', 'volunteers', 'assets', 'ministries'])->sum(fn (string $key): int => is_array($context[$key] ?? null) ? count($context[$key]) : 0))));
    }
}
