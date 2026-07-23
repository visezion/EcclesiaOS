<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceVerification;
use App\Models\Campus;
use App\Models\Church;
use App\Models\CommunicationDelivery;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Models\EventSession;
use App\Models\MeetingIntegration;
use App\Models\Member;
use App\Models\Program;
use App\Models\ProgramSection;
use App\Models\ProgramSectionAssignment;
use App\Models\User;
use App\Models\Workflow;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventFlowController extends Controller
{
    private const PHYSICAL_METHODS = ['manual', 'qr', 'geolocation', 'kiosk', 'face'];

    private const ONLINE_METHODS = ['zoom', 'google_meet', 'jitsi', 'livekit'];

    private const PROVIDERS = ['zoom', 'google_meet', 'jitsi', 'livekit'];

    public function programs(Request $request): View
    {
        $this->authorizeEvents($request);

        $status = $request->query('status');
        $campusId = $this->decodeOptionalCampus($request->query('campus'));

        $programs = $this->scopePrograms(Program::query()->with(['campus', 'church'])->withCount(['events', 'sessions']), $request)
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = str((string) $request->query('q'))->lower()->trim()->toString();
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->when(in_array($status, ['upcoming', 'ongoing', 'completed', 'cancelled'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->orderByRaw("CASE WHEN status = 'ongoing' THEN 0 WHEN status = 'upcoming' THEN 1 WHEN status = 'completed' THEN 2 ELSE 3 END")
            ->orderBy('starts_on')
            ->paginate(10)
            ->withQueryString();

        return view('events.programs', [
            'programs' => $programs,
            'churches' => $this->visibleChurches($request)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'stats' => $this->programStats($request),
            'breadcrumbs' => $this->breadcrumbs([['Programs', null]]),
        ]);
    }

    public function storeProgram(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeEvents($request);

        $validated = $this->applyActorScope($request, $this->validateProgramPayload($request));
        $program = Program::query()->create($validated);

        $activityLogger->log('Programs', 'program_created', $program->name.' was created.', $program, ['resource' => 'Program', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('programs.events', $program)->with('status', 'Program created.');
    }

    public function updateProgram(Request $request, Program $program, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);

        $validated = $this->applyActorScope($request, $this->validateProgramPayload($request));
        $program->update($validated);

        $activityLogger->log('Programs', 'program_updated', $program->name.' was updated.', $program, ['resource' => 'Program', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('programs.index')->with('status', 'Program updated.');
    }

    public function destroyProgram(Request $request, Program $program, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);

        $name = $program->name;
        $activityLogger->log('Programs', 'program_deleted', $name.' was deleted.', $program, ['resource' => 'Program', 'risk' => 'medium', 'status' => 'success'], $request);
        $program->delete();

        return redirect()->route('programs.index')->with('status', 'Program deleted.');
    }

    public function events(Request $request, ?Program $program = null): View
    {
        $this->authorizeEvents($request);
        if ($program) {
            $this->authorizeProgram($request, $program);
        }

        $program ??= $this->scopePrograms(Program::query(), $request)->latest('starts_on')->first();

        $eventQuery = Event::query()
            ->withCount('sessions')
            ->with('program')
            ->when($program, fn (Builder $query) => $query->where('program_id', $program->id))
            ->where(fn (Builder $query) => $this->scopeEventQuery($query, $request))
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = str((string) $request->query('q'))->lower()->trim()->toString();
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(venue) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->when(in_array($request->query('status'), ['scheduled', 'draft', 'completed', 'cancelled'], true), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when(filled($request->query('type')), fn (Builder $query) => $query->whereRaw('LOWER(COALESCE(event_type, category, ?)) LIKE ?', ['event', '%'.strtolower((string) $request->query('type')).'%']));
        $eventStatsQuery = clone $eventQuery;
        $events = $eventQuery
            ->latest('starts_at')
            ->paginate(10)
            ->withQueryString();

        $eventStats = [
            'total' => (clone $eventStatsQuery)->count(),
            'sessions' => (clone $eventStatsQuery)->get()->sum('sessions_count'),
            'scheduled' => (clone $eventStatsQuery)->where('status', 'scheduled')->count(),
            'draft' => (clone $eventStatsQuery)->where('status', 'draft')->count(),
        ];

        return view('events.events', [
            'program' => $program,
            'programs' => $this->scopePrograms(Program::query(), $request)->orderBy('name')->get(),
            'events' => $events,
            'stats' => $eventStats,
            'breadcrumbs' => $this->breadcrumbs([['Programs', route('programs.index')], [$program?->name ?? 'Events', null]]),
        ]);
    }

    public function storeEvent(Request $request, Program $program, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_type' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'venue' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::in(['scheduled', 'draft', 'completed', 'cancelled'])],
        ]);

        $event = $program->events()->create([
            ...$validated,
            'church_id' => $program->church_id,
            'campus_id' => $program->campus_id,
            'category' => $validated['event_type'] ?? 'Event',
        ]);

        $this->createDefaultSession($event);
        $activityLogger->log('Events', 'event_created', $event->title.' was created.', $event, ['resource' => 'Program Event', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.index', [$program, $event])->with('status', 'Event created.');
    }

    public function sessions(Request $request, Program $program, Event $event): View
    {
        $this->authorizeProgram($request, $program);
        abort_unless((int) $event->program_id === (int) $program->id, 404);

        $sessionQuery = $event->sessions()
            ->with('attendanceSession')
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = str((string) $request->query('q'))->lower()->trim()->toString();
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(venue) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(address) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->when(in_array($request->query('status'), ['scheduled', 'draft', 'completed', 'cancelled'], true), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when(in_array($request->query('meeting_type'), ['physical', 'online', 'hybrid'], true), fn (Builder $query) => $query->where('meeting_type', $request->query('meeting_type')))
            ->when(filled($request->query('date')), fn (Builder $query) => $query->whereDate('session_date', $request->query('date')));

        $sessionStatsQuery = clone $sessionQuery;
        $sessions = $sessionQuery->orderBy('session_date')->paginate(10)->withQueryString();

        return view('events.sessions', [
            'program' => $program,
            'event' => $event,
            'sessions' => $sessions,
            'campuses' => $this->visibleCampuses($request)->get(),
            'recurrenceRules' => $event->recurrenceRules()->withCount('sessions')->latest()->get(),
            'sections' => ProgramSection::query()
                ->with(['assignments.user', 'assignments.member', 'assignments.approval'])
                ->where('program_id', $program->id)
                ->where(fn (Builder $query) => $query->whereNull('event_id')->orWhere('event_id', $event->id))
                ->orderBy('position')
                ->get(),
            'assignableUsers' => $this->scopeUsers(User::query()->with('roles')->orderBy('name'), $request)->get(),
            'assignableMembers' => $this->scopeMemberQueryReturn(Member::query()->orderBy('last_name')->orderBy('first_name'), $request)->get(),
            'enabledMeetingProviders' => $this->enabledMeetingProviders($request),
            'stats' => [
                'total' => (clone $sessionStatsQuery)->count(),
                'physical' => (clone $sessionStatsQuery)->where('meeting_type', 'physical')->count(),
                'online' => (clone $sessionStatsQuery)->where('meeting_type', 'online')->count(),
                'hybrid' => (clone $sessionStatsQuery)->where('meeting_type', 'hybrid')->count(),
                'recurring' => $event->recurrenceRules()->count(),
                'sections' => ProgramSection::query()->where('program_id', $program->id)->where(fn (Builder $query) => $query->whereNull('event_id')->orWhere('event_id', $event->id))->count(),
                'pending_assignments' => ProgramSectionAssignment::query()
                    ->whereHas('section', fn (Builder $query) => $query->where('program_id', $program->id)->where(fn (Builder $scope) => $scope->whereNull('event_id')->orWhere('event_id', $event->id)))
                    ->where('status', 'pending_approval')
                    ->count(),
            ],
            'breadcrumbs' => $this->breadcrumbs([
                ['Programs', route('programs.index')],
                [$program->name, route('programs.events', $program)],
                [$event->title, null],
            ]),
        ]);
    }

    public function storeSession(Request $request, Program $program, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);
        abort_unless((int) $event->program_id === (int) $program->id, 404);

        $validated = $request->validate($this->sessionRules());
        $session = $event->sessions()->create([
            ...$validated,
            'church_id' => $event->church_id,
            'campus_id' => ($validated['campus_id'] ?? null) ?: $event->campus_id,
            'timezone' => ($validated['timezone'] ?? null) ?: config('app.timezone'),
            'meeting_links' => $this->meetingLinksFromRequest($request),
        ]);
        $this->syncAttendanceMethods($session);

        $activityLogger->log('Event Sessions', 'event_session_created', $session->title.' was created.', $session, ['resource' => 'Event Session', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.meeting', $session)->with('status', 'Event session created.');
    }

    public function storeRecurringSessions(Request $request, Program $program, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);
        abort_unless((int) $event->program_id === (int) $program->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'frequency' => ['required', Rule::in(['weekly', 'monthly'])],
            'interval' => ['required', 'integer', 'min:1', 'max:12'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_occurrences' => ['required', 'integer', 'min:1', 'max:60'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'meeting_type' => ['required', Rule::in(['physical', 'online', 'hybrid'])],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'venue' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        if ($validated['frequency'] === 'weekly' && empty($validated['days_of_week'])) {
            throw ValidationException::withMessages(['days_of_week' => 'Choose at least one weekday for a weekly recurrence.']);
        }

        $requiresApproval = $request->boolean('requires_approval', true);
        $sessionStatus = $requiresApproval ? 'draft' : 'scheduled';

        $rule = DB::transaction(function () use ($request, $event, $validated, $requiresApproval, $sessionStatus): EventRecurrenceRule {
            $rule = EventRecurrenceRule::query()->create([
                'church_id' => $event->church_id,
                'campus_id' => ($validated['campus_id'] ?? null) ?: $event->campus_id,
                'event_id' => $event->id,
                'created_by' => $request->user()?->id,
                'title' => $validated['title'],
                'frequency' => $validated['frequency'],
                'interval' => $validated['interval'],
                'days_of_week' => $validated['days_of_week'] ?? null,
                'day_of_month' => $validated['day_of_month'] ?? null,
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'] ?? null,
                'max_occurrences' => $validated['max_occurrences'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'timezone' => config('app.timezone'),
                'meeting_type' => $validated['meeting_type'],
                'venue' => $validated['venue'] ?? null,
                'address' => $validated['address'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'meeting_links' => $this->meetingLinksFromRequest($request),
                'status' => $requiresApproval ? 'pending_approval' : 'active',
            ]);

            foreach ($this->recurrenceDates($rule) as $date) {
                $session = EventSession::query()->firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'recurrence_rule_id' => $rule->id,
                        'session_date' => $date->toDateString(),
                        'starts_at' => $rule->starts_at,
                    ],
                    [
                        'church_id' => $rule->church_id,
                        'campus_id' => $rule->campus_id,
                        'title' => $rule->title.' - '.$date->format('M d, Y'),
                        'ends_at' => $rule->ends_at,
                        'timezone' => $rule->timezone,
                        'meeting_type' => $rule->meeting_type,
                        'venue' => $rule->venue,
                        'address' => $rule->address,
                        'capacity' => $rule->capacity,
                        'status' => $sessionStatus,
                        'meeting_links' => $rule->meeting_links,
                    ],
                );
                $this->syncAttendanceMethods($session);
            }

            if ($requiresApproval) {
                $this->requestApproval($request, $rule, 'create_recurring_meeting', [
                    'title' => $rule->title,
                    'frequency' => $rule->frequency,
                    'generated_sessions' => $rule->sessions()->count(),
                ]);
            }

            return $rule;
        });

        $activityLogger->log('Event Sessions', 'recurring_sessions_created', $rule->title.' recurrence generated '.$rule->sessions()->count().' session(s).', $rule, ['resource' => 'Event Recurrence Rule', 'risk' => $requiresApproval ? 'medium' : 'low', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.index', [$program, $event])->with('status', $rule->sessions()->count().' recurring session(s) generated. '.($requiresApproval ? 'Approval request created.' : ''));
    }

    public function storeProgramSection(Request $request, Program $program, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);
        abort_unless((int) $event->program_id === (int) $program->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'section_type' => ['required', Rule::in(['worship', 'prayer', 'sermon', 'offering', 'announcement', 'media', 'hospitality', 'custom'])],
            'position' => ['required', 'integer', 'min:1', 'max:500'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:720'],
            'scope' => ['required', Rule::in(['program', 'event'])],
        ]);

        $section = ProgramSection::query()->create([
            'church_id' => $program->church_id,
            'campus_id' => $program->campus_id,
            'program_id' => $program->id,
            'event_id' => $validated['scope'] === 'event' ? $event->id : null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'section_type' => $validated['section_type'],
            'position' => $validated['position'],
            'planned_start_time' => $validated['planned_start_time'] ?? null,
            'planned_duration_minutes' => $validated['planned_duration_minutes'] ?? null,
            'status' => 'active',
        ]);

        $activityLogger->log('Program Sections', 'program_section_created', $section->title.' was added to the order of service.', $section, ['resource' => 'Program Section', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.index', [$program, $event])->with('status', 'Program section added.');
    }

    public function storeProgramSectionAssignment(Request $request, Program $program, Event $event, ProgramSection $section, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);
        abort_unless((int) $event->program_id === (int) $program->id && (int) $section->program_id === (int) $program->id, 404);

        $validated = $request->validate([
            'assignee_type' => ['required', Rule::in(['user', 'member'])],
            'user_id' => ['nullable', 'exists:users,id'],
            'member_id' => ['nullable', 'exists:members,id'],
            'role_title' => ['required', 'string', 'max:120'],
            'responsibility_notes' => ['nullable', 'string', 'max:1200'],
            'call_time' => ['nullable', 'date'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        if ($validated['assignee_type'] === 'user' && empty($validated['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'Choose a user for this assignment.']);
        }
        if ($validated['assignee_type'] === 'member' && empty($validated['member_id'])) {
            throw ValidationException::withMessages(['member_id' => 'Choose a member for this assignment.']);
        }

        $requiresApproval = $request->boolean('requires_approval', true);
        $assignment = ProgramSectionAssignment::query()->create([
            'church_id' => $section->church_id,
            'campus_id' => $section->campus_id,
            'program_section_id' => $section->id,
            'user_id' => $validated['assignee_type'] === 'user' ? $validated['user_id'] : null,
            'member_id' => $validated['assignee_type'] === 'member' ? $validated['member_id'] : null,
            'role_title' => $validated['role_title'],
            'responsibility_notes' => $validated['responsibility_notes'] ?? null,
            'call_time' => $validated['call_time'] ?? null,
            'status' => $requiresApproval ? 'pending_approval' : 'assigned',
        ]);

        if ($requiresApproval) {
            $this->requestApproval($request, $assignment, 'assign_program_section', [
                'section' => $section->title,
                'role_title' => $assignment->role_title,
                'responsibility_notes' => $assignment->responsibility_notes,
            ]);
        } else {
            $this->notifyAssignment($assignment, 'Program responsibility assigned', 'You have been assigned to '.$section->title.' as '.$assignment->role_title.'.');
        }

        $activityLogger->log('Program Sections', 'program_section_assigned', $assignment->role_title.' assignment was created for '.$section->title.'.', $assignment, ['resource' => 'Program Section Assignment', 'risk' => $requiresApproval ? 'medium' : 'low', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.index', [$program, $event])->with('status', 'Section assignment created'.($requiresApproval ? ' and sent for approval.' : '.'));
    }

    public function calendar(Request $request): View
    {
        $this->authorizeEvents($request);
        $month = Carbon::parse($request->query('month', now()->format('Y-m-01')))->startOfMonth();
        $sessions = EventSession::query()
            ->with(['event.program', 'campus', 'attendanceSession'])
            ->where(fn (Builder $query) => $this->scopeSessionQuery($query, $request))
            ->whereBetween('session_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('session_date')
            ->get()
            ->groupBy(fn (EventSession $session): string => $session->session_date->toDateString());

        return view('events.calendar', [
            'month' => $month,
            'sessionsByDate' => $sessions,
            'monthSessions' => $sessions->flatten(1),
            'breadcrumbs' => $this->breadcrumbs([['Calendar', null]]),
        ]);
    }

    public function meetings(Request $request): View
    {
        $this->authorizeEvents($request);

        $meetingQuery = EventSession::query()
            ->with(['event.program', 'campus', 'attendanceSession'])
            ->where(fn (Builder $query) => $this->scopeSessionQuery($query, $request))
            ->where('session_date', '>=', now()->subDay()->toDateString())
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = str((string) $request->query('q'))->lower()->trim()->toString();
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(venue) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('event.program', fn (Builder $programQuery) => $programQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']));
                });
            })
            ->when(in_array($request->query('meeting_type'), ['physical', 'online', 'hybrid'], true), fn (Builder $query) => $query->where('meeting_type', $request->query('meeting_type')))
            ->when(in_array($request->query('status'), ['scheduled', 'draft', 'completed', 'cancelled'], true), fn (Builder $query) => $query->where('status', $request->query('status')));
        $meetingStatsQuery = clone $meetingQuery;
        $sessions = $meetingQuery
            ->orderBy('session_date')
            ->paginate(10)
            ->withQueryString();

        $meetingStats = [
            'total' => (clone $meetingStatsQuery)->count(),
            'physical' => (clone $meetingStatsQuery)->where('meeting_type', 'physical')->count(),
            'online' => (clone $meetingStatsQuery)->where('meeting_type', 'online')->count(),
            'hybrid' => (clone $meetingStatsQuery)->where('meeting_type', 'hybrid')->count(),
            'attendance' => $sessions->getCollection()->sum(fn (EventSession $session): int => (int) ($session->attendanceSession?->records()->count() ?? 0)),
        ];

        return view('events.meetings', [
            'sessions' => $sessions,
            'integrations' => $this->providerIntegrations($request),
            'stats' => $meetingStats,
            'breadcrumbs' => $this->breadcrumbs([['Meetings', null]]),
        ]);
    }

    public function meeting(Request $request, EventSession $eventSession): View
    {
        $this->authorizeSession($request, $eventSession);
        $eventSession->load(['event.program', 'campus', 'attendanceSession']);

        return view('events.meeting', [
            'session' => $eventSession,
            'integrations' => $this->providerIntegrations($request),
            'enabledMeetingProviders' => $this->enabledMeetingProviders($request),
            'breadcrumbs' => $this->breadcrumbs([
                ['Programs', route('programs.index')],
                [$eventSession->event->title, route('event-sessions.index', [$eventSession->event->program, $eventSession->event])],
                ['Meeting', null],
            ]),
        ]);
    }

    public function updateMeeting(Request $request, EventSession $eventSession, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSession($request, $eventSession);

        $validated = $request->validate([
            'meeting_type' => ['required', Rule::in(['physical', 'online', 'hybrid'])],
            'venue' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:200'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $eventSession->update([
            ...$validated,
            'meeting_links' => $this->meetingLinksFromRequest($request),
        ]);
        $this->syncAttendanceMethods($eventSession->fresh());

        $activityLogger->log('Meetings', 'meeting_updated', $eventSession->title.' meeting settings were updated.', $eventSession, ['resource' => 'Meeting', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Meeting updated.');
    }

    public function room(Request $request, EventSession $eventSession, string $provider, ActivityLogger $activityLogger): View
    {
        $this->authorizeSession($request, $eventSession);
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $eventSession->load(['event.program', 'campus', 'attendanceSession']);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        abort_unless($this->sessionHasSelectedProvider($eventSession, $provider), 403);
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);

        $integration = MeetingIntegration::query()
            ->where('church_id', $eventSession->church_id)
            ->where('provider', $provider)
            ->firstOrFail();
        abort_unless($integration->enabled, 403);

        $member = $this->memberForUser($request);
        $record = null;

        if ($member && $provider === 'livekit') {
            $record = $this->roomAttendanceRecord($attendanceSession, $member);
        } elseif ($member) {
            $record = $this->storeAttendanceEvidence(
                $attendanceSession,
                $eventSession,
                $member,
                $provider,
                $provider,
                96,
                [
                    'auto_online' => true,
                    'internal_room' => true,
                    'room_provider' => $provider,
                    'user_agent' => $request->userAgent(),
                ],
                null,
                'built-in meeting room',
            );
        }

        $activeRoomParticipants = $this->activeRoomParticipants($attendanceSession, $provider);
        $liveKitPayload = $provider === 'livekit'
            ? $this->liveKitRoomPayload($integration, $eventSession, $attendanceSession, $request, $record, $activeRoomParticipants->count())
            : null;

        if ($record && $provider !== 'livekit') {
            $activityLogger->log('Meetings', 'meeting_room_joined', $member->first_name.' joined '.$provider.' internally.', $record, ['resource' => 'Built-in Meeting Room', 'risk' => 'low', 'status' => 'success'], $request);
        }

        return view('events.room', [
            'session' => $eventSession,
            'attendanceSession' => $attendanceSession->load(['records.member']),
            'provider' => $provider,
            'meta' => $this->providerMeta()[$provider],
            'member' => $member,
            'record' => $record,
            'liveKitPayload' => $liveKitPayload,
            'activeRoomParticipants' => $activeRoomParticipants,
            'breadcrumbs' => $this->breadcrumbs([
                ['Meetings', route('meetings.index')],
                [$eventSession->title, route('event-sessions.meeting', $eventSession)],
                ['Built-in Room', null],
            ]),
        ]);
    }

    public function markRoomAttendance(Request $request, EventSession $eventSession, string $provider, ActivityLogger $activityLogger): JsonResponse
    {
        $this->authorizeSession($request, $eventSession);
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $eventSession->load(['event.program', 'campus', 'attendanceSession']);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        abort_unless($this->sessionHasSelectedProvider($eventSession, $provider), 403);
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);

        $integration = MeetingIntegration::query()
            ->where('church_id', $eventSession->church_id)
            ->where('provider', $provider)
            ->firstOrFail();
        abort_unless($integration->enabled, 403);

        $member = $this->memberForUser($request);
        abort_unless($member, 422, 'No linked member record was found for this signed-in user.');

        $payload = $request->validate([
            'connected' => ['required', 'boolean'],
            'room' => ['nullable', 'string', 'max:160'],
            'remote_participants' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        abort_unless((bool) $payload['connected'], 422, 'Attendance can only be marked after a successful room connection.');

        $record = $this->storeAttendanceEvidence(
            $attendanceSession,
            $eventSession,
            $member,
            $provider,
            $provider,
            $provider === 'livekit' ? 98 : 96,
            [
                'auto_online' => true,
                'internal_room' => true,
                'room_provider' => $provider,
                'room' => $payload['room'] ?? ($eventSession->meeting_links[$provider]['room'] ?? null),
                'remote_participants' => (int) ($payload['remote_participants'] ?? 0),
                'connected_before_attendance' => true,
                'online_status' => 'online',
                'checked_out_at' => null,
                'last_seen_at' => now()->toIso8601String(),
                'user_agent' => $request->userAgent(),
            ],
            null,
            $provider === 'livekit' ? 'livekit room connection' : 'built-in meeting room',
        );

        $activityLogger->log('Meetings', 'meeting_room_joined', $member->first_name.' joined '.$provider.' room.', $record, ['resource' => 'Built-in Meeting Room', 'risk' => 'low', 'status' => 'success'], $request);

        $attendanceSession->load(['records.member']);
        $activeCount = $this->activeRoomParticipants($attendanceSession, $provider)->count();

        return response()->json([
            'marked' => true,
            'participant_count' => $activeCount,
            'record_url' => route('attendance.records.show', [$attendanceSession, $member->opaqueId()]),
            'checked_in_at' => $record->checked_in_at?->format('h:i A'),
        ]);
    }

    public function markRoomCheckout(Request $request, EventSession $eventSession, string $provider): JsonResponse
    {
        $this->authorizeSession($request, $eventSession);
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $eventSession->load(['attendanceSession']);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        abort_unless($this->sessionHasSelectedProvider($eventSession, $provider), 403);
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);

        $member = $this->memberForUser($request);
        abort_unless($member, 422, 'No linked member record was found for this signed-in user.');

        $record = $this->roomAttendanceRecord($attendanceSession, $member);
        abort_unless($record, 404);

        $metadata = $record->metadata ?? [];
        $record->update([
            'metadata' => [
                ...$metadata,
                'room_provider' => $provider,
                'online_status' => 'checked_out',
                'checked_out_at' => now()->toIso8601String(),
                'last_seen_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'checked_out' => true,
            'participant_count' => $this->activeRoomParticipants($attendanceSession, $provider)->count(),
        ]);
    }

    public function attendance(Request $request, EventSession $eventSession): View
    {
        $this->authorizeSession($request, $eventSession);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        $attendanceSession->load(['eventSession.event.program', 'records.member', 'verifications']);

        return view('events.attendance-session', [
            'session' => $eventSession->load(['event.program', 'campus']),
            'attendanceSession' => $attendanceSession,
            'selectedOnlineMethods' => $this->selectedOnlineMethods($eventSession),
            'records' => $attendanceSession->records()->with(['member', 'verifications'])->latest('checked_in_at')->paginate(10),
            'breadcrumbs' => $this->breadcrumbs([
                ['Programs', route('programs.index')],
                [$eventSession->event->title, route('event-sessions.index', [$eventSession->event->program, $eventSession->event])],
                ['Attendance Session', null],
            ]),
        ]);
    }

    public function updateAttendance(Request $request, EventSession $eventSession, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSession($request, $eventSession);

        $validated = $request->validate([
            'methods' => ['nullable', 'array'],
            'methods.*' => [Rule::in([...self::PHYSICAL_METHODS, ...self::ONLINE_METHODS])],
            'verification_policy' => ['required', Rule::in(['any_one', 'best_confidence', 'manual_review'])],
            'require_authenticated' => ['nullable', 'boolean'],
            'allow_guests' => ['nullable', 'boolean'],
            'geo_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'geo_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geo_radius_meters' => ['required', 'integer', 'min:10', 'max:50000'],
            'expected_attendance' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['scheduled', 'open', 'closed'])],
        ]);

        $this->ensureAttendanceSession($eventSession)->update([
            ...$validated,
            'methods' => $this->allowedRequestedMethods($eventSession, $validated['methods'] ?? $this->attendanceMethodsForSession($eventSession)),
            'require_authenticated' => (bool) ($validated['require_authenticated'] ?? false),
            'allow_guests' => (bool) ($validated['allow_guests'] ?? false),
            'expected_attendance' => (int) ($validated['expected_attendance'] ?? 0),
        ]);

        $activityLogger->log('Attendance', 'attendance_session_updated', $eventSession->title.' attendance policy was updated.', $eventSession, ['resource' => 'Attendance Session', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Attendance session updated.');
    }

    public function destroyAttendanceSession(Request $request, AttendanceSession $attendanceSession, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeAttendanceSession($request, $attendanceSession);

        $title = $attendanceSession->title;
        DB::transaction(function () use ($attendanceSession): void {
            $attendanceSession->verifications()->delete();
            $attendanceSession->records()->delete();
            $attendanceSession->delete();
        });

        $activityLogger->log('Attendance', 'attendance_session_deleted', $title.' attendance session was deleted with its records.', $attendanceSession, ['resource' => 'Attendance Session', 'risk' => 'medium', 'status' => 'success'], $request);

        return redirect()->route('attendance.index')->with('status', 'Attendance session deleted.');
    }

    public function attendanceIndex(Request $request): View
    {
        $this->authorizeAttendance($request);

        $status = $request->query('status');
        $attendanceSessions = AttendanceSession::query()
            ->with(['eventSession.event.program', 'eventSession.campus'])
            ->withCount('records')
            ->where(fn (Builder $query) => $this->scopeAttendanceQuery($query, $request))
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = str((string) $request->query('q'))->lower()->trim()->toString();
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('eventSession', fn (Builder $sessionQuery) => $sessionQuery->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%']))
                        ->orWhereHas('eventSession.event.program', fn (Builder $programQuery) => $programQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']));
                });
            })
            ->when(in_array($status, ['scheduled', 'open', 'closed'], true), fn (Builder $query) => $query->where('status', $status))
            ->latest('opens_at')
            ->paginate(10)
            ->withQueryString();

        return view('events.attendance-index', [
            'attendanceSessions' => $attendanceSessions,
            'stats' => $this->attendanceStats($request),
            'breadcrumbs' => $this->breadcrumbs([['Attendance', null]]),
        ]);
    }

    public function methods(Request $request, AttendanceSession $attendanceSession): View
    {
        $this->authorizeAttendanceSession($request, $attendanceSession);
        $attendanceSession->load('eventSession.event.program');
        $member = $this->memberForUser($request);

        return view('events.attendance-methods', [
            'attendanceSession' => $attendanceSession,
            'member' => $member,
            'selectedOnlineMethods' => $this->selectedOnlineMethods($attendanceSession->eventSession),
            'breadcrumbs' => $this->breadcrumbs([['Attendance', route('attendance.index')], ['Check-in Methods', null]]),
        ]);
    }

    public function checkIn(Request $request, AttendanceSession $attendanceSession, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeAttendanceSession($request, $attendanceSession);

        $method = $request->validate([
            'method' => ['required', Rule::in([...self::PHYSICAL_METHODS, ...self::ONLINE_METHODS])],
            'provider' => ['nullable', 'string', 'max:80'],
            'member_id' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'face_reference' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(in_array($method['method'], $attendanceSession->methods ?? [], true), 403);

        $member = $this->resolveMember($request, $method['member_id'] ?? null);
        $eventSession = $attendanceSession->eventSession()->with('event')->firstOrFail();
        $confidence = $this->confidenceFor($method['method'], $method);

        $record = $this->storeAttendanceEvidence(
            $attendanceSession,
            $eventSession,
            $member,
            $method['method'],
            $method['provider'] ?? $method['method'],
            $confidence,
            [
                'latitude' => $method['latitude'] ?? null,
                'longitude' => $method['longitude'] ?? null,
                'face_reference' => $method['face_reference'] ?? null,
                'auto_online' => in_array($method['method'], self::ONLINE_METHODS, true),
            ],
        );

        $activityLogger->log('Attendance', 'attendance_marked', ($member?->first_name ?? 'Guest').' attendance was marked by '.$method['method'].'.', $record, ['resource' => 'Attendance Record', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('attendance.records.show', [$attendanceSession, $member?->opaqueId() ?? 'guest'])->with('status', 'Attendance marked.');
    }

    public function record(Request $request, AttendanceSession $attendanceSession, string $member): View
    {
        $this->authorizeAttendanceSession($request, $attendanceSession);
        $resolvedMember = $member === 'guest' ? null : Member::query()->whereKey(OpaqueId::decode($member, Member::class))->firstOrFail();
        $record = AttendanceRecord::query()
            ->with(['member', 'verifications'])
            ->where('attendance_session_id', $attendanceSession->id)
            ->when($resolvedMember, fn (Builder $query) => $query->where('member_id', $resolvedMember->id), fn (Builder $query) => $query->whereNull('member_id'))
            ->firstOrFail();

        return view('events.attendance-record', [
            'attendanceSession' => $attendanceSession->load('eventSession.event.program'),
            'record' => $record,
            'breadcrumbs' => $this->breadcrumbs([['Attendance', route('attendance.index')], ['Final Attendance Record', null]]),
        ]);
    }

    public function updateAttendanceRecord(Request $request, AttendanceRecord $record, ActivityLogger $activityLogger): RedirectResponse
    {
        $record->load('attendanceSession');
        abort_unless($record->attendanceSession, 404);
        $this->authorizeAttendanceSession($request, $record->attendanceSession);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'final_method' => ['required', Rule::in([...self::PHYSICAL_METHODS, ...self::ONLINE_METHODS])],
            'service_date' => ['required', 'date'],
            'checked_in_at' => ['nullable', 'date'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $metadata = $record->metadata ?? [];
        $metadata['admin_note'] = $validated['admin_note'] ?? null;
        $metadata['edited_by'] = $request->user()?->id;
        $metadata['edited_at'] = now()->toIso8601String();

        $record->update([
            'status' => $validated['status'],
            'final_method' => $validated['final_method'],
            'service_date' => $validated['service_date'],
            'checked_in_at' => $validated['checked_in_at'] ? Carbon::parse($validated['checked_in_at']) : null,
            'metadata' => $metadata,
        ]);

        $activityLogger->log('Attendance', 'attendance_record_updated', 'Attendance record '.$record->opaqueId().' was updated.', $record, ['resource' => 'Attendance Record', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Attendance record updated.');
    }

    public function destroyAttendanceRecord(Request $request, AttendanceRecord $record, ActivityLogger $activityLogger): RedirectResponse
    {
        $record->load('attendanceSession');
        abort_unless($record->attendanceSession, 404);
        $this->authorizeAttendanceSession($request, $record->attendanceSession);

        $attendanceSession = $record->attendanceSession;
        $recordId = $record->opaqueId();
        DB::transaction(function () use ($record): void {
            $record->verifications()->delete();
            $record->delete();
        });

        $activityLogger->log('Attendance', 'attendance_record_deleted', 'Attendance record '.$recordId.' was deleted.', $attendanceSession, ['resource' => 'Attendance Record', 'risk' => 'medium', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.attendance', $attendanceSession->eventSession)->with('status', 'Attendance record deleted.');
    }

    public function integrations(Request $request): View
    {
        $this->authorizeSettings($request);

        return view('events.integrations', [
            'integrations' => $this->providerIntegrations($request),
            'providers' => self::PROVIDERS,
            'providerMeta' => $this->providerMeta(),
            'breadcrumbs' => $this->breadcrumbs([['Administration', route('users.index')], ['Meeting Integrations', null]]),
        ]);
    }

    public function updateIntegrations(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);
        $churchId = $request->user()?->church_id ?? Church::query()->value('id');
        $validated = $request->validate([
            'providers' => ['nullable', 'array'],
            'providers.*.enabled' => ['nullable', 'boolean'],
            'providers.*.internal_endpoint' => ['nullable', 'string', 'max:255'],
            'providers.*.webhook_secret' => ['nullable', 'string', 'max:255'],
            'providers.*.webhook_event' => ['nullable', 'string', 'max:160'],
            'providers.*.room_prefix' => ['nullable', 'string', 'max:80'],
            'providers.*.identity_field' => ['nullable', Rule::in(['email', 'phone'])],
            'providers.*.recording_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'providers.*.server_url' => ['nullable', 'string', 'max:255'],
            'providers.*.api_key' => ['nullable', 'string', 'max:255'],
            'providers.*.api_secret' => ['nullable', 'string', 'max:255'],
            'providers.*.participant_token_ttl' => ['nullable', 'string', 'max:40'],
        ]);

        foreach (self::PROVIDERS as $provider) {
            $input = $validated['providers'][$provider] ?? [];
            $existing = MeetingIntegration::query()->where('church_id', $churchId)->where('provider', $provider)->first();
            $existingSettings = $existing?->settings ?? [];
            $enabled = (bool) ($input['enabled'] ?? false);
            $webhookSecretHash = filled($input['webhook_secret'] ?? null)
                ? hash('sha256', (string) $input['webhook_secret'])
                : ($existingSettings['webhook_secret_hash'] ?? null);

            if ($provider === 'livekit') {
                $serverUrl = filled($input['server_url'] ?? null)
                    ? $this->normalizeLiveKitServerUrl((string) $input['server_url'])
                    : ($existingSettings['server_url'] ?? null);
                $apiKey = $input['api_key'] ?? ($existingSettings['api_key'] ?? null);
                $apiSecretEncrypted = filled($input['api_secret'] ?? null)
                    ? Crypt::encryptString((string) $input['api_secret'])
                    : ($existingSettings['api_secret_encrypted'] ?? null);
                $ttlSeconds = $this->parseLiveKitTokenTtl($input['participant_token_ttl'] ?? ($existingSettings['participant_token_ttl_label'] ?? '2 hr'));

                if ($enabled && (! filled($input['room_prefix'] ?? ($existingSettings['room_prefix'] ?? null)) || ! filled($serverUrl) || ! filled($apiKey) || ! filled($apiSecretEncrypted))) {
                    throw ValidationException::withMessages([
                        "providers.{$provider}.server_url" => 'Enabled LiveKit rooms require server URL, room prefix, API key, API secret, and token TTL.',
                    ]);
                }

                MeetingIntegration::query()->updateOrCreate(
                    ['church_id' => $churchId, 'provider' => $provider],
                    [
                        'enabled' => $enabled,
                        'settings' => [
                            'server_url' => $serverUrl,
                            'room_prefix' => $input['room_prefix'] ?? ($existingSettings['room_prefix'] ?? 'church'),
                            'api_key' => $apiKey,
                            'api_secret_encrypted' => $apiSecretEncrypted,
                            'api_secret_configured' => filled($apiSecretEncrypted),
                            'participant_token_ttl_seconds' => $ttlSeconds,
                            'participant_token_ttl_label' => $this->formatLiveKitTokenTtl($ttlSeconds),
                            'last_test_status' => $existingSettings['last_test_status'] ?? 'not_tested',
                            'last_test_message' => $existingSettings['last_test_message'] ?? null,
                        ],
                        'last_tested_at' => $existing?->last_tested_at,
                    ],
                );

                continue;
            }

            if ($enabled && (! filled($input['room_prefix'] ?? ($existingSettings['room_prefix'] ?? null)) || ! filled($webhookSecretHash))) {
                throw ValidationException::withMessages([
                    "providers.{$provider}.room_prefix" => 'Enabled built-in meeting methods require a room prefix and attendance secret.',
                ]);
            }

            MeetingIntegration::query()->updateOrCreate(
                ['church_id' => $churchId, 'provider' => $provider],
                [
                    'enabled' => $enabled,
                    'settings' => [
                        'internal_endpoint' => $input['internal_endpoint'] ?? ($existingSettings['internal_endpoint'] ?? route('meetings.index', absolute: false)),
                        'webhook_secret_hash' => $webhookSecretHash,
                        'webhook_secret_configured' => filled($webhookSecretHash),
                        'webhook_event' => $input['webhook_event'] ?? ($existingSettings['webhook_event'] ?? 'internal.participant_joined'),
                        'room_prefix' => $input['room_prefix'] ?? ($existingSettings['room_prefix'] ?? 'kingdomlife'),
                        'identity_field' => $input['identity_field'] ?? ($existingSettings['identity_field'] ?? 'email'),
                        'recording_retention_days' => (int) ($input['recording_retention_days'] ?? ($existingSettings['recording_retention_days'] ?? 30)),
                        'last_test_status' => $existingSettings['last_test_status'] ?? 'not_tested',
                        'last_test_message' => $existingSettings['last_test_message'] ?? null,
                    ],
                    'last_tested_at' => $existing?->last_tested_at,
                ],
            );
        }

        $activityLogger->log('Settings', 'meeting_integrations_updated', 'Meeting provider integrations were updated.', null, ['resource' => 'Meeting Integrations', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Meeting integrations updated.');
    }

    public function testIntegration(Request $request, string $provider, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $churchId = $request->user()?->church_id ?? Church::query()->value('id');
        $integration = MeetingIntegration::query()->where('church_id', $churchId)->where('provider', $provider)->firstOrFail();
        $settings = $integration->settings ?? [];

        if ($provider === 'livekit') {
            $liveKitPayload = $this->liveKitTestPayload($integration);
            $connectivity = $this->liveKitConnectivityHints($liveKitPayload['server_url']);
            $message = 'LiveKit credentials generated a valid participant token for '.$liveKitPayload['server_url'].' (room '.$liveKitPayload['room'].', TTL '.$liveKitPayload['ttl_label'].').';
            $status = empty($connectivity['warnings']) ? 'healthy' : 'warning';

            if (! empty($connectivity['warnings'])) {
                $message .= ' Media connectivity warning: '.implode(' ', $connectivity['warnings']);
            }

            $integration->update([
                'last_tested_at' => now(),
                'settings' => [
                    ...$settings,
                    'last_test_status' => $status,
                    'last_test_message' => $message,
                    'last_test_room' => $liveKitPayload['room'],
                    'last_test_token_expires_at' => $liveKitPayload['expires_at'],
                    'last_connectivity_check' => $connectivity,
                ],
            ]);

            $activityLogger->log('Settings', 'meeting_integration_tested', str_replace('_', ' ', $provider).' integration was tested.', $integration, ['resource' => 'Meeting Integrations', 'risk' => 'low', 'status' => $status === 'healthy' ? 'success' : 'warning'], $request);

            return back()->with('status', $message);
        }

        if (! $integration->enabled || ! filled($settings['room_prefix'] ?? null) || ! ($settings['webhook_secret_configured'] ?? false)) {
            throw ValidationException::withMessages([
                'provider' => 'Enable the built-in method and save its room prefix and attendance secret before testing.',
            ]);
        }

        $message = 'Built-in meeting adapter is ready inside EcclesiaOS.';
        $status = 'healthy';

        $integration->update([
            'last_tested_at' => now(),
            'settings' => [
                ...$settings,
                'last_test_status' => $status,
                'last_test_message' => $message,
            ],
        ]);

        $activityLogger->log('Settings', 'meeting_integration_tested', str_replace('_', ' ', $provider).' integration was tested.', $integration, ['resource' => 'Meeting Integrations', 'risk' => 'low', 'status' => $status === 'failed' ? 'failed' : 'success'], $request);

        return back()->with($status === 'failed' ? 'error' : 'status', $message);
    }

    public function onlineAttendanceWebhook(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $payload = $request->validate([
            'attendance_session' => ['required', 'string'],
            'email' => ['required', 'email'],
            'joined_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'meeting_id' => ['nullable', 'string', 'max:120'],
        ]);

        $attendanceSession = AttendanceSession::query()
            ->with('eventSession')
            ->findOrFail(OpaqueId::decode($payload['attendance_session'], AttendanceSession::class));

        $integration = MeetingIntegration::query()
            ->where('church_id', $attendanceSession->church_id)
            ->where('provider', $provider)
            ->firstOrFail();
        $settings = $integration->settings ?? [];
        $secret = (string) $request->header('X-Meeting-Webhook-Secret', '');

        abort_unless($integration->enabled && filled($settings['webhook_secret_hash'] ?? null) && hash_equals((string) $settings['webhook_secret_hash'], hash('sha256', $secret)), 403);
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);

        $member = Member::query()
            ->where('church_id', $attendanceSession->church_id)
            ->where('email', $payload['email'])
            ->first();

        $record = DB::transaction(function () use ($attendanceSession, $provider, $payload, $member): AttendanceRecord {
            $eventSession = $attendanceSession->eventSession;
            $record = AttendanceRecord::query()->updateOrCreate(
                ['attendance_session_id' => $attendanceSession->id, 'member_id' => $member?->id],
                [
                    'church_id' => $attendanceSession->church_id,
                    'campus_id' => $attendanceSession->campus_id,
                    'event_id' => $eventSession->event_id,
                    'service_date' => $eventSession->session_date,
                    'status' => 'present',
                    'checked_in_at' => filled($payload['joined_at'] ?? null) ? Carbon::parse($payload['joined_at']) : now(),
                    'final_method' => $provider,
                    'metadata' => ['source' => 'built-in meeting callback'],
                ],
            );

            AttendanceVerification::query()->create([
                'attendance_session_id' => $attendanceSession->id,
                'attendance_record_id' => $record->id,
                'member_id' => $member?->id,
                'method' => $provider,
                'provider' => $provider,
                'status' => 'success',
                'confidence' => 96,
                'verified_at' => filled($payload['joined_at'] ?? null) ? Carbon::parse($payload['joined_at']) : now(),
                'metadata' => [
                    'auto_online' => true,
                    'meeting_id' => $payload['meeting_id'] ?? null,
                    'duration_minutes' => $payload['duration_minutes'] ?? null,
                    'email' => $payload['email'],
                ],
            ]);

            $summary = AttendanceVerification::query()
                ->where('attendance_record_id', $record->id)
                ->orderByDesc('confidence')
                ->get(['method', 'provider', 'status', 'confidence', 'verified_at'])
                ->map(fn (AttendanceVerification $verification): array => [
                    'method' => $verification->method,
                    'provider' => $verification->provider,
                    'status' => $verification->status,
                    'confidence' => $verification->confidence,
                    'verified_at' => $verification->verified_at?->toIso8601String(),
                ])
                ->all();

            $record->update(['final_method' => $provider, 'verification_summary' => $summary]);

            return $record->fresh();
        });

        return response()->json([
            'status' => 'ok',
            'attendance_record' => $record->opaqueId(),
            'member_matched' => $member !== null,
        ]);
    }

    private function createDefaultSession(Event $event): void
    {
        $session = $event->sessions()->firstOrCreate(
            ['title' => $event->title, 'session_date' => $event->starts_at->toDateString()],
            [
                'church_id' => $event->church_id,
                'campus_id' => $event->campus_id,
                'starts_at' => $event->starts_at->format('H:i:s'),
                'ends_at' => $event->ends_at?->format('H:i:s'),
                'timezone' => config('app.timezone'),
                'meeting_type' => 'physical',
                'venue' => $event->venue,
                'status' => $event->status,
            ],
        );
        $this->ensureAttendanceSession($session);
    }

    private function ensureAttendanceSession(EventSession $session): AttendanceSession
    {
        $start = Carbon::parse($session->session_date->toDateString().' '.$session->starts_at);
        $end = $session->ends_at ? Carbon::parse($session->session_date->toDateString().' '.$session->ends_at) : $start->copy()->addHours(2);

        return AttendanceSession::query()->firstOrCreate(
            ['event_session_id' => $session->id],
            [
                'church_id' => $session->church_id,
                'campus_id' => $session->campus_id,
                'title' => $session->title.' Attendance',
                'opens_at' => $start->copy()->subMinutes(30),
                'closes_at' => $end->copy()->addMinutes(15),
                'methods' => $this->attendanceMethodsForSession($session),
                'verification_policy' => 'any_one',
                'require_authenticated' => true,
                'allow_guests' => false,
                'expected_attendance' => (int) ($session->capacity ?? 0),
                'status' => 'scheduled',
            ],
        );
    }

    private function defaultMethods(string $meetingType): array
    {
        return match ($meetingType) {
            'online' => self::ONLINE_METHODS,
            'hybrid' => [...self::PHYSICAL_METHODS, ...self::ONLINE_METHODS],
            default => self::PHYSICAL_METHODS,
        };
    }

    private function syncAttendanceMethods(EventSession $session): AttendanceSession
    {
        $attendanceSession = $this->ensureAttendanceSession($session);
        $attendanceSession->update(['methods' => $this->attendanceMethodsForSession($session)]);

        return $attendanceSession->fresh();
    }

    private function attendanceMethodsForSession(EventSession $session): array
    {
        $selectedOnlineMethods = $this->selectedOnlineMethods($session);

        return match ($session->meeting_type) {
            'online' => $selectedOnlineMethods,
            'hybrid' => array_values(array_unique([...self::PHYSICAL_METHODS, ...$selectedOnlineMethods])),
            default => self::PHYSICAL_METHODS,
        };
    }

    private function allowedRequestedMethods(EventSession $session, array $requestedMethods): array
    {
        $allowed = $this->attendanceMethodsForSession($session);

        return collect($requestedMethods)
            ->filter(fn (string $method): bool => in_array($method, $allowed, true))
            ->values()
            ->all();
    }

    private function selectedOnlineMethods(EventSession $session): array
    {
        return collect($session->meeting_links ?? [])
            ->keys()
            ->filter(fn (string $provider): bool => in_array($provider, self::ONLINE_METHODS, true))
            ->values()
            ->all();
    }

    private function sessionHasSelectedProvider(EventSession $session, string $provider): bool
    {
        return in_array($provider, $this->selectedOnlineMethods($session), true);
    }

    private function meetingLinksFromRequest(Request $request): array
    {
        $enabledProviders = $this->enabledProviderKeys($request);

        return collect(self::PROVIDERS)
            ->filter(fn (string $provider): bool => in_array($provider, $enabledProviders, true))
            ->filter(fn (string $provider): bool => $request->boolean("meeting_links.{$provider}.enabled"))
            ->mapWithKeys(fn (string $provider): array => [
                $provider => [
                    'room' => $request->input("meeting_links.{$provider}.room") ?: 'kingdomlife-'.$provider.'-'.Str::slug((string) $request->input('title', 'session')),
                    'access_code' => $request->input("meeting_links.{$provider}.access_code"),
                ],
            ])
            ->all();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function recurrenceDates(EventRecurrenceRule $rule): Collection
    {
        $startsOn = $rule->starts_on->copy()->startOfDay();
        $endsOn = ($rule->ends_on ?: $startsOn->copy()->addYear())->copy()->startOfDay();
        $maxOccurrences = min((int) ($rule->max_occurrences ?: 12), 60);
        $dates = collect();

        if ($rule->frequency === 'weekly') {
            $allowedDays = collect($rule->days_of_week ?: [strtolower($startsOn->format('l'))])
                ->map(fn (string $day): string => strtolower($day))
                ->all();
            $cursor = $startsOn->copy();

            while ($cursor->lte($endsOn) && $dates->count() < $maxOccurrences) {
                $weekOffset = (int) floor($startsOn->diffInWeeks($cursor));
                if ($weekOffset % max((int) $rule->interval, 1) === 0 && in_array(strtolower($cursor->format('l')), $allowedDays, true)) {
                    $dates->push($cursor->copy());
                }
                $cursor->addDay();
            }

            return $dates;
        }

        $dayOfMonth = (int) ($rule->day_of_month ?: $startsOn->day);
        $cursor = $startsOn->copy()->startOfMonth();
        while ($cursor->lte($endsOn) && $dates->count() < $maxOccurrences) {
            $candidate = $cursor->copy()->day(min($dayOfMonth, $cursor->daysInMonth));
            if ($candidate->gte($startsOn) && $candidate->lte($endsOn)) {
                $dates->push($candidate);
            }
            $cursor->addMonths(max((int) $rule->interval, 1));
        }

        return $dates;
    }

    private function requestApproval(Request $request, EventRecurrenceRule|ProgramSectionAssignment $resource, string $action, array $payload): Approval
    {
        $workflow = Workflow::query()->firstOrCreate(
            [
                'church_id' => $resource->church_id,
                'module' => 'programs',
                'name' => 'Program Planning Approval',
            ],
            [
                'status' => 'active',
                'steps' => [
                    ['position' => 1, 'role' => 'Senior Pastor', 'required' => true],
                    ['position' => 2, 'role' => 'Church Administrator', 'required' => false],
                ],
            ],
        );

        $approval = Approval::query()->create([
            'church_id' => $resource->church_id,
            'workflow_id' => $workflow->id,
            'approvable_type' => $resource::class,
            'approvable_id' => $resource->id,
            'action' => $action,
            'requested_by' => $request->user()?->id,
            'status' => 'pending',
            'notes' => Str::headline($action).' requires approval.',
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        $this->notifyApprovers($approval);

        return $approval;
    }

    private function notifyApprovers(Approval $approval): void
    {
        User::query()
            ->where(fn (Builder $query) => $query->where('church_id', $approval->church_id)->orWhereNull('church_id'))
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['Super Administrator', 'Church Administrator', 'Senior Pastor']))
            ->get()
            ->each(function (User $user) use ($approval): void {
                CommunicationDelivery::query()->create([
                    'church_id' => $approval->church_id,
                    'channel' => 'in_app',
                    'provider' => 'ecclesiaos',
                    'recipient_name' => $user->name,
                    'recipient_contact' => $user->email,
                    'subject' => 'Approval required: '.Str::headline((string) $approval->action),
                    'body_excerpt' => 'Review the pending workflow request in Workflow & Approvals.',
                    'event_type' => 'ApprovalRequested',
                    'status' => 'queued',
                ]);
            });
    }

    private function notifyAssignment(ProgramSectionAssignment $assignment, string $subject, string $message): void
    {
        $name = $assignment->user?->name
            ?? trim(($assignment->member?->first_name ?? '').' '.($assignment->member?->last_name ?? ''))
            ?: 'Assigned Person';
        $contact = $assignment->user?->email ?? $assignment->member?->email;

        CommunicationDelivery::query()->create([
            'church_id' => $assignment->church_id,
            'member_id' => $assignment->member_id,
            'channel' => 'in_app',
            'provider' => 'ecclesiaos',
            'recipient_name' => $name,
            'recipient_contact' => $contact,
            'subject' => $subject,
            'body_excerpt' => $message,
            'event_type' => 'ProgramSectionAssigned',
            'status' => 'queued',
        ]);
    }

    private function enabledProviderKeys(Request $request): array
    {
        return $this->providerIntegrations($request)
            ->filter(fn (MeetingIntegration $integration): bool => $integration->enabled)
            ->keys()
            ->values()
            ->all();
    }

    private function enabledMeetingProviders(Request $request): array
    {
        $meta = $this->providerMeta();

        return collect($this->enabledProviderKeys($request))
            ->mapWithKeys(fn (string $provider): array => [
                $provider => [
                    'label' => $meta[$provider]['label'],
                    'icon' => $meta[$provider]['icon'],
                    'color' => $meta[$provider]['color'],
                ],
            ])
            ->all();
    }

    private function providerIntegrations(Request $request): Collection
    {
        $churchId = $request->user()?->church_id ?? Church::query()->value('id');

        return collect(self::PROVIDERS)->mapWithKeys(fn (string $provider): array => [
            $provider => MeetingIntegration::query()->firstOrCreate(
                ['church_id' => $churchId, 'provider' => $provider],
                ['enabled' => false, 'settings' => []],
            ),
        ]);
    }

    private function providerMeta(): array
    {
        return [
            'zoom' => [
                'label' => 'Zoom',
                'icon' => 'video',
                'color' => 'blue',
                'internal_endpoint' => '/meetings',
                'required' => ['Room Prefix', 'Attendance Secret', 'Identity Field'],
                'event' => 'internal.participant_joined',
            ],
            'google_meet' => [
                'label' => 'Google Meet',
                'icon' => 'calendar-clock',
                'color' => 'emerald',
                'internal_endpoint' => '/meetings',
                'required' => ['Room Prefix', 'Attendance Secret', 'Identity Field'],
                'event' => 'internal.participant_joined',
            ],
            'jitsi' => [
                'label' => 'Jitsi Meet',
                'icon' => 'radio',
                'color' => 'orange',
                'internal_endpoint' => '/meetings',
                'required' => ['Room Prefix', 'Attendance Secret', 'Identity Field'],
                'event' => 'internal.participant_joined',
            ],
            'livekit' => [
                'label' => 'LiveKit',
                'icon' => 'radio-tower',
                'color' => 'violet',
                'internal_endpoint' => null,
                'required' => ['Server URL', 'Room Prefix', 'API Key', 'API Secret', 'Token TTL'],
                'event' => 'livekit.participant_joined',
            ],
        ];
    }

    private function liveKitTestPayload(MeetingIntegration $integration): array
    {
        $settings = $integration->settings ?? [];
        $this->validateLiveKitSettings($integration);

        $ttlSeconds = (int) ($settings['participant_token_ttl_seconds'] ?? 7200);
        $room = Str::slug((string) ($settings['room_prefix'] ?? 'church')).'-test-room';
        $expiresAt = now()->addSeconds($ttlSeconds);

        return [
            'server_url' => $settings['server_url'],
            'room' => $room,
            'token' => $this->generateLiveKitToken(
                (string) $settings['api_key'],
                $this->decryptLiveKitSecret($settings),
                $room,
                'integration-test',
                'Integration Test',
                $ttlSeconds,
            ),
            'ttl_label' => $this->formatLiveKitTokenTtl($ttlSeconds),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function liveKitRoomPayload(MeetingIntegration $integration, EventSession $eventSession, AttendanceSession $attendanceSession, Request $request, ?AttendanceRecord $record, int $activeParticipantCount): array
    {
        $settings = $integration->settings ?? [];
        $this->validateLiveKitSettings($integration);

        $room = (string) ($eventSession->meeting_links['livekit']['room'] ?? Str::slug((string) ($settings['room_prefix'] ?? 'church')).'-livekit-'.$eventSession->id);
        $member = $this->memberForUser($request);
        $identity = $member?->email ?: $request->user()?->email ?: 'guest-'.($request->user()?->id ?? Str::random(8));
        $name = $member ? trim($member->first_name.' '.$member->last_name) : ($request->user()?->name ?? 'Guest');
        $ttlSeconds = (int) ($settings['participant_token_ttl_seconds'] ?? 7200);

        return [
            'server_url' => (string) $settings['server_url'],
            'room' => $room,
            'identity' => $identity,
            'name' => $name,
            'token' => $this->generateLiveKitToken(
                (string) $settings['api_key'],
                $this->decryptLiveKitSecret($settings),
                $room,
                $identity,
                $name,
                $ttlSeconds,
            ),
            'ttl_label' => $this->formatLiveKitTokenTtl($ttlSeconds),
            'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
            'mark_attendance_url' => route('meetings.rooms.attendance.store', [$eventSession, 'livekit']),
            'mark_checkout_url' => route('meetings.rooms.checkout.store', [$eventSession, 'livekit']),
            'attendance_marked' => (bool) $record,
            'attendance_record_url' => $record && $member ? route('attendance.records.show', [$attendanceSession, $member->opaqueId()]) : null,
            'participant_count' => $activeParticipantCount,
        ];
    }

    private function roomAttendanceRecord(AttendanceSession $attendanceSession, Member $member): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('attendance_session_id', $attendanceSession->id)
            ->where('member_id', $member->id)
            ->latest('checked_in_at')
            ->first();
    }

    private function activeRoomParticipants(AttendanceSession $attendanceSession, string $provider): Collection
    {
        return $attendanceSession->records()
            ->with('member')
            ->where('final_method', $provider)
            ->latest('checked_in_at')
            ->get()
            ->filter(function (AttendanceRecord $record) use ($provider): bool {
                $metadata = $record->metadata ?? [];

                return ($metadata['room_provider'] ?? $record->final_method) === $provider
                    && ($metadata['online_status'] ?? null) === 'online'
                    && blank($metadata['checked_out_at'] ?? null);
            })
            ->values();
    }

    private function validateLiveKitSettings(MeetingIntegration $integration): void
    {
        $settings = $integration->settings ?? [];

        if (! $integration->enabled || ! filled($settings['server_url'] ?? null) || ! filled($settings['room_prefix'] ?? null) || ! filled($settings['api_key'] ?? null) || ! filled($settings['api_secret_encrypted'] ?? null)) {
            throw ValidationException::withMessages([
                'provider' => 'Enable LiveKit and save its server URL, room prefix, API key, API secret, and token TTL before testing.',
            ]);
        }
    }

    private function liveKitConnectivityHints(string $serverUrl): array
    {
        $host = parse_url($serverUrl, PHP_URL_HOST);
        $warnings = [];

        if (! is_string($host) || blank($host)) {
            return ['warnings' => ['The LiveKit server host could not be parsed.']];
        }

        $checks = [
            7881 => $this->tcpPortOpen($host, 7881),
            5349 => $this->tcpPortOpen($host, 5349),
        ];

        if (! $checks[7881] && ! $checks[5349]) {
            $warnings[] = 'Neither TCP 7881 nor TURN/TLS 5349 is reachable from this server. If browsers report "could not establish pc connection", expose LiveKit media ports or configure TURN/TLS.';
        }

        if (str_ends_with($host, '.cloud') || str_contains((string) gethostbyname($host), '104.')) {
            $warnings[] = 'This host appears to be behind a proxy/CDN. LiveKit signaling can work through HTTPS, but WebRTC media ports must reach the LiveKit server directly or through TURN.';
        }

        return [
            'host' => $host,
            'tcp_7881_open' => $checks[7881],
            'turn_tls_5349_open' => $checks[5349],
            'warnings' => $warnings,
        ];
    }

    private function tcpPortOpen(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 2.0);

        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }

    private function generateLiveKitToken(string $apiKey, string $apiSecret, string $room, string $identity, string $name, int $ttlSeconds): string
    {
        $now = now()->timestamp;
        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'name' => $name,
            'nbf' => $now,
            'exp' => $now + max(60, $ttlSeconds),
            'video' => [
                'roomJoin' => true,
                'room' => $room,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $apiSecret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function normalizeLiveKitServerUrl(string $serverUrl): string
    {
        $serverUrl = trim($serverUrl);
        $parts = parse_url($serverUrl);

        if ($parts === false || blank($parts['host'] ?? null)) {
            throw ValidationException::withMessages([
                'providers.livekit.server_url' => 'Enter a valid LiveKit server URL.',
            ]);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'wss'));
        $scheme = match ($scheme) {
            'http', 'ws' => 'ws',
            'https', 'wss' => 'wss',
            default => throw ValidationException::withMessages([
                'providers.livekit.server_url' => 'LiveKit server URL must use http, https, ws, or wss.',
            ]),
        };

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        return $scheme.'://'.$parts['host'].$port.$path;
    }

    private function parseLiveKitTokenTtl(?string $ttl): int
    {
        $ttl = trim((string) ($ttl ?: '2 hr'));

        if (preg_match('/^(\d+)\s*(h|hr|hrs|hour|hours)$/i', $ttl, $matches) === 1) {
            return max(60, min(86400, (int) $matches[1] * 3600));
        }

        if (preg_match('/^(\d+)\s*(m|min|mins|minute|minutes)$/i', $ttl, $matches) === 1) {
            return max(60, min(86400, (int) $matches[1] * 60));
        }

        if (preg_match('/^\d+$/', $ttl) === 1) {
            return max(60, min(86400, (int) $ttl * 60));
        }

        throw ValidationException::withMessages([
            'providers.livekit.participant_token_ttl' => 'Use a token TTL like 2 hr, 90 min, or 120.',
        ]);
    }

    private function formatLiveKitTokenTtl(int $seconds): string
    {
        if ($seconds % 3600 === 0) {
            $hours = (int) ($seconds / 3600);

            return $hours.' '.Str::plural('hr', $hours);
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' '.Str::plural('min', $minutes);
    }

    private function decryptLiveKitSecret(array $settings): string
    {
        try {
            return Crypt::decryptString((string) $settings['api_secret_encrypted']);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'provider' => 'The stored LiveKit API secret could not be decrypted. Re-enter the API secret and save again.',
            ]);
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function storeAttendanceEvidence(
        AttendanceSession $attendanceSession,
        EventSession $eventSession,
        ?Member $member,
        string $method,
        string $provider,
        int $confidence,
        array $metadata = [],
        ?Carbon $checkedInAt = null,
        string $source = 'attendance session',
    ): AttendanceRecord {
        $checkedInAt ??= now();

        return DB::transaction(function () use ($attendanceSession, $eventSession, $member, $method, $provider, $confidence, $metadata, $checkedInAt, $source): AttendanceRecord {
            $record = AttendanceRecord::query()->updateOrCreate(
                ['attendance_session_id' => $attendanceSession->id, 'member_id' => $member?->id],
                [
                    'church_id' => $attendanceSession->church_id,
                    'campus_id' => $attendanceSession->campus_id,
                    'event_id' => $eventSession->event_id,
                    'service_date' => $eventSession->session_date,
                    'status' => 'present',
                    'checked_in_at' => $checkedInAt,
                    'final_method' => $method,
                    'metadata' => [
                        'source' => $source,
                        ...$metadata,
                    ],
                ],
            );

            AttendanceVerification::query()->create([
                'attendance_session_id' => $attendanceSession->id,
                'attendance_record_id' => $record->id,
                'member_id' => $member?->id,
                'method' => $method,
                'provider' => $provider,
                'status' => 'success',
                'confidence' => $confidence,
                'verified_at' => $checkedInAt,
                'metadata' => [
                    'ip' => request()->ip(),
                    ...$metadata,
                ],
            ]);

            $summary = AttendanceVerification::query()
                ->where('attendance_record_id', $record->id)
                ->orderByDesc('confidence')
                ->get(['method', 'provider', 'status', 'confidence', 'verified_at'])
                ->map(fn (AttendanceVerification $verification): array => [
                    'method' => $verification->method,
                    'provider' => $verification->provider,
                    'status' => $verification->status,
                    'confidence' => $verification->confidence,
                    'verified_at' => $verification->verified_at?->toIso8601String(),
                ])
                ->all();

            $best = collect($summary)->sortByDesc('confidence')->first();
            $record->update(['final_method' => $best['method'] ?? $method, 'verification_summary' => $summary]);

            return $record->fresh();
        });
    }

    private function resolveMember(Request $request, ?string $key): ?Member
    {
        if (filled($key)) {
            $id = OpaqueId::decode($key, Member::class);
            $member = Member::query()->findOrFail($id);
            abort_unless($request->user()?->canAccessChurch($member->church_id) && $request->user()?->canAccessCampus($member->campus_id), 403);

            return $member;
        }

        return $this->memberForUser($request);
    }

    private function memberForUser(Request $request): ?Member
    {
        $user = $request->user();

        return Member::query()
            ->where(fn (Builder $query) => $query->where('email', $user?->email)->orWhere('phone', $user?->phone))
            ->where(fn (Builder $query) => $this->scopeMemberQuery($query, $request))
            ->first()
            ?? Member::query()->where(fn (Builder $query) => $this->scopeMemberQuery($query, $request))->orderBy('last_name')->first();
    }

    private function confidenceFor(string $method, array $payload): int
    {
        return match ($method) {
            'geolocation' => filled($payload['latitude'] ?? null) && filled($payload['longitude'] ?? null) ? 95 : 75,
            'face' => filled($payload['face_reference'] ?? null) ? 92 : 70,
            'zoom', 'google_meet', 'jitsi', 'livekit' => 88,
            'qr' => 90,
            'kiosk' => 85,
            default => 80,
        };
    }

    private function sessionRules(): array
    {
        return [
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'title' => ['required', 'string', 'max:160'],
            'session_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'meeting_type' => ['required', Rule::in(['physical', 'online', 'hybrid'])],
            'venue' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:200'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['scheduled', 'draft', 'completed', 'cancelled'])],
        ];
    }

    private function validateProgramPayload(Request $request): array
    {
        if (filled($request->input('campus_id')) && ! filter_var($request->input('campus_id'), FILTER_VALIDATE_INT)) {
            $request->merge(['campus_id' => OpaqueId::decode($request->input('campus_id'), Campus::class)]);
        }

        return $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
        ]);
    }

    private function authorizeEvents(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage events'), 403);
    }

    private function authorizeAttendance(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage attendance') || $request->user()?->hasPermission('manage events'), 403);
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage settings'), 403);
    }

    private function authorizeProgram(Request $request, Program $program): void
    {
        $this->authorizeEvents($request);
        abort_unless($request->user()?->canAccessChurch($program->church_id) && $request->user()?->canAccessCampus($program->campus_id), 403);
    }

    private function authorizeSession(Request $request, EventSession $session): void
    {
        $this->authorizeEvents($request);
        abort_unless($request->user()?->canAccessChurch($session->church_id) && $request->user()?->canAccessCampus($session->campus_id), 403);
    }

    private function authorizeAttendanceSession(Request $request, AttendanceSession $session): void
    {
        $this->authorizeAttendance($request);
        abort_unless($request->user()?->canAccessChurch($session->church_id) && $request->user()?->canAccessCampus($session->campus_id), 403);
    }

    private function applyActorScope(Request $request, array $validated): array
    {
        $actor = $request->user();
        if (! $actor?->isSuperAdministrator()) {
            $validated['church_id'] = $actor?->church_id;
            if ($actor?->campus_id !== null) {
                $validated['campus_id'] = $actor->campus_id;
            }
        }

        $validated['church_id'] = $validated['church_id'] ?? Church::query()->value('id');
        abort_unless($actor?->canAccessChurch((int) $validated['church_id']) && $actor->canAccessCampus($validated['campus_id'] ?? null), 403);

        return $validated;
    }

    private function visibleChurches(Request $request): Builder
    {
        return $request->user()?->isSuperAdministrator() ? Church::query() : Church::query()->whereKey($request->user()?->church_id);
    }

    private function visibleCampuses(Request $request): Builder
    {
        $query = Campus::query();
        if (! $request->user()?->isSuperAdministrator()) {
            $query->where('church_id', $request->user()?->church_id);
            if ($request->user()?->campus_id !== null) {
                $query->whereKey($request->user()?->campus_id);
            }
        }

        return $query;
    }

    private function scopePrograms(Builder $query, Request $request): Builder
    {
        if ($request->user()?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $request->user()?->church_id);
        if ($request->user()?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery->whereNull('campus_id')->orWhere('campus_id', $request->user()?->campus_id));
        }

        return $query;
    }

    private function scopeEventQuery(Builder $query, Request $request): void
    {
        if ($request->user()?->isSuperAdministrator()) {
            return;
        }

        $query->where('church_id', $request->user()?->church_id);
        if ($request->user()?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery->whereNull('campus_id')->orWhere('campus_id', $request->user()?->campus_id));
        }
    }

    private function scopeSessionQuery(Builder $query, Request $request): void
    {
        if ($request->user()?->isSuperAdministrator()) {
            return;
        }

        $query->where('church_id', $request->user()?->church_id);
        if ($request->user()?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery->whereNull('campus_id')->orWhere('campus_id', $request->user()?->campus_id));
        }
    }

    private function scopeAttendanceQuery(Builder $query, Request $request): void
    {
        if ($request->user()?->isSuperAdministrator()) {
            return;
        }

        $query->where('church_id', $request->user()?->church_id);
        if ($request->user()?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery->whereNull('campus_id')->orWhere('campus_id', $request->user()?->campus_id));
        }
    }

    private function scopeMemberQuery(Builder $query, Request $request): void
    {
        if ($request->user()?->isSuperAdministrator()) {
            return;
        }

        $query->where('church_id', $request->user()?->church_id);
        if ($request->user()?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery->whereNull('campus_id')->orWhere('campus_id', $request->user()?->campus_id));
        }
    }

    private function scopeMemberQueryReturn(Builder $query, Request $request): Builder
    {
        $this->scopeMemberQuery($query, $request);

        return $query;
    }

    private function scopeUsers(Builder $query, Request $request): Builder
    {
        if ($request->user()?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $request->user()?->church_id);
        if ($request->user()?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery->whereNull('campus_id')->orWhere('campus_id', $request->user()?->campus_id));
        }

        return $query;
    }

    private function programStats(Request $request): array
    {
        return [
            'programs' => $this->scopePrograms(Program::query(), $request)->count(),
            'upcoming' => $this->scopePrograms(Program::query(), $request)->where('status', 'upcoming')->count(),
            'ongoing' => $this->scopePrograms(Program::query(), $request)->where('status', 'ongoing')->count(),
            'completed' => $this->scopePrograms(Program::query(), $request)->where('status', 'completed')->count(),
            'events' => Event::query()->where(fn (Builder $query) => $this->scopeEventQuery($query, $request))->count(),
            'sessions' => EventSession::query()->where(fn (Builder $query) => $this->scopeSessionQuery($query, $request))->count(),
            'attendance' => AttendanceRecord::query()->whereNotNull('attendance_session_id')->count(),
        ];
    }

    private function attendanceStats(Request $request): array
    {
        $base = AttendanceSession::query()->where(fn (Builder $query) => $this->scopeAttendanceQuery($query, $request));

        return [
            'sessions' => (clone $base)->count(),
            'scheduled' => (clone $base)->where('status', 'scheduled')->count(),
            'open' => (clone $base)->where('status', 'open')->count(),
            'closed' => (clone $base)->where('status', 'closed')->count(),
            'records' => AttendanceRecord::query()->whereNotNull('attendance_session_id')->count(),
        ];
    }

    private function decodeOptionalCampus(mixed $value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        return OpaqueId::decode((string) $value, Campus::class);
    }

    private function breadcrumbs(array $items): array
    {
        return array_merge([['label' => 'Dashboard', 'url' => route('dashboard')]], collect($items)->map(fn (array $item): array => ['label' => $item[0], 'url' => $item[1] ?? null])->all());
    }
}
