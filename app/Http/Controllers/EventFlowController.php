<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceVerification;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Models\EventSession;
use App\Models\EventTemplate;
use App\Models\MeetingIntegration;
use App\Models\MeetingPoll;
use App\Models\MeetingPollOption;
use App\Models\MeetingPollVote;
use App\Models\MeetingQnaItem;
use App\Models\MeetingScene;
use App\Models\MeetingStudioState;
use App\Models\Member;
use App\Models\Program;
use App\Models\ProgramSection;
use App\Models\ProgramSectionAssignment;
use App\Models\User;
use App\Models\Workflow;
use App\Services\ActivityLogger;
use App\Services\Communications\DomainNotificationService;
use App\Services\Communications\ZenderWhatsAppNotifier;
use App\Support\OpaqueId;
use App\Support\SecretHash;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventFlowController extends Controller
{
    public function __construct(private readonly DomainNotificationService $domainNotifications) {}

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

        $eventQuery = Event::query()
            ->withCount('sessions')
            ->with(['program', 'sessions' => fn ($query) => $query->orderBy('session_date')->limit(1)])
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
            'eventTemplates' => EventTemplate::query()
                ->where('church_id', $request->user()->church_id)
                ->when($program?->campus_id, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->whereNull('campus_id')->orWhere('campus_id', $program->campus_id)))
                ->orderBy('name')
                ->get(),
            'events' => $events,
            'stats' => $eventStats,
            'breadcrumbs' => $this->breadcrumbs([['Programs', route('programs.index')], [$program?->name ?? 'Events', null]]),
        ]);
    }

    public function storeEvent(Request $request, ?Program $program, ActivityLogger $activityLogger, ZenderWhatsAppNotifier $notifier): RedirectResponse
    {
        $program = $program?->exists ? $program : null;
        if ($program) {
            $this->authorizeProgram($request, $program);
        } else {
            $this->authorizeEvents($request);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_type' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'venue' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['scheduled', 'draft', 'completed', 'cancelled'])],
            'template_id' => ['nullable', 'exists:event_templates,id'],
        ]);

        $template = null;
        if (filled($validated['template_id'] ?? null)) {
            $template = EventTemplate::query()
                ->where('church_id', $program?->church_id ?? $request->user()->church_id)
                ->findOrFail($validated['template_id']);
            $validated['description'] = $validated['description'] ?? $template->description;
            $validated['event_type'] = $validated['event_type'] ?? $template->event_type;
            $validated['venue'] = $validated['venue'] ?? $template->venue;
        }
        unset($validated['template_id']);

        $churchId = $program?->church_id ?? $request->user()->church_id ?? Church::query()->value('id');
        $campusId = $program?->campus_id ?? $request->user()->campus_id;
        $event = Event::query()->create([
            ...$validated,
            'church_id' => $churchId,
            'campus_id' => $campusId,
            'program_id' => $program?->id,
            'category' => $validated['event_type'] ?? 'Event',
            'status' => 'draft',
        ]);

        $session = $this->createDefaultSession($event);
        if ($template) {
            $this->copyTemplateAgenda($template, $event);
        }
        $activityLogger->log('Events', 'event_created', $event->title.' was created.', $event, ['resource' => 'Program Event', 'risk' => 'low', 'status' => 'success'], $request);

        return $program
            ? redirect()->route('event-sessions.index', [$program, $event])->with('status', 'Event created.')
            : redirect()->route('event-sessions.meeting', $session)->with('status', 'Event created.');
    }

    public function submitEventForApproval(Request $request, Event $event, ActivityLogger $activityLogger, ?Program $program = null): RedirectResponse
    {
        $this->authorizeEvents($request);
        if ($program) {
            $this->authorizeProgram($request, $program);
            abort_unless((int) $event->program_id === (int) $program->id, 404);
        } else {
            abort_unless($request->user()?->canAccessChurch($event->church_id) && $request->user()?->canAccessCampus($event->campus_id), 403);
        }

        abort_unless(in_array($event->status, ['draft', 'scheduled'], true), 422, 'Only a draft or scheduled event can be submitted for approval.');
        $approval = $this->requestApproval($request, $event, 'publish_event', [
            'title' => $event->title,
            'event_type' => $event->event_type ?? $event->category,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'sessions' => $event->sessions()->count(),
            'agenda_items' => ProgramSection::query()->where('event_id', $event->id)->count(),
        ]);
        $event->update(['status' => 'draft']);
        $activityLogger->log('Events', 'event_submitted_for_approval', $event->title.' was submitted for approval.', $event, ['resource' => 'Event', 'risk' => 'medium', 'status' => 'pending'], $request);

        return back()->with('status', $approval->wasRecentlyCreated ? 'Event submitted for approval.' : 'This event is already awaiting approval.');
    }

    public function submitMeetingForApproval(Request $request, EventSession $eventSession, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSession($request, $eventSession);
        abort_unless(in_array($eventSession->status, ['draft', 'scheduled'], true), 422, 'Only a draft or scheduled meeting can be submitted for approval.');

        $approval = $this->requestApproval($request, $eventSession, 'publish_meeting', [
            'title' => $eventSession->title,
            'event' => $eventSession->event?->title,
            'session_date' => $eventSession->session_date?->toDateString(),
            'starts_at' => $eventSession->starts_at,
            'agenda_items' => ProgramSection::query()->where('event_session_id', $eventSession->id)->count(),
        ]);
        $eventSession->update(['status' => 'draft']);
        $activityLogger->log('Meetings', 'meeting_submitted_for_approval', $eventSession->title.' was submitted for approval.', $eventSession, ['resource' => 'Event Session', 'risk' => 'medium', 'status' => 'pending'], $request);

        return back()->with('status', $approval->wasRecentlyCreated ? 'Meeting submitted for approval.' : 'This meeting is already awaiting approval.');
    }

    public function cloneEvent(Request $request, Program $program, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);
        abort_unless((int) $event->program_id === (int) $program->id, 404);
        $validated = $request->validate(['title' => ['required', 'string', 'max:160']]);

        $clone = DB::transaction(function () use ($event, $program, $validated): Event {
            $clone = $event->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
            $clone->program_id = $program->id;
            $clone->title = $validated['title'];
            $clone->status = 'draft';
            $clone->save();

            $sessionMap = [];
            foreach ($event->sessions()->get() as $session) {
                $newSession = $session->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                $newSession->event_id = $clone->id;
                $newSession->recurrence_rule_id = null;
                $newSession->status = 'draft';
                $newSession->save();
                $sessionMap[$session->id] = $newSession;
                $this->ensureAttendanceSession($newSession);
            }

            ProgramSection::query()
                ->with('assignments')
                ->where('event_id', $event->id)
                ->get()
                ->each(function (ProgramSection $section) use ($clone, $sessionMap): void {
                    $newSection = $section->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                    $newSection->event_id = $clone->id;
                    $newSection->program_id = $clone->program_id;
                    $newSection->event_session_id = $section->event_session_id ? $sessionMap[$section->event_session_id]?->id : null;
                    $newSection->save();
                    foreach ($section->assignments as $assignment) {
                        $newAssignment = $assignment->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                        $newAssignment->program_section_id = $newSection->id;
                        $newAssignment->status = 'assigned';
                        $newAssignment->save();
                    }
                });

            return $clone;
        });

        $activityLogger->log('Events', 'event_cloned', $clone->title.' was cloned from '.$event->title.'.', $clone, ['resource' => 'Program Event', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.index', [$program, $clone])->with('status', 'Event cloned as a draft. You can now adjust its agenda.');
    }

    public function storeEventTemplate(Request $request, Program $program, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeProgram($request, $program);
        abort_unless((int) $event->program_id === (int) $program->id, 404);
        $validated = $request->validate(['name' => ['required', 'string', 'max:160']]);

        $agenda = ProgramSection::query()
            ->with('assignments')
            ->where('event_id', $event->id)
            ->orderBy('position')
            ->get()
            ->map(fn (ProgramSection $section): array => [
                'title' => $section->title,
                'description' => $section->description,
                'resource_reference' => $section->resource_reference,
                'attachment_path' => $section->attachment_path,
                'attachment_name' => $section->attachment_name,
                'section_type' => $section->section_type,
                'position' => $section->position,
                'planned_start_time' => $section->planned_start_time,
                'planned_duration_minutes' => $section->planned_duration_minutes,
                'assignments' => $section->assignments->map(fn (ProgramSectionAssignment $assignment): array => [
                    'user_id' => $assignment->user_id,
                    'member_id' => $assignment->member_id,
                    'role_title' => $assignment->role_title,
                    'responsibility_notes' => $assignment->responsibility_notes,
                ])->all(),
            ])->all();

        EventTemplate::query()->create([
            'church_id' => $event->church_id,
            'campus_id' => $event->campus_id,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $event->description,
            'event_type' => $event->event_type ?? $event->category,
            'venue' => $event->venue,
            'agenda' => $agenda,
        ]);
        $activityLogger->log('Events', 'event_template_created', $validated['name'].' template was created.', null, ['resource' => 'Event Template', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Event template saved. It is now available when creating an event.');
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
            'status' => 'draft',
        ]);
        $this->syncAttendanceMethods($session);

        $activityLogger->log('Event Sessions', 'event_session_created', $session->title.' was created.', $session, ['resource' => 'Event Session', 'risk' => 'low', 'status' => 'success'], $request);
        $this->domainNotifications->audience(
            (int) $session->church_id,
            $session->campus_id ? (int) $session->campus_id : null,
            'EventSessionCreated',
            'events',
            "New session: {$session->title}",
            "{$session->title} has been scheduled for ".$session->session_date?->format('M d, Y').'.',
            ['in_app'],
            ['url' => route('event-sessions.meeting', $session)],
        );

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
        if (! $requiresApproval) {
            $this->domainNotifications->audience(
                (int) $rule->church_id,
                $rule->campus_id ? (int) $rule->campus_id : null,
                'EventSessionCreated',
                'events',
                "Recurring sessions: {$rule->title}",
                $rule->sessions()->count().' recurring sessions were added.',
                ['in_app'],
                ['url' => route('event-sessions.index', [$program, $event])],
            );
        }

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
            'agendaSections' => $this->agendaSectionsForSession($eventSession),
            'assignableUsers' => $this->scopeUsers(User::query()->orderBy('name'), $request)->get(),
            'assignableMembers' => $this->scopeMemberQueryReturn(Member::query()->orderBy('last_name')->orderBy('first_name'), $request)->get(),
            'integrations' => $this->providerIntegrations($request),
            'enabledMeetingProviders' => $this->enabledMeetingProviders($request),
            'breadcrumbs' => $this->breadcrumbs([
                ['Programs', route('programs.index')],
                [$eventSession->event->title, route('event-sessions.index', [$eventSession->event->program, $eventSession->event])],
                ['Meeting', null],
            ]),
        ]);
    }

    public function storeMeetingAgenda(Request $request, EventSession $eventSession, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSession($request, $eventSession);
        $wasScheduled = $eventSession->status === 'scheduled';
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'section_type' => ['required', Rule::in(['worship', 'prayer', 'sermon', 'offering', 'announcement', 'media', 'hospitality', 'custom'])],
            'position' => ['required', 'integer', 'min:1', 'max:500'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:720'],
            'assignee_type' => ['nullable', Rule::in(['user', 'member'])],
            'user_id' => ['nullable', 'exists:users,id'],
            'member_id' => ['nullable', 'exists:members,id'],
            'role_title' => ['nullable', 'string', 'max:120'],
            'responsibility_notes' => ['nullable', 'string', 'max:1200'],
            'resource_reference' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,ppt,pptx,txt,jpg,jpeg,png'],
        ]);

        $assigneeType = $validated['assignee_type'] ?? null;
        if ($assigneeType === 'user' && empty($validated['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'Choose a responsible user.']);
        }
        if ($assigneeType === 'member' && empty($validated['member_id'])) {
            throw ValidationException::withMessages(['member_id' => 'Choose a responsible member.']);
        }

        $attachmentPath = $request->file('attachment')?->store('meeting-agendas/'.$eventSession->church_id, 'public');
        $section = ProgramSection::query()->create([
            'church_id' => $eventSession->church_id,
            'campus_id' => $eventSession->campus_id,
            'program_id' => $eventSession->event->program_id,
            'event_id' => $eventSession->event_id,
            'event_session_id' => $eventSession->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'resource_reference' => $validated['resource_reference'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
            'section_type' => $validated['section_type'],
            'position' => $validated['position'],
            'planned_start_time' => $validated['planned_start_time'] ?? null,
            'planned_duration_minutes' => $validated['planned_duration_minutes'] ?? null,
            'status' => 'active',
        ]);

        if ($assigneeType) {
            $assignment = ProgramSectionAssignment::query()->create([
                'church_id' => $section->church_id,
                'campus_id' => $section->campus_id,
                'program_section_id' => $section->id,
                'user_id' => $assigneeType === 'user' ? $validated['user_id'] : null,
                'member_id' => $assigneeType === 'member' ? $validated['member_id'] : null,
                'role_title' => $validated['role_title'] ?? 'Responsible person',
                'responsibility_notes' => $validated['responsibility_notes'] ?? null,
                'call_time' => null,
                'status' => 'assigned',
            ]);
        }

        if ($wasScheduled) {
            $eventSession->update(['status' => 'draft']);
        }

        $activityLogger->log('Meetings', 'meeting_agenda_item_created', $section->title.' was added to the meeting agenda.', $section, ['resource' => 'Meeting Agenda', 'risk' => 'low', 'status' => 'success'], $request);

        if ($wasScheduled) {
            $this->requestApproval($request, $eventSession, 'publish_meeting', [
                'title' => $eventSession->title,
                'event' => $eventSession->event?->title,
                'agenda_items' => ProgramSection::query()->where('event_session_id', $eventSession->id)->count(),
                'reason' => 'Agenda updated',
            ]);
        }

        return back()->with('status', 'Agenda item added to the meeting. Submit the meeting for approval before publishing.');
    }

    public function updateMeeting(Request $request, EventSession $eventSession, ActivityLogger $activityLogger, ZenderWhatsAppNotifier $notifier): RedirectResponse
    {
        $this->authorizeSession($request, $eventSession);
        $wasScheduled = $eventSession->status === 'scheduled';

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
        if ($wasScheduled) {
            $eventSession->update(['status' => 'draft']);
            $this->requestApproval($request, $eventSession, 'publish_meeting', [
                'title' => $eventSession->title,
                'event' => $eventSession->event?->title,
                'reason' => 'Meeting details updated',
            ]);
        }
        $this->syncAttendanceMethods($eventSession->fresh());

        $activityLogger->log('Meetings', 'meeting_updated', $eventSession->title.' meeting settings were updated.', $eventSession, ['resource' => 'Meeting', 'risk' => 'low', 'status' => 'success'], $request);
        $notifier->notify(
            (int) $eventSession->church_id,
            "Meeting update: {$eventSession->title} was updated.\n\nEvent: {$eventSession->event?->title}\nDate: ".$eventSession->session_date?->format('M d, Y')."\nType: {$eventSession->meeting_type}",
            'MeetingUpdated',
            (int) $eventSession->campus_id,
            null,
            "Meeting updated: {$eventSession->title}",
        );
        $this->domainNotifications->audience(
            (int) $eventSession->church_id,
            $eventSession->campus_id ? (int) $eventSession->campus_id : null,
            'EventSessionUpdated',
            'events',
            "Meeting updated: {$eventSession->title}",
            "The meeting details for {$eventSession->title} were updated.",
            ['in_app'],
            ['url' => route('event-sessions.meeting', $eventSession)],
        );

        return back()->with('status', 'Meeting updated.');
    }

    public function room(Request $request, EventSession $eventSession, string $provider, ActivityLogger $activityLogger): View
    {
        return $this->renderRoom($request, $eventSession, $provider, $activityLogger);
    }

    public function shortRoom(Request $request, string $code, string $provider, ActivityLogger $activityLogger): View|RedirectResponse
    {
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $eventSession = $this->eventSessionFromShortCode($code);

        if ($request->user()) {
            return redirect()->route('meetings.rooms.show', [$eventSession, $provider]);
        }

        $eventSession->load(['event.program', 'campus', 'attendanceSession']);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        abort_unless($this->sessionHasSelectedProvider($eventSession, $provider), 403);
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);

        $integration = MeetingIntegration::query()
            ->where('church_id', $eventSession->church_id)
            ->where('provider', $provider)
            ->firstOrFail();
        abort_unless($integration->enabled, 403);

        $guest = $request->session()->get($this->guestRoomSessionKey($eventSession, $provider));

        if (! filled($guest['name'] ?? null)) {
            return view('events.guest-join', [
                'session' => $eventSession,
                'provider' => $provider,
                'meta' => $this->providerMeta()[$provider],
                'joinUrl' => route('meetings.rooms.short.join', [$this->shortRoomCode($eventSession), $provider]),
                'loginUrl' => route('meetings.rooms.show', [$eventSession, $provider]),
            ]);
        }

        return $this->renderRoom($request, $eventSession, $provider, $activityLogger, true, $guest);
    }

    public function joinShortRoom(Request $request, string $code, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $eventSession = $this->eventSessionFromShortCode($code);
        $eventSession->load(['attendanceSession']);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        abort_unless($this->sessionHasSelectedProvider($eventSession, $provider), 403);
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $request->session()->put($this->guestRoomSessionKey($eventSession, $provider), [
            'name' => trim($validated['guest_name']),
            'identity' => 'guest-'.$eventSession->getKey().'-'.$provider.'-'.Str::lower((string) Str::random(10)),
        ]);

        return redirect()->route('meetings.rooms.short', [$this->shortRoomCode($eventSession), $provider]);
    }

    private function renderRoom(Request $request, EventSession $eventSession, string $provider, ActivityLogger $activityLogger, bool $guestAccess = false, array $guest = []): View
    {
        if (! $guestAccess) {
            $this->authorizeSession($request, $eventSession);
        }

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

        $member = $guestAccess ? null : $this->memberForUser($request);
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
            ? $this->liveKitRoomPayload($integration, $eventSession, $attendanceSession, $request, $record, $activeRoomParticipants->count(), $guestAccess ? $guest : [])
            : null;

        if ($record && $provider !== 'livekit') {
            $activityLogger->log('Meetings', 'meeting_room_joined', $member->first_name.' joined '.$provider.' internally.', $record, ['resource' => 'Built-in Meeting Room', 'risk' => 'low', 'status' => 'success'], $request);
        }

        $agendaSections = $this->agendaSectionsForSession($eventSession);
        $canManageRoomInteractions = ! $guestAccess && $this->canManageStudioBackroom($request);
        $shortRoomCode = $this->shortRoomCode($eventSession);

        return view('events.room', [
            'session' => $eventSession,
            'attendanceSession' => $attendanceSession->load(['records.member']),
            'provider' => $provider,
            'meta' => $this->providerMeta()[$provider],
            'member' => $member,
            'record' => $record,
            'liveKitPayload' => $liveKitPayload,
            'activeRoomParticipants' => $activeRoomParticipants,
            'agendaSections' => $agendaSections,
            'canManageRoomInteractions' => $canManageRoomInteractions,
            'guestParticipant' => $guestAccess ? $guest : null,
            'studioStatePayload' => $this->studioStatePayload($eventSession, $provider),
            'studioStateUrl' => route('meetings.rooms.short.state', [$shortRoomCode, $provider]),
            'shortRoomCode' => $shortRoomCode,
            'shortRoomUrl' => route('meetings.rooms.short', [$shortRoomCode, $provider]),
            'breadcrumbs' => $this->breadcrumbs([
                ['Meetings', route('meetings.index')],
                [$eventSession->title, route('event-sessions.meeting', $eventSession)],
                ['Built-in Room', null],
            ]),
        ]);
    }

    private function eventSessionFromShortCode(string $code): EventSession
    {
        abort_unless(preg_match('/^([0-9a-z]+)-([0-9a-f]{16})$/i', $code, $matches) === 1, 404);

        $eventSessionId = (int) base_convert(Str::lower($matches[1]), 36, 10);
        $expectedSignature = substr(hash_hmac('sha256', EventSession::class.':'.$eventSessionId, (string) config('app.key')), 0, 16);

        abort_unless($eventSessionId > 0 && hash_equals($expectedSignature, Str::lower($matches[2])), 404);

        return EventSession::query()->findOrFail($eventSessionId);
    }

    private function shortRoomCode(EventSession $eventSession): string
    {
        $encodedId = Str::lower(base_convert((string) $eventSession->getKey(), 10, 36));
        $signature = substr(hash_hmac('sha256', EventSession::class.':'.$eventSession->getKey(), (string) config('app.key')), 0, 16);

        return $encodedId.'-'.$signature;
    }

    private function guestRoomSessionKey(EventSession $eventSession, string $provider): string
    {
        return 'meeting_guest.'.$eventSession->getKey().'.'.$provider;
    }

    public function studio(Request $request, EventSession $eventSession, string $provider): View
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $eventSession->load(['event.program', 'campus', 'attendanceSession']);
        abort_unless($this->sessionHasSelectedProvider($eventSession, $provider), 403);

        [$state, $scenes] = $this->ensureStudioSetup($eventSession, $provider);
        $agendaSections = $this->agendaSectionsForSession($eventSession);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        $participants = $this->activeRoomParticipants($attendanceSession, $provider);
        $sourceParticipants = $attendanceSession->records()
            ->with('member')
            ->where('final_method', $provider)
            ->latest('checked_in_at')
            ->get();
        $integration = MeetingIntegration::query()
            ->where('church_id', $eventSession->church_id)
            ->where('provider', $provider)
            ->first();
        $poll = MeetingPoll::query()
            ->with(['options'])
            ->where('event_session_id', $eventSession->id)
            ->latest()
            ->first();
        $questions = MeetingQnaItem::query()
            ->where('event_session_id', $eventSession->id)
            ->latest('is_pinned')
            ->latest()
            ->limit(30)
            ->get();

        return view('events.studio', [
            'session' => $eventSession,
            'provider' => $provider,
            'meta' => $this->providerMeta()[$provider],
            'state' => $state->load(['liveScene', 'previewScene']),
            'scenes' => $scenes,
            'agendaSections' => $agendaSections,
            'participants' => $participants,
            'sourceParticipants' => $sourceParticipants,
            'poll' => $poll,
            'questions' => $questions,
            'studioStatePayload' => $this->studioStatePayload($eventSession, $provider),
            'studioLiveKitPayload' => $provider === 'livekit' && $integration?->enabled ? $this->liveKitStudioPayload($integration, $eventSession, $request) : null,
            'lowerThirdBackgroundPresets' => $this->lowerThirdBackgroundPresets(),
            'roomUrl' => route('meetings.rooms.show', [$eventSession, $provider]),
            'shortRoomUrl' => route('meetings.rooms.short', [$this->shortRoomCode($eventSession), $provider]),
        ]);
    }

    public function storeStudioScene(Request $request, EventSession $eventSession, string $provider): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'scene_type' => ['required', Rule::in(['camera', 'screen', 'scripture', 'presentation', 'countdown', 'media', 'agenda'])],
            'description' => ['nullable', 'string', 'max:500'],
            'source_identity' => ['nullable', 'string', 'max:180'],
            'manual_source_identity' => ['nullable', 'string', 'max:180'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'source_kind' => ['nullable', Rule::in(['camera', 'screen'])],
            'scripture_reference' => ['nullable', 'string', 'max:120'],
            'scripture_text' => ['nullable', 'string', 'max:700'],
            'countdown_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'media_url' => ['nullable', 'string', 'max:500'],
            'agenda_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings = [];
        $mediaUrl = null;
        $sceneType = $validated['scene_type'];

        if (in_array($sceneType, ['camera', 'screen'], true)) {
            $identity = trim((string) (($validated['manual_source_identity'] ?? null) ?: ($validated['source_identity'] ?? null) ?: ''));
            $settings = [
                'source_identity' => $identity ?: null,
                'source_name' => ($validated['source_name'] ?? null) ?: ($identity ?: null),
                'source_kind' => $sceneType === 'screen' ? 'screen' : ($validated['source_kind'] ?? 'camera'),
            ];
        } elseif ($sceneType === 'scripture') {
            $settings = [
                'reference' => $validated['scripture_reference'] ?? null,
                'text' => $validated['scripture_text'] ?? null,
            ];
        } elseif ($sceneType === 'countdown') {
            $settings = ['minutes' => (int) ($validated['countdown_minutes'] ?? 5)];
        } elseif (in_array($sceneType, ['media', 'presentation'], true)) {
            $mediaUrl = $validated['media_url'] ?? null;
            $settings = ['media_url' => $mediaUrl];
        } elseif ($sceneType === 'agenda') {
            $settings = ['notes' => $validated['agenda_notes'] ?? null];
        }

        MeetingScene::query()->create([
            'church_id' => $eventSession->church_id,
            'campus_id' => $eventSession->campus_id,
            'event_session_id' => $eventSession->id,
            'title' => $validated['title'],
            'scene_type' => $sceneType,
            'description' => $validated['description'] ?? null,
            'media_url' => $mediaUrl,
            'position' => (int) MeetingScene::query()->where('event_session_id', $eventSession->id)->max('position') + 1,
            'status' => 'ready',
            'settings' => collect($settings)->reject(fn ($value): bool => $value === null || $value === '')->all(),
        ]);

        return back()->with('status', 'Studio scene added.');
    }

    public function previewStudioScene(Request $request, EventSession $eventSession, string $provider, MeetingScene $scene): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        $this->authorizeStudioScene($eventSession, $scene);
        $state = $this->ensureStudioSetup($eventSession, $provider)[0];
        $state->update(['preview_scene_id' => $scene->id, 'updated_by' => $request->user()?->id]);

        return back()->with('status', 'Preview scene selected.');
    }

    public function takeStudioSceneLive(Request $request, EventSession $eventSession, string $provider, MeetingScene $scene): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        $this->authorizeStudioScene($eventSession, $scene);
        $state = $this->ensureStudioSetup($eventSession, $provider)[0];

        DB::transaction(function () use ($eventSession, $scene, $state, $request): void {
            MeetingScene::query()->where('event_session_id', $eventSession->id)->update(['is_live' => false]);
            $scene->update(['is_live' => true, 'status' => 'live']);
            $state->update([
                'live_scene_id' => $scene->id,
                'preview_scene_id' => $scene->id,
                'stream_status' => 'live',
                'updated_by' => $request->user()?->id,
            ]);
        });

        return back()->with('status', 'Scene is now live.');
    }

    public function updateStudioSceneSource(Request $request, EventSession $eventSession, string $provider, MeetingScene $scene): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        $this->authorizeStudioScene($eventSession, $scene);

        $validated = $request->validate([
            'source_identity' => ['nullable', 'string', 'max:180'],
            'manual_source_identity' => ['nullable', 'string', 'max:180'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'source_kind' => ['required', Rule::in(['camera', 'screen'])],
        ]);

        $identity = trim((string) (($validated['manual_source_identity'] ?? null) ?: ($validated['source_identity'] ?? null) ?: ''));
        $settings = $scene->settings ?? [];
        $settings['source_identity'] = $identity ?: null;
        $settings['source_name'] = $validated['source_name'] ?: $identity ?: null;
        $settings['source_kind'] = $validated['source_kind'];

        $scene->update(['settings' => $settings]);

        return back()->with('status', 'Scene source updated.');
    }

    public function destroyStudioScene(Request $request, EventSession $eventSession, string $provider, MeetingScene $scene): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        $this->authorizeStudioScene($eventSession, $scene);

        $activeSceneCount = MeetingScene::query()
            ->where('event_session_id', $eventSession->id)
            ->count();

        if ($activeSceneCount <= 1) {
            return back()->with('status', 'Keep at least one studio screen available.');
        }

        $state = $this->ensureStudioSetup($eventSession, $provider)[0];
        $replacement = MeetingScene::query()
            ->where('event_session_id', $eventSession->id)
            ->where($scene->getKeyName(), '!=', $scene->getKey())
            ->orderBy('position')
            ->first();

        DB::transaction(function () use ($request, $scene, $state, $replacement): void {
            $updates = ['updated_by' => $request->user()?->id];

            if ((int) $state->live_scene_id === (int) $scene->getKey()) {
                $updates['live_scene_id'] = $replacement?->id;
                $replacement?->update(['is_live' => true, 'status' => 'live']);
            }

            if ((int) $state->preview_scene_id === (int) $scene->getKey()) {
                $updates['preview_scene_id'] = $replacement?->id;
            }

            $state->update($updates);
            $scene->update(['is_live' => false, 'status' => 'archived']);
            $scene->delete();
        });

        return back()->with('status', 'Studio screen deleted.');
    }

    public function updateStudioState(Request $request, EventSession $eventSession, string $provider): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        $state = $this->ensureStudioSetup($eventSession, $provider)[0];
        $backgroundPresets = $this->lowerThirdBackgroundPresets();

        $validated = $request->validate([
            'speaker_name' => ['nullable', 'string', 'max:120'],
            'speaker_role' => ['nullable', 'string', 'max:120'],
            'service_label' => ['nullable', 'string', 'max:120'],
            'scripture_reference' => ['nullable', 'string', 'max:120'],
            'scripture_text' => ['nullable', 'string', 'max:700'],
            'ticker_text' => ['nullable', 'string', 'max:160'],
            'countdown_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'chat_visible' => ['nullable', 'boolean'],
            'qna_enabled' => ['nullable', 'boolean'],
            'poll_visible' => ['nullable', 'boolean'],
            'stream_status' => ['nullable', Rule::in(['preview', 'live', 'paused', 'ended'])],
            'audio_mixer' => ['nullable', 'array'],
            'audio_mixer.*' => ['nullable', 'integer', 'min:-60', 'max:12'],
            'quick_actions' => ['nullable', 'array'],
            'quick_actions.*' => ['nullable', 'string', 'max:180'],
            'destination_name' => ['nullable', 'string', 'max:120'],
            'destination_status' => ['nullable', Rule::in(['ready', 'live', 'paused', 'offline'])],
            'lower_third_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'lower_third_background_preset' => ['nullable', Rule::in(array_keys($backgroundPresets))],
            'remove_lower_third_background' => ['nullable', 'boolean'],
        ]);
        $lowerThirdBackgroundUrl = null;
        if ($request->hasFile('lower_third_background')) {
            $lowerThirdBackgroundUrl = Storage::disk('public')->url(
                $request->file('lower_third_background')->store('studio/lower-thirds', 'public')
            );
        }

        $lowerThirdPreset = $validated['lower_third_background_preset'] ?? null;
        $removeLowerThirdBackground = $request->boolean('remove_lower_third_background');
        $hasLowerThird = $request->hasAny(['speaker_name', 'speaker_role', 'service_label', 'lower_third_background_preset', 'remove_lower_third_background']) || filled($lowerThirdBackgroundUrl);
        $hasScripture = $request->hasAny(['scripture_reference', 'scripture_text']);
        $lowerThird = $state->lower_third ?? [];

        if ($hasLowerThird) {
            $lowerThird = [
                'speaker_name' => $request->has('speaker_name') ? ($validated['speaker_name'] ?? null) : ($lowerThird['speaker_name'] ?? null),
                'speaker_role' => $request->has('speaker_role') ? ($validated['speaker_role'] ?? null) : ($lowerThird['speaker_role'] ?? null),
                'service_label' => $request->has('service_label') ? ($validated['service_label'] ?? null) : ($lowerThird['service_label'] ?? null),
                'background_url' => $removeLowerThirdBackground || filled($lowerThirdPreset) ? null : ($lowerThirdBackgroundUrl ?: ($lowerThird['background_url'] ?? null)),
                'background_style' => $removeLowerThirdBackground || filled($lowerThirdBackgroundUrl) ? null : (filled($lowerThirdPreset) ? $backgroundPresets[$lowerThirdPreset]['style'] : ($lowerThird['background_style'] ?? null)),
                'background_label' => $removeLowerThirdBackground || filled($lowerThirdBackgroundUrl) ? null : (filled($lowerThirdPreset) ? $backgroundPresets[$lowerThirdPreset]['label'] : ($lowerThird['background_label'] ?? null)),
            ];
        }

        $quickActions = collect($state->quick_actions ?? [])
            ->merge($validated['quick_actions'] ?? [])
            ->reject(fn ($value): bool => $value === null || $value === '')
            ->all();
        $audioMixer = collect($validated['audio_mixer'] ?? [])
            ->map(fn ($value): int => (int) $value)
            ->all();
        $destinations = $state->destinations ?? [];

        if (filled($validated['destination_name'] ?? null)) {
            $destinations[] = [
                'name' => $validated['destination_name'],
                'status' => $validated['destination_status'] ?? 'ready',
            ];
        }

        $state->update([
            'lower_third' => $hasLowerThird ? $lowerThird : $state->lower_third,
            'scripture' => $hasScripture ? [
                'reference' => $validated['scripture_reference'] ?? null,
                'text' => $validated['scripture_text'] ?? null,
            ] : $state->scripture,
            'ticker_text' => $request->has('ticker_text') ? ($validated['ticker_text'] ?? null) : $state->ticker_text,
            'countdown_ends_at' => $request->has('countdown_minutes') ? (((int) ($validated['countdown_minutes'] ?? 0)) > 0 ? now()->addMinutes((int) $validated['countdown_minutes']) : null) : $state->countdown_ends_at,
            'chat_visible' => $request->has('chat_visible') ? $request->boolean('chat_visible') : $state->chat_visible,
            'qna_enabled' => $request->has('qna_enabled') ? $request->boolean('qna_enabled') : $state->qna_enabled,
            'poll_visible' => $request->has('poll_visible') ? $request->boolean('poll_visible') : $state->poll_visible,
            'stream_status' => $validated['stream_status'] ?? $state->stream_status,
            'audio_mixer' => $request->has('audio_mixer') ? array_merge($state->audio_mixer ?? [], $audioMixer) : $state->audio_mixer,
            'destinations' => filled($validated['destination_name'] ?? null) ? $destinations : $state->destinations,
            'quick_actions' => $request->has('quick_actions') ? $quickActions : $state->quick_actions,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('status', 'Studio controls updated.');
    }

    public function storeStudioPoll(Request $request, EventSession $eventSession, string $provider): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:180'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['nullable', 'string', 'max:100'],
        ]);
        $options = collect($validated['options'])->map(fn ($option): string => trim((string) $option))->filter()->values();
        abort_if($options->count() < 2, 422, 'Add at least two poll options.');

        DB::transaction(function () use ($eventSession, $request, $validated, $options): void {
            MeetingPoll::query()->where('event_session_id', $eventSession->id)->update(['is_open' => false]);
            $poll = MeetingPoll::query()->create([
                'church_id' => $eventSession->church_id,
                'campus_id' => $eventSession->campus_id,
                'event_session_id' => $eventSession->id,
                'question' => $validated['question'],
                'is_open' => true,
                'show_results' => true,
                'created_by' => $request->user()?->id,
            ]);
            $options->each(fn (string $label, int $index) => $poll->options()->create(['label' => $label, 'position' => $index + 1]));
        });

        return back()->with('status', 'Poll published.');
    }

    public function updateStudioPoll(Request $request, EventSession $eventSession, string $provider, MeetingPoll $poll): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        abort_unless($poll->event_session_id === $eventSession->id, 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['open', 'close', 'hide_results', 'show_results'])],
        ]);

        $poll->update(match ($validated['action']) {
            'open' => ['is_open' => true],
            'close' => ['is_open' => false],
            'hide_results' => ['show_results' => false],
            'show_results' => ['show_results' => true],
        });

        return back()->with('status', 'Poll updated.');
    }

    public function updateStudioQuestion(Request $request, EventSession $eventSession, string $provider, MeetingQnaItem $question): RedirectResponse
    {
        $this->authorizeStudioBackroom($request, $eventSession);
        abort_unless($question->event_session_id === $eventSession->id, 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['pin', 'unpin', 'answered', 'open'])],
        ]);

        $question->update(match ($validated['action']) {
            'pin' => ['is_pinned' => true],
            'unpin' => ['is_pinned' => false],
            'answered' => ['status' => 'answered', 'answered_at' => now()],
            'open' => ['status' => 'open', 'answered_at' => null],
        });

        return back()->with('status', 'Question updated.');
    }

    public function publicStudioState(string $code, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        return response()->json($this->studioStatePayload($this->eventSessionFromShortCode($code), $provider));
    }

    public function storePublicQuestion(Request $request, string $code, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);
        $eventSession = $this->eventSessionFromShortCode($code);
        $state = $this->ensureStudioSetup($eventSession, $provider)[0];
        abort_unless($state->qna_enabled, 403);

        $validated = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $guest = $request->session()->get($this->guestRoomSessionKey($eventSession, $provider), []);
        abort_unless($request->user() || filled($guest['identity'] ?? null), 403);
        $author = $request->user()?->name ?: ($guest['name'] ?? 'Guest');

        MeetingQnaItem::query()->create([
            'church_id' => $eventSession->church_id,
            'campus_id' => $eventSession->campus_id,
            'event_session_id' => $eventSession->id,
            'user_id' => $request->user()?->id,
            'guest_identity' => $guest['identity'] ?? null,
            'author_name' => $author,
            'body' => $validated['body'],
        ]);

        return response()->json(['ok' => true, 'studio' => $this->studioStatePayload($eventSession, $provider)]);
    }

    public function storePublicPollVote(Request $request, string $code, string $provider, MeetingPoll $poll): JsonResponse
    {
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);
        $eventSession = $this->eventSessionFromShortCode($code);
        abort_unless($poll->event_session_id === $eventSession->id && $poll->is_open, 403);

        $validated = $request->validate(['option' => ['required', 'integer']]);
        $option = MeetingPollOption::query()
            ->where('meeting_poll_id', $poll->id)
            ->whereKey($validated['option'])
            ->firstOrFail();
        $guest = $request->session()->get($this->guestRoomSessionKey($eventSession, $provider), []);
        abort_unless($request->user() || filled($guest['identity'] ?? null), 403);
        $guestIdentity = $request->user() ? null : $guest['identity'];

        DB::transaction(function () use ($poll, $option, $request, $guestIdentity): void {
            $vote = MeetingPollVote::query()->updateOrCreate(
                [
                    'meeting_poll_id' => $poll->id,
                    'user_id' => $request->user()?->id,
                    'guest_identity' => $request->user() ? null : $guestIdentity,
                ],
                ['meeting_poll_option_id' => $option->id],
            );

            $poll->options->each(fn (MeetingPollOption $pollOption) => $pollOption->update(['votes_count' => MeetingPollVote::query()->where('meeting_poll_option_id', $pollOption->id)->count()]));
        });

        return response()->json(['ok' => true, 'studio' => $this->studioStatePayload($eventSession, $provider)]);
    }

    private function ensureStudioSetup(EventSession $eventSession, string $provider): array
    {
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);
        abort_unless($this->sessionHasSelectedProvider($eventSession, $provider), 403);

        $sceneDefaults = [
            ['Main Camera', 'camera', 'Primary speaker camera'],
            ['Screen Share', 'screen', 'Participant screen share'],
            ['Worship Band', 'camera', 'Worship team and stage audio'],
            ['Audience Cam', 'camera', 'Congregation view'],
            ['Scripture Slide', 'scripture', 'Bible reference or sermon verse'],
            ['Presentation', 'presentation', 'Slides and media'],
            ['Countdown', 'countdown', 'Service countdown timer'],
        ];

        foreach ($sceneDefaults as $index => [$title, $type, $description]) {
            MeetingScene::query()->firstOrCreate(
                ['event_session_id' => $eventSession->id, 'title' => $title],
                [
                    'church_id' => $eventSession->church_id,
                    'campus_id' => $eventSession->campus_id,
                    'scene_type' => $type,
                    'description' => $description,
                    'position' => $index + 1,
                    'status' => $index === 0 ? 'live' : 'ready',
                    'settings' => in_array($type, ['camera', 'screen'], true) ? ['source_kind' => $type === 'screen' ? 'screen' : 'camera'] : [],
                    'is_live' => $index === 0,
                ],
            );
        }

        $scenes = MeetingScene::query()
            ->where('event_session_id', $eventSession->id)
            ->orderBy('position')
            ->get();
        $liveScene = $scenes->firstWhere('is_live', true) ?: $scenes->first();

        $state = MeetingStudioState::query()->firstOrCreate(
            ['event_session_id' => $eventSession->id, 'provider' => $provider],
            [
                'church_id' => $eventSession->church_id,
                'campus_id' => $eventSession->campus_id,
                'live_scene_id' => $liveScene?->id,
                'preview_scene_id' => $liveScene?->id,
                'lower_third' => [
                    'speaker_name' => null,
                    'speaker_role' => null,
                    'service_label' => $eventSession->title,
                ],
                'scripture' => ['reference' => null, 'text' => null],
                'chat_visible' => true,
                'qna_enabled' => true,
                'poll_visible' => true,
                'stream_status' => 'preview',
                'audio_mixer' => [
                    'pastor_mic' => -2,
                    'worship_band' => -5,
                    'audience' => -10,
                    'background_music' => -18,
                    'system_audio' => -6,
                ],
                'destinations' => [
                    ['name' => 'YouTube Live', 'status' => 'ready'],
                    ['name' => 'Facebook Live', 'status' => 'ready'],
                    ['name' => 'OBS (RTMP)', 'status' => 'ready'],
                ],
                'quick_actions' => [],
            ],
        );

        if (! $state->live_scene_id && $liveScene) {
            $state->update(['live_scene_id' => $liveScene->id, 'preview_scene_id' => $liveScene->id]);
        }

        return [$state->fresh(['liveScene', 'previewScene']), $scenes];
    }

    private function agendaSectionsForSession(EventSession $eventSession): Collection
    {
        return ProgramSection::query()
            ->with(['assignments.user', 'assignments.member'])
            ->where('program_id', $eventSession->event->program_id)
            ->where(fn (Builder $query) => $query
                ->whereNull('event_session_id')
                ->where(fn (Builder $scope) => $scope->whereNull('event_id')->orWhere('event_id', $eventSession->event_id))
                ->orWhere('event_session_id', $eventSession->id))
            ->orderBy('position')
            ->orderBy('planned_start_time')
            ->get();
    }

    private function notifyMeetingAgendaAssignment(ProgramSectionAssignment $assignment, EventSession $eventSession): void
    {
        $subject = 'Meeting agenda responsibility assigned';
        $message = 'You are responsible for "'.$assignment->section->title.'" in '.$eventSession->title.' on '.$eventSession->session_date?->format('M d, Y').'.';
        $metadata = [
            'url' => route('event-sessions.meeting', $eventSession),
            'event_session_id' => $eventSession->id,
            'event_title' => $eventSession->title,
            'agenda_item' => $assignment->section->title,
            'responsible_role' => $assignment->role_title,
        ];

        if ($assignment->user) {
            $this->domainNotifications->user($assignment->user, 'MeetingAgendaAssigned', 'events', $subject, $message, ['in_app', 'email'], $metadata);
        } elseif ($assignment->member) {
            $this->domainNotifications->member($assignment->member, 'MeetingAgendaAssigned', 'events', $subject, $message, ['in_app', 'email'], $metadata);
        }
    }

    private function lowerThirdBackgroundPresets(): array
    {
        return [
            'royal-violet' => [
                'label' => 'Royal Violet',
                'style' => 'background: radial-gradient(circle at 18% 35%, rgba(124,58,237,.62), transparent 26%), linear-gradient(90deg, rgba(0,0,0,.94), rgba(39,18,88,.9), rgba(7,19,33,.72));',
            ],
            'gold-sanctuary' => [
                'label' => 'Gold Sanctuary',
                'style' => 'background: radial-gradient(circle at 12% 45%, rgba(245,158,11,.45), transparent 24%), linear-gradient(90deg, rgba(0,0,0,.94), rgba(55,37,8,.9), rgba(7,19,33,.72));',
            ],
            'midnight-blue' => [
                'label' => 'Midnight Blue',
                'style' => 'background: radial-gradient(circle at 22% 30%, rgba(37,99,235,.55), transparent 28%), linear-gradient(90deg, rgba(0,0,0,.94), rgba(8,31,66,.9), rgba(5,10,20,.72));',
            ],
            'emerald-light' => [
                'label' => 'Emerald Light',
                'style' => 'background: radial-gradient(circle at 16% 36%, rgba(16,185,129,.42), transparent 25%), linear-gradient(90deg, rgba(0,0,0,.94), rgba(6,47,41,.88), rgba(7,19,33,.72));',
            ],
        ];
    }

    private function studioStatePayload(EventSession $eventSession, string $provider): array
    {
        $eventSession->loadMissing(['event.program', 'campus']);
        [$state, $scenes] = $this->ensureStudioSetup($eventSession, $provider);
        $shortRoomCode = $this->shortRoomCode($eventSession);
        $poll = MeetingPoll::query()
            ->with('options')
            ->where('event_session_id', $eventSession->id)
            ->latest()
            ->first();
        $questions = MeetingQnaItem::query()
            ->where('event_session_id', $eventSession->id)
            ->whereIn('status', ['open', 'answered'])
            ->latest('is_pinned')
            ->latest()
            ->limit(20)
            ->get();

        return [
            'updated_at' => $state->updated_at?->toIso8601String(),
            'stream_status' => $state->stream_status,
            'chat_visible' => $state->chat_visible,
            'qna_enabled' => $state->qna_enabled,
            'poll_visible' => $state->poll_visible,
            'ticker_text' => $state->ticker_text,
            'countdown_ends_at' => $state->countdown_ends_at?->toIso8601String(),
            'lower_third' => $state->lower_third ?: [],
            'scripture' => $state->scripture ?: [],
            'qna_submit_url' => route('meetings.rooms.short.qna.store', [$shortRoomCode, $provider]),
            'live_scene' => $state->liveScene ? [
                'id' => $state->liveScene->id,
                'title' => $state->liveScene->title,
                'type' => $state->liveScene->scene_type,
                'description' => $state->liveScene->description,
                'settings' => $state->liveScene->settings ?: [],
            ] : null,
            'preview_scene' => $state->previewScene ? [
                'id' => $state->previewScene->id,
                'title' => $state->previewScene->title,
                'type' => $state->previewScene->scene_type,
                'description' => $state->previewScene->description,
                'settings' => $state->previewScene->settings ?: [],
            ] : null,
            'scenes' => $scenes->map(fn (MeetingScene $scene): array => [
                'id' => $scene->id,
                'title' => $scene->title,
                'type' => $scene->scene_type,
                'description' => $scene->description,
                'settings' => $scene->settings ?: [],
                'is_live' => $scene->is_live,
            ])->values()->all(),
            'poll' => $poll ? [
                'id' => $poll->id,
                'question' => $poll->question,
                'is_open' => $poll->is_open,
                'show_results' => $poll->show_results,
                'vote_url' => route('meetings.rooms.short.polls.vote', [$shortRoomCode, $provider, $poll]),
                'options' => $poll->options->map(fn (MeetingPollOption $option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes' => $option->votes_count,
                ])->values()->all(),
            ] : null,
            'qna' => $questions->map(fn (MeetingQnaItem $question): array => [
                'id' => $question->id,
                'author' => $question->author_name,
                'body' => $question->body,
                'status' => $question->status,
                'votes' => $question->votes_count,
                'pinned' => $question->is_pinned,
                'at' => $question->created_at?->format('h:i A'),
            ])->values()->all(),
        ];
    }

    private function authorizeStudioScene(EventSession $eventSession, MeetingScene $scene): void
    {
        abort_unless($scene->event_session_id === $eventSession->id, 404);
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
            'identity' => ['nullable', 'string', 'max:180'],
            'participant_name' => ['nullable', 'string', 'max:120'],
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
                'livekit_identity' => $payload['identity'] ?? null,
                'participant_name' => $payload['participant_name'] ?? null,
                'avatar' => $request->user()?->avatar_src,
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

        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        $wasOpen = $attendanceSession->status === 'open';
        $attendanceSession->update([
            ...$validated,
            'methods' => $this->allowedRequestedMethods($eventSession, $validated['methods'] ?? $this->attendanceMethodsForSession($eventSession)),
            'require_authenticated' => (bool) ($validated['require_authenticated'] ?? false),
            'allow_guests' => (bool) ($validated['allow_guests'] ?? false),
            'expected_attendance' => (int) ($validated['expected_attendance'] ?? 0),
        ]);

        $activityLogger->log('Attendance', 'attendance_session_updated', $eventSession->title.' attendance policy was updated.', $eventSession, ['resource' => 'Attendance Session', 'risk' => 'low', 'status' => 'success'], $request);
        if (! $wasOpen && $attendanceSession->status === 'open') {
            $this->domainNotifications->audience(
                (int) $attendanceSession->church_id,
                $eventSession->campus_id ? (int) $eventSession->campus_id : null,
                'AttendanceSessionOpened',
                'attendance',
                "Check-in open: {$eventSession->title}",
                "Attendance check-in is now open for {$eventSession->title}.",
                ['in_app'],
                ['url' => route('attendance.methods', $attendanceSession)],
            );
        }

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
        if ($member) {
            $this->domainNotifications->member(
                $member,
                'AttendanceRecorded',
                'attendance',
                'Attendance confirmed',
                "Your attendance for {$eventSession->title} was recorded successfully.",
                ['in_app'],
                ['url' => route('attendance.records.show', [$attendanceSession, $member->opaqueId()])],
            );
        }

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
                ? SecretHash::make((string) $input['webhook_secret'])
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
        $timestamp = (int) $request->header('X-Meeting-Webhook-Timestamp', 0);

        $storedSecretHash = (string) ($settings['webhook_secret_hash'] ?? '');
        abort_unless($integration->enabled && $storedSecretHash !== '' && SecretHash::verify($secret, $storedSecretHash), 403);

        if (SecretHash::needsRehash($storedSecretHash)) {
            $settings['webhook_secret_hash'] = SecretHash::make($secret);
            $integration->forceFill(['settings' => $settings])->save();
        }
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);
        abort_unless($timestamp > 0 && abs(now()->timestamp - $timestamp) <= 300, 403);

        $replayKey = 'meeting-webhook:'.hash('sha256', implode('|', [
            $provider,
            (string) $attendanceSession->id,
            (string) $payload['email'],
            (string) ($payload['joined_at'] ?? ''),
            (string) ($payload['duration_minutes'] ?? ''),
            (string) ($payload['meeting_id'] ?? ''),
            (string) $timestamp,
        ]));
        abort_unless(Cache::add($replayKey, true, now()->addMinutes(10)), 409);

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

    private function createDefaultSession(Event $event): EventSession
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

        return $session;
    }

    private function copyTemplateAgenda(EventTemplate $template, Event $event): void
    {
        foreach ($template->agenda ?? [] as $item) {
            $section = ProgramSection::query()->create([
                'church_id' => $event->church_id,
                'campus_id' => $event->campus_id,
                'program_id' => $event->program_id,
                'event_id' => $event->id,
                'event_session_id' => null,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'resource_reference' => $item['resource_reference'] ?? null,
                'attachment_path' => $item['attachment_path'] ?? null,
                'attachment_name' => $item['attachment_name'] ?? null,
                'section_type' => $item['section_type'] ?? 'custom',
                'position' => $item['position'] ?? 1,
                'planned_start_time' => $item['planned_start_time'] ?? null,
                'planned_duration_minutes' => $item['planned_duration_minutes'] ?? null,
                'status' => 'active',
            ]);

            foreach ($item['assignments'] ?? [] as $assignment) {
                ProgramSectionAssignment::query()->create([
                    'church_id' => $section->church_id,
                    'campus_id' => $section->campus_id,
                    'program_section_id' => $section->id,
                    'user_id' => $assignment['user_id'] ?? null,
                    'member_id' => $assignment['member_id'] ?? null,
                    'role_title' => $assignment['role_title'] ?? 'Responsible person',
                    'responsibility_notes' => $assignment['responsibility_notes'] ?? null,
                    'status' => 'assigned',
                ]);
            }
        }
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

    private function requestApproval(Request $request, Event|EventSession|EventRecurrenceRule|ProgramSectionAssignment $resource, string $action, array $payload): Approval
    {
        $workflow = Workflow::query()
            ->where('church_id', $resource->church_id)
            ->where('module', 'events')
            ->where('status', 'active')
            ->latest('updated_at')
            ->first();

        if (! $workflow) {
            $workflow = Workflow::query()->create([
                'church_id' => $resource->church_id,
                'module' => 'events',
                'name' => 'Event & Meeting Approval',
                'status' => 'active',
                'steps' => [
                    'description' => 'Review and publish events and meetings.',
                    'approval_type' => 'sequential',
                    'timeout_hours' => 72,
                    'steps' => [
                        ['position' => 1, 'label' => 'Final Approval', 'role' => 'Church Administrator', 'mode' => 'required', 'required' => true],
                    ],
                ],
            ]);
        }

        $existing = Approval::query()
            ->where('approvable_type', $resource::class)
            ->where('approvable_id', $resource->id)
            ->where('action', $action)
            ->where('status', 'pending')
            ->latest()
            ->first();
        if ($existing) {
            return $existing;
        }

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
            ->each(fn (User $user) => $this->domainNotifications->user(
                $user,
                'ApprovalRequested',
                'system',
                'Approval required: '.Str::headline((string) $approval->action),
                'Review the pending workflow request in Workflow & Approvals.',
                ['in_app'],
                ['url' => route('workflows.index')],
                true,
            ));
    }

    private function notifyAssignment(ProgramSectionAssignment $assignment, string $subject, string $message): void
    {
        if ($assignment->user) {
            $this->domainNotifications->user($assignment->user, 'ProgramSectionAssigned', 'volunteers', $subject, $message, ['in_app'], ['url' => route('events.index')]);
        } elseif ($assignment->member) {
            $this->domainNotifications->member($assignment->member, 'ProgramSectionAssigned', 'volunteers', $subject, $message, ['in_app'], ['url' => route('events.index')]);
        }
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

    private function liveKitRoomPayload(MeetingIntegration $integration, EventSession $eventSession, AttendanceSession $attendanceSession, Request $request, ?AttendanceRecord $record, int $activeParticipantCount, array $guest = []): array
    {
        $settings = $integration->settings ?? [];
        $this->validateLiveKitSettings($integration);

        $room = (string) ($eventSession->meeting_links['livekit']['room'] ?? Str::slug((string) ($settings['room_prefix'] ?? 'church')).'-livekit-'.$eventSession->id);
        $member = $this->memberForUser($request);
        $guestName = trim((string) ($guest['name'] ?? ''));
        $guestIdentity = trim((string) ($guest['identity'] ?? ''));
        $identity = $guestIdentity ?: ($member?->email ?: $request->user()?->email ?: 'guest-'.Str::random(8));
        $name = $guestName ?: ($member ? trim($member->first_name.' '.$member->last_name) : ($request->user()?->name ?? 'Guest'));
        $ttlSeconds = (int) ($settings['participant_token_ttl_seconds'] ?? 7200);
        $isGuest = filled($guestName);

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
                ['avatar' => $request->user()?->avatar_src],
            ),
            'ttl_label' => $this->formatLiveKitTokenTtl($ttlSeconds),
            'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
            'mark_attendance_url' => $isGuest ? null : route('meetings.rooms.attendance.store', [$eventSession, 'livekit']),
            'mark_checkout_url' => $isGuest ? null : route('meetings.rooms.checkout.store', [$eventSession, 'livekit']),
            'attendance_marked' => (bool) $record,
            'attendance_record_url' => $record && $member ? route('attendance.records.show', [$attendanceSession, $member->opaqueId()]) : null,
            'participant_count' => $activeParticipantCount,
        ];
    }

    private function liveKitStudioPayload(MeetingIntegration $integration, EventSession $eventSession, Request $request): array
    {
        $settings = $integration->settings ?? [];
        $this->validateLiveKitSettings($integration);

        $room = (string) ($eventSession->meeting_links['livekit']['room'] ?? Str::slug((string) ($settings['room_prefix'] ?? 'church')).'-livekit-'.$eventSession->id);
        $ttlSeconds = (int) ($settings['participant_token_ttl_seconds'] ?? 7200);
        $operatorName = $request->user()?->name ?? 'Studio Operator';
        $identity = 'studio-'.$eventSession->getKey().'-'.($request->user()?->getKey() ?? Str::random(8));

        return [
            'server_url' => (string) $settings['server_url'],
            'room' => $room,
            'identity' => $identity,
            'name' => $operatorName.' Studio',
            'token' => $this->generateLiveKitToken(
                (string) $settings['api_key'],
                $this->decryptLiveKitSecret($settings),
                $room,
                $identity,
                $operatorName.' Studio',
                $ttlSeconds,
                ['role' => 'studio', 'hidden' => true],
            ),
            'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
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

    private function generateLiveKitToken(string $apiKey, string $apiSecret, string $room, string $identity, string $name, int $ttlSeconds, array $metadata = []): string
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

        $metadata = array_filter($metadata, fn ($value): bool => filled($value));
        if ($metadata !== []) {
            $payload['metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
        }

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
            $secret = Crypt::decryptString((string) $settings['api_secret_encrypted']);
            $legacySecret = @unserialize($secret, ['allowed_classes' => false]);

            return is_string($legacySecret) ? $legacySecret : $secret;
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

        if (! $user) {
            return null;
        }

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

    private function canManageStudioBackroom(Request $request): bool
    {
        return $request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage studio');
    }

    private function authorizeStudioBackroom(Request $request, EventSession $session): void
    {
        abort_unless($this->canManageStudioBackroom($request), 403);
        abort_unless($request->user()?->canAccessChurch($session->church_id) && $request->user()?->canAccessCampus($session->campus_id), 403);
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
