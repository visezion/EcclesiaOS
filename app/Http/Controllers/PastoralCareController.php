<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\CareTask;
use App\Models\Church;
use App\Models\Member;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PastoralCareController extends Controller
{
    private const TYPES = ['Counseling', 'Visitation', 'Prayer Request', 'Membership', 'Family Care', 'Hospital Visit'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    private const STATUSES = ['pending', 'assigned', 'in-progress', 'on-hold', 'resolved'];

    public function index(Request $request): View
    {
        $this->authorizeMembers($request);

        $query = $this->scopeTasks(CareTask::query(), $request)->with(['member.campus', 'campus', 'assignedUser']);
        $this->applyFilters($query, $request);
        $tasks = $query->latest('due_at')->paginate(10)->withQueryString();
        $selectedTaskId = OpaqueId::decode($request->query('task'), CareTask::class);
        $selectedTask = $selectedTaskId ? $this->scopeTasks(CareTask::query()->with(['member.campus', 'campus', 'assignedUser']), $request)->find($selectedTaskId) : $tasks->first();

        return view('members.follow-up', [
            'tasks' => $tasks,
            'selectedTask' => $selectedTask,
            'members' => $this->visibleMembers($request)->with('campus')->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'users' => $this->visibleUsers($request)->get(),
            'stats' => $this->stats($request),
            'statusDistribution' => $this->statusDistribution($request),
            'campusDistribution' => $this->campusDistribution($request),
            'recentActivity' => ActivityLog::query()->with('user')->where('module', 'Pastoral Care')->latest()->limit(6)->get(),
            'types' => self::TYPES,
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members.index')],
                ['label' => 'Follow-up & Pastoral Care', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $validated = $this->validatedTask($request);
        $member = $this->visibleMembers($request)->findOrFail($validated['member_id']);
        $validated['church_id'] = $member->church_id;
        $validated['campus_id'] = $validated['campus_id'] ?? $member->campus_id;
        $task = CareTask::query()->create($validated);

        $activityLogger->log('Pastoral Care', 'care_task_created', 'Care task created for '.$member->first_name.' '.$member->last_name.'.', $task, ['resource' => 'Care Task', 'risk' => $task->priority, 'status' => 'success'], $request);

        return redirect()->route('members.follow-up', ['task' => OpaqueId::encode($task->id, CareTask::class)])->with('status', 'Care task created.');
    }

    public function update(Request $request, CareTask $task, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $this->authorizeTaskRecord($request, $task);
        $validated = $this->validatedTask($request);
        $validated['resolved_at'] = $validated['status'] === 'resolved' ? now() : null;
        $task->update($validated);

        $activityLogger->log('Pastoral Care', 'care_task_updated', 'Care task updated for '.$task->member?->first_name.' '.$task->member?->last_name.'.', $task, ['resource' => 'Care Task', 'risk' => $task->priority, 'status' => 'success'], $request);

        return redirect()->route('members.follow-up', ['task' => OpaqueId::encode($task->id, CareTask::class)])->with('status', 'Care task updated.');
    }

    public function bulk(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $validated = $request->validate([
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*' => ['required', 'string'],
            'action' => ['required', Rule::in(['pending', 'assigned', 'in-progress', 'on-hold', 'resolved'])],
        ]);

        $taskIds = OpaqueId::decodeMany($validated['tasks'], CareTask::class);
        if ($taskIds === []) {
            throw ValidationException::withMessages(['tasks' => 'Select at least one valid care task.']);
        }

        $this->scopeTasks(CareTask::query()->whereIn('id', $taskIds), $request)->update([
            'status' => $validated['action'],
            'resolved_at' => $validated['action'] === 'resolved' ? now() : null,
        ]);
        $activityLogger->log('Pastoral Care', 'care_task_bulk_updated', 'Bulk care task update completed.', null, ['resource' => 'Care Tasks', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Care tasks updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeMembers($request);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['Member', 'Care Type', 'Priority', 'Assigned To', 'Campus', 'Next Action', 'Status', 'Due Date', 'Notes']);
            $this->scopeTasks(CareTask::query(), $request)->with(['member', 'campus', 'assignedUser'])->lazy(100)->each(function (CareTask $task) use ($handle): void {
                fputcsv($handle, [
                    $task->member ? $task->member->first_name.' '.$task->member->last_name : '',
                    $task->type,
                    $task->priority,
                    $task->assignedUser?->name,
                    $task->campus?->name,
                    $task->next_action,
                    $task->status,
                    $task->due_at?->format('Y-m-d H:i'),
                    $task->notes,
                ]);
            });
            fclose($handle);
        }, 'pastoral-care-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeMembers(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage members'), 403);
    }

    private function authorizeTaskRecord(Request $request, CareTask $task): void
    {
        $user = $request->user();
        abort_unless($user?->canAccessChurch($task->church_id) && $user->canAccessCampus($task->campus_id), 403);
    }

    private function scopeTasks(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function visibleMembers(Request $request): Builder
    {
        $query = Member::query()->orderBy('last_name')->orderBy('first_name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function visibleCampuses(Request $request): Builder
    {
        $query = Campus::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where('id', $user->campus_id);
        }

        return $query;
    }

    private function visibleUsers(Request $request): Builder
    {
        $query = User::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function validatedTask(Request $request): array
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string'],
            'campus_id' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'string'],
            'type' => ['required', Rule::in(self::TYPES)],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'next_action' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $validated['member_id'] = OpaqueId::decode($validated['member_id'], Member::class);
        if (! $validated['member_id']) {
            throw ValidationException::withMessages(['member_id' => 'Select a valid member.']);
        }

        $validated['campus_id'] = filled($validated['campus_id'] ?? null)
            ? OpaqueId::decode($validated['campus_id'], Campus::class)
            : null;

        if (filled($request->input('campus_id')) && ! $validated['campus_id']) {
            throw ValidationException::withMessages(['campus_id' => 'Select a valid campus.']);
        }

        $validated['assigned_user_id'] = filled($validated['assigned_user_id'] ?? null)
            ? OpaqueId::decode($validated['assigned_user_id'], User::class)
            : null;

        if (filled($request->input('assigned_user_id')) && ! $validated['assigned_user_id']) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Select a valid assignee.']);
        }

        if (! empty($validated['member_id'])) {
            abort_unless($this->visibleMembers($request)->where('id', $validated['member_id'])->exists(), 403);
        }

        if (! empty($validated['campus_id'])) {
            abort_unless($this->visibleCampuses($request)->where('id', $validated['campus_id'])->exists(), 403);
        }

        if (! empty($validated['assigned_user_id'])) {
            abort_unless($this->visibleUsers($request)->where('id', $validated['assigned_user_id'])->exists(), 403);
        }

        return $validated;
    }

    private function applyFilters($query, Request $request): void
    {
        $query->when($request->filled('q'), function ($query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->whereHas('member', fn ($query) => $query->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
        });
        $query->when($this->queryId($request, 'campus_id', Campus::class), fn ($query, int $campusId) => $query->where('campus_id', $campusId));
        $query->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')));
        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
        $query->when($this->queryId($request, 'assigned_user_id', User::class), fn ($query, int $userId) => $query->where('assigned_user_id', $userId));
        $query->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')));
    }

    private function stats(Request $request): array
    {
        return [
            'open' => $this->scopeTasks(CareTask::query(), $request)->whereNot('status', 'resolved')->count(),
            'inactive' => $this->visibleMembers($request)->whereIn('status', ['inactive', 'follow-up'])->count(),
            'prayer' => $this->scopePrayerRequests(PrayerRequest::query(), $request)->where('status', 'open')->count(),
            'counseling' => $this->scopeTasks(CareTask::query(), $request)->where('type', 'Counseling')->whereNot('status', 'resolved')->count(),
            'visits' => $this->scopeTasks(CareTask::query(), $request)->whereIn('type', ['Visitation', 'Hospital Visit'])->whereDate('due_at', '>=', now()->startOfWeek())->count(),
            'resolved' => $this->scopeTasks(CareTask::query(), $request)->where('status', 'resolved')->whereDate('resolved_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    private function statusDistribution(Request $request): array
    {
        $total = max($this->scopeTasks(CareTask::query(), $request)->count(), 1);

        return collect(self::STATUSES)->map(function (string $status) use ($request, $total): array {
            $count = $this->scopeTasks(CareTask::query(), $request)->where('status', $status)->count();

            return ['label' => str($status)->headline()->toString(), 'status' => $status, 'count' => $count, 'percent' => round(($count / $total) * 100, 1)];
        })->all();
    }

    private function campusDistribution(Request $request): array
    {
        $total = max($this->scopeTasks(CareTask::query(), $request)->count(), 1);

        return $this->visibleCampuses($request)->withCount(['careTasks' => fn (Builder $query) => $this->scopeTasks($query, $request)])
            ->orderByDesc('care_tasks_count')
            ->limit(5)
            ->get()
            ->map(fn (Campus $campus): array => [
            'name' => $campus->name,
            'count' => $campus->care_tasks_count,
            'percent' => round(($campus->care_tasks_count / $total) * 100, 1),
        ])->all();
    }

    private function scopePrayerRequests(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function queryId(Request $request, string $key, string $scope): ?int
    {
        return OpaqueId::decode($request->query($key), $scope);
    }
}
