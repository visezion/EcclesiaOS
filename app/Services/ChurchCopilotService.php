<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Asset;
use App\Models\CareTask;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Event;
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
            'app_overview' => $this->appOverview($user),
            'total_members' => $this->totalMembers($user),
            'upcoming_events' => $this->upcomingEvents($user),
            'attendance_summary' => $this->attendanceSummary($user),
            'prayer_requests' => $this->prayerRequests($user),
            'care_tasks' => $this->careTasks($user),
            'volunteers' => $this->volunteers($user),
            'assets' => $this->assets($user),
            'ministries' => $this->ministries($user),
            'missed_services' => $this->missedServices($user),
            'giving_trends' => $this->givingTrends($user),
            'reports_attention' => $this->reportsAttention($user),
            'new_member_followup' => $this->newMemberFollowup($user),
            default => ['title' => 'General Copilot question', 'available_operations' => $this->suggestedQuestions(), 'note' => 'No church records were supplied for this general question. Do not invent app data or claim an action was completed.'],
        };
        $answer = match ($intent) {
            'capabilities' => "## I can help with church operations\n\n- Count and summarize members\n- Find members who missed recent services\n- Show giving trends by campus\n- Identify leadership reports needing attention\n- Create new-member follow-up lists\n\nI only use verified, permission-filtered records and I will explain when data is unavailable.",
            'total_members' => '## Total members\n\nYour permission-filtered church member count is **'.number_format((int) $context['total_members']).'**.',
            default => $this->client->answer($this->church($user), $question, $context),
        };
        $answer = trim($answer) !== '' ? $answer : $this->fallbackAnswer($intent, $context);

        return ['intent' => $intent, 'answer' => $answer, 'context' => $context, 'source_count' => $this->countContext($context)];
    }

    public function suggestedQuestions(): array
    {
        return ['How many members do we have in total?', 'What events are coming up?', 'Summarize attendance this month.', 'Which prayer requests need follow-up?', 'Which care tasks are overdue?', 'Show active volunteers by ministry.', 'Which assets need attention?', 'List our ministries.', 'Show giving trends by campus.', 'Which leadership reports need attention?', 'Create a follow-up list for new members.'];
    }

    private function intent(string $question): string
    {
        $q = Str::lower($question);

        return match (true) {
            str_contains($q, 'what can you do') || str_contains($q, 'help') || str_contains($q, 'capabilit') => 'capabilities',
            str_contains($q, 'overview') || str_contains($q, 'dashboard') || str_contains($q, 'church status') || str_contains($q, 'summary of the app') => 'app_overview',
            (str_contains($q, 'how many') || str_contains($q, 'total') || str_contains($q, 'count')) && (str_contains($q, 'member') || str_contains($q, 'people')) => 'total_members',
            (str_contains($q, 'event') || str_contains($q, 'calendar')) && (str_contains($q, 'upcoming') || str_contains($q, 'next') || str_contains($q, 'coming')) => 'upcoming_events',
            str_contains($q, 'attendance') || str_contains($q, 'attended') || str_contains($q, 'check-in') || str_contains($q, 'check in') => 'attendance_summary',
            str_contains($q, 'prayer') => 'prayer_requests',
            str_contains($q, 'care task') || str_contains($q, 'pastoral care') || str_contains($q, 'overdue task') => 'care_tasks',
            str_contains($q, 'volunteer') || str_contains($q, 'serving team') => 'volunteers',
            str_contains($q, 'asset') || str_contains($q, 'equipment') || str_contains($q, 'inventory') => 'assets',
            str_contains($q, 'ministr') || str_contains($q, 'departments') => 'ministries',
            str_contains($q, 'missed') || str_contains($q, 'absent') || str_contains($q, 'last three') => 'missed_services',
            str_contains($q, 'giving') || str_contains($q, 'donation') || (str_contains($q, 'campus') && str_contains($q, 'trend')) => 'giving_trends',
            str_contains($q, 'leadership') || (str_contains($q, 'report') && (str_contains($q, 'attention') || str_contains($q, 'overdue'))) => 'reports_attention',
            str_contains($q, 'new member') || str_contains($q, 'follow-up') || str_contains($q, 'follow up') => 'new_member_followup',
            default => 'unknown',
        };
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
        if ($user->isSuperAdministrator() || $user->hasAnyPermission(['manage events'])) {
            $counts['upcoming_events'] = Event::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('starts_at', '>=', now())->where('starts_at', '<=', now()->addDays(30))->count();
        }
        if ($user->isSuperAdministrator() || $user->hasAnyPermission(['manage ministries'])) {
            $counts['ministries'] = Ministry::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->count();
        }
        if ($user->isSuperAdministrator() || $user->hasAnyPermission(['manage attendance'])) {
            $counts['attendance_records_last_30_days'] = AttendanceRecord::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('service_date', '>=', now()->subDays(30)->toDateString())->count();
        }

        return ['title' => 'Permission-filtered church operations overview', 'counts' => $counts, 'note' => 'Counts are limited to modules the current user can access.'];
    }

    private function upcomingEvents(User $user): array
    {
        $this->authorizeAny($user, ['manage events']);
        $rows = Event::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->whereBetween('starts_at', [now(), now()->addDays(60)])->with('campus')->orderBy('starts_at')->limit(50)->get()->map(fn (Event $event) => ['title' => $event->title, 'starts_at' => $event->starts_at?->toDateTimeString(), 'venue' => $event->venue, 'campus' => $event->campus?->name, 'status' => $event->status])->all();

        return ['title' => 'Upcoming events in the next 60 days', 'events' => $rows];
    }

    private function attendanceSummary(User $user): array
    {
        $this->authorizeAny($user, ['manage attendance']);
        $rows = AttendanceRecord::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('service_date', '>=', now()->subDays(31)->toDateString())->get()->groupBy(fn (AttendanceRecord $record) => (string) $record->service_date)->sortKeysDesc()->take(12)->map(fn ($records, $date) => ['date' => $date, 'records' => $records->count(), 'members_present' => $records->whereNotNull('member_id')->unique('member_id')->count()])->values()->all();

        return ['title' => 'Attendance summary for the last 31 days', 'rows' => $rows];
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
            'total_members' => 'I found **'.number_format((int) ($context['total_members'] ?? 0)).'** permission-filtered members.',
            default => 'I could not generate a response from the verified records. Please try one of the suggested operational questions.',
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

    private function givingTrends(User $user): array
    {
        $this->authorizeAny($user, ['manage finance', 'view finance', 'view ministry finance']);
        $rows = Donation::query()->where('church_id', $user->church_id)->when($user->campus_id !== null && ! $this->broad($user), fn ($q) => $q->where('campus_id', $user->campus_id))->where('received_at', '>=', now()->subMonths(12)->startOfMonth())->with('campus')->get()->groupBy(fn (Donation $d) => $d->campus?->name ?? 'Unassigned')->map(fn ($items, $campus) => ['campus' => $campus, 'total' => round((float) $items->sum('amount'), 2), 'monthly_average' => round((float) $items->sum('amount') / 12, 2), 'donations' => $items->count()])->values()->all();

        return ['title' => 'Giving trends by campus for the last 12 months', 'rows' => $rows, 'period' => 'last 12 months'];
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
