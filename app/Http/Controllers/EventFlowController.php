<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceVerification;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\MeetingIntegration;
use App\Models\Member;
use App\Models\Program;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        if (filled($request->input('campus_id')) && ! filter_var($request->input('campus_id'), FILTER_VALIDATE_INT)) {
            $request->merge(['campus_id' => OpaqueId::decode($request->input('campus_id'), Campus::class)]);
        }

        $validated = $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
        ]);

        $validated = $this->applyActorScope($request, $validated);
        $program = Program::query()->create($validated);

        $activityLogger->log('Programs', 'program_created', $program->name.' was created.', $program, ['resource' => 'Program', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('programs.events', $program)->with('status', 'Program created.');
    }

    public function events(Request $request, ?Program $program = null): View
    {
        $this->authorizeEvents($request);
        $program ??= $this->scopePrograms(Program::query(), $request)->latest('starts_on')->first();

        $events = Event::query()
            ->withCount('sessions')
            ->with('program')
            ->when($program, fn (Builder $query) => $query->where('program_id', $program->id))
            ->where(fn (Builder $query) => $this->scopeEventQuery($query, $request))
            ->latest('starts_at')
            ->paginate(10)
            ->withQueryString();

        return view('events.events', [
            'program' => $program,
            'programs' => $this->scopePrograms(Program::query(), $request)->orderBy('name')->get(),
            'events' => $events,
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

        $sessions = $event->sessions()->with('attendanceSession')->orderBy('session_date')->paginate(10)->withQueryString();

        return view('events.sessions', [
            'program' => $program,
            'event' => $event,
            'sessions' => $sessions,
            'campuses' => $this->visibleCampuses($request)->get(),
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
            'campus_id' => $validated['campus_id'] ?: $event->campus_id,
            'timezone' => $validated['timezone'] ?: config('app.timezone'),
            'meeting_links' => $this->meetingLinksFromRequest($request),
        ]);
        $this->ensureAttendanceSession($session);

        $activityLogger->log('Event Sessions', 'event_session_created', $session->title.' was created.', $session, ['resource' => 'Event Session', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('event-sessions.meeting', $session)->with('status', 'Event session created.');
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
            'breadcrumbs' => $this->breadcrumbs([['Calendar', null]]),
        ]);
    }

    public function meetings(Request $request): View
    {
        $this->authorizeEvents($request);

        $sessions = EventSession::query()
            ->with(['event.program', 'campus', 'attendanceSession'])
            ->where(fn (Builder $query) => $this->scopeSessionQuery($query, $request))
            ->where('session_date', '>=', now()->subDay()->toDateString())
            ->orderBy('session_date')
            ->paginate(10)
            ->withQueryString();

        return view('events.meetings', [
            'sessions' => $sessions,
            'integrations' => $this->providerIntegrations($request),
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
        $this->ensureAttendanceSession($eventSession->fresh());

        $activityLogger->log('Meetings', 'meeting_updated', $eventSession->title.' meeting settings were updated.', $eventSession, ['resource' => 'Meeting', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Meeting updated.');
    }

    public function room(Request $request, EventSession $eventSession, string $provider, ActivityLogger $activityLogger): View
    {
        $this->authorizeSession($request, $eventSession);
        abort_unless(in_array($provider, self::ONLINE_METHODS, true), 404);

        $eventSession->load(['event.program', 'campus', 'attendanceSession']);
        $attendanceSession = $this->ensureAttendanceSession($eventSession);
        abort_unless(in_array($provider, $attendanceSession->methods ?? [], true), 403);

        $integration = MeetingIntegration::query()
            ->where('church_id', $eventSession->church_id)
            ->where('provider', $provider)
            ->firstOrFail();
        abort_unless($integration->enabled, 403);

        $member = $this->memberForUser($request);
        $record = $member
            ? $this->storeAttendanceEvidence(
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
            )
            : null;

        if ($record) {
            $activityLogger->log('Meetings', 'meeting_room_joined', $member->first_name.' joined '.$provider.' internally.', $record, ['resource' => 'Built-in Meeting Room', 'risk' => 'low', 'status' => 'success'], $request);
        }

        return view('events.room', [
            'session' => $eventSession,
            'attendanceSession' => $attendanceSession->load(['records.member']),
            'provider' => $provider,
            'meta' => $this->providerMeta()[$provider],
            'member' => $member,
            'record' => $record,
            'breadcrumbs' => $this->breadcrumbs([
                ['Meetings', route('meetings.index')],
                [$eventSession->title, route('event-sessions.meeting', $eventSession)],
                ['Built-in Room', null],
            ]),
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
            'methods' => $validated['methods'] ?? $this->defaultMethods($eventSession->meeting_type),
            'require_authenticated' => (bool) ($validated['require_authenticated'] ?? false),
            'allow_guests' => (bool) ($validated['allow_guests'] ?? false),
            'expected_attendance' => (int) ($validated['expected_attendance'] ?? 0),
        ]);

        $activityLogger->log('Attendance', 'attendance_session_updated', $eventSession->title.' attendance policy was updated.', $eventSession, ['resource' => 'Attendance Session', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Attendance session updated.');
    }

    public function attendanceIndex(Request $request): View
    {
        $this->authorizeAttendance($request);

        $attendanceSessions = AttendanceSession::query()
            ->with(['eventSession.event.program', 'records'])
            ->where(fn (Builder $query) => $this->scopeAttendanceQuery($query, $request))
            ->latest('opens_at')
            ->paginate(10)
            ->withQueryString();

        return view('events.attendance-index', [
            'attendanceSessions' => $attendanceSessions,
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
        $resolvedMember = $member === 'guest' ? null : Member::query()->whereKey(\App\Support\OpaqueId::decode($member, Member::class))->firstOrFail();
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
        ]);

        foreach (self::PROVIDERS as $provider) {
            $input = $validated['providers'][$provider] ?? [];
            $existing = MeetingIntegration::query()->where('church_id', $churchId)->where('provider', $provider)->first();
            $existingSettings = $existing?->settings ?? [];
            $enabled = (bool) ($input['enabled'] ?? false);
            $webhookSecretHash = filled($input['webhook_secret'] ?? null)
                ? hash('sha256', (string) $input['webhook_secret'])
                : ($existingSettings['webhook_secret_hash'] ?? null);

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
            ->findOrFail(\App\Support\OpaqueId::decode($payload['attendance_session'], AttendanceSession::class));

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
                'methods' => $this->defaultMethods($session->meeting_type),
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

    private function meetingLinksFromRequest(Request $request): array
    {
        return collect(self::PROVIDERS)->mapWithKeys(fn (string $provider): array => [
            $provider => [
                'room' => $request->input("meeting_links.{$provider}.room"),
                'access_code' => $request->input("meeting_links.{$provider}.access_code"),
            ],
        ])->filter(fn (array $link): bool => filled($link['room']) || filled($link['access_code']))->all();
    }

    private function providerIntegrations(Request $request): \Illuminate\Support\Collection
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
                'internal_endpoint' => '/meetings',
                'required' => ['Room Prefix', 'Attendance Secret', 'Identity Field'],
                'event' => 'internal.participant_joined',
            ],
        ];
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
                    'metadata' => ['source' => $source],
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
            $id = \App\Support\OpaqueId::decode($key, Member::class);
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
