<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_sidebar_route_resolves_successfully(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        foreach (config('navigation') as $item) {
            $this->actingAs($user)
                ->get(route($item['route']))
                ->assertOk();
        }
    }

    public function test_event_flow_pages_and_attendance_records_are_functional(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $program = Program::query()->firstOrFail();
        $event = Event::query()->where('program_id', $program->id)->firstOrFail();
        $session = EventSession::query()->where('event_id', $event->id)->firstOrFail();
        $attendanceSession = AttendanceSession::query()->where('event_session_id', $session->id)->firstOrFail();
        $member = Member::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('programs.index'))
            ->assertOk()
            ->assertSee('Programs')
            ->assertSee(route('programs.store'), false);

        $this->actingAs($user)
            ->get(route('programs.events', $program))
            ->assertOk()
            ->assertSee($program->name)
            ->assertSee(route('programs.events.store', $program), false);

        $this->actingAs($user)
            ->get(route('event-sessions.index', [$program, $event]))
            ->assertOk()
            ->assertSee('Event Sessions')
            ->assertSee(route('event-sessions.store', [$program, $event]), false);

        $this->actingAs($user)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Calendar');

        $this->actingAs($user)
            ->get(route('meetings.index'))
            ->assertOk()
            ->assertSee('Meetings');

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('Attendance Sessions');

        $this->actingAs($user)
            ->get(route('meeting-integrations.index'))
            ->assertOk()
            ->assertSee('Built-in Meeting Methods')
            ->assertSee(route('meeting-integrations.update'), false)
            ->assertSee(route('meeting-integrations.test', 'zoom'), false)
            ->assertSee('Internal Attendance Callback');

        $this->actingAs($user)
            ->post(route('attendance.check-in', $attendanceSession), [
                'member_id' => $member->opaqueId(),
                'method' => 'qr',
                'provider' => 'qr',
            ])
            ->assertRedirect();

        $this->assertSame(1, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->where('member_id', $member->id)->count());

        $this->actingAs($user)
            ->get(route('meetings.rooms.show', [$session, 'zoom']))
            ->assertOk()
            ->assertSee('Built-in Zoom Room')
            ->assertSee('Attendance Record');

        $roomMember = Member::query()->where('email', $user->email)->first() ?? Member::query()->orderBy('last_name')->firstOrFail();
        $this->assertSame(1, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->where('member_id', $roomMember->id)->count());
        $this->assertDatabaseHas('attendance_verifications', [
            'attendance_session_id' => $attendanceSession->id,
            'member_id' => $roomMember->id,
            'method' => 'zoom',
            'provider' => 'zoom',
            'status' => 'success',
        ]);

        $this->withHeader('X-Meeting-Webhook-Secret', 'seeded-secret')
            ->postJson(route('meeting-attendance.webhook', 'zoom'), [
                'attendance_session' => $attendanceSession->opaqueId(),
                'email' => $member->email,
                'joined_at' => now()->toIso8601String(),
                'duration_minutes' => 47,
                'meeting_id' => '123 456 789',
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'member_matched' => true]);

        $this->assertSame(1, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->where('member_id', $member->id)->count());
        $this->assertDatabaseHas('attendance_verifications', [
            'attendance_session_id' => $attendanceSession->id,
            'member_id' => $member->id,
            'method' => 'zoom',
            'status' => 'success',
        ]);
    }

    public function test_all_program_event_session_pages_render_successfully(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        Event::query()
            ->with('program')
            ->whereNotNull('program_id')
            ->get()
            ->each(function (Event $event) use ($user): void {
                $this->actingAs($user)
                    ->get(route('event-sessions.index', [$event->program, $event]))
                    ->assertOk()
                    ->assertSee('Event Sessions');
            });
    }

    public function test_recurring_sessions_sections_assignments_and_approvals_are_functional(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $assignee = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $program = Program::query()->firstOrFail();
        $event = Event::query()->where('program_id', $program->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('event-sessions.recurrences.store', [$program, $event]), [
                'title' => 'Weekly Leadership Prayer',
                'frequency' => 'weekly',
                'interval' => 1,
                'days_of_week' => ['monday'],
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-31',
                'max_occurrences' => 3,
                'starts_at' => '07:30',
                'ends_at' => '08:30',
                'meeting_type' => 'hybrid',
                'venue' => 'Prayer Room',
                'capacity' => 40,
                'requires_approval' => '1',
            ])
            ->assertRedirect();

        $rule = EventRecurrenceRule::query()->where('title', 'Weekly Leadership Prayer')->firstOrFail();
        $this->assertSame('pending_approval', $rule->status);
        $this->assertSame(3, EventSession::query()->where('recurrence_rule_id', $rule->id)->where('status', 'draft')->count());
        $this->assertSame(3, AttendanceSession::query()->whereIn('event_session_id', $rule->sessions()->pluck('id'))->count());

        $recurrenceApproval = Approval::query()->where('approvable_type', EventRecurrenceRule::class)->where('approvable_id', $rule->id)->firstOrFail();
        $this->assertSame('pending', $recurrenceApproval->status);

        $this->actingAs($admin)
            ->post(route('workflows.approvals.approve', $recurrenceApproval))
            ->assertRedirect();

        $rule->refresh();
        $this->assertSame('active', $rule->status);
        $this->assertSame(3, EventSession::query()->where('recurrence_rule_id', $rule->id)->where('status', 'scheduled')->count());

        $this->actingAs($admin)
            ->post(route('event-sections.store', [$program, $event]), [
                'title' => 'Opening Prayer',
                'description' => 'Lead prayer before worship.',
                'section_type' => 'prayer',
                'position' => 1,
                'planned_start_time' => '09:00',
                'planned_duration_minutes' => 8,
                'scope' => 'event',
            ])
            ->assertRedirect();

        $section = ProgramSection::query()->where('event_id', $event->id)->where('title', 'Opening Prayer')->latest()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('event-section-assignments.store', [$program, $event, $section]), [
                'assignee_type' => 'user',
                'user_id' => $assignee->id,
                'role_title' => 'Prayer Leader',
                'responsibility_notes' => 'Open service with prayer and invite the worship team.',
                'requires_approval' => '1',
            ])
            ->assertRedirect();

        $assignment = ProgramSectionAssignment::query()->where('program_section_id', $section->id)->where('user_id', $assignee->id)->firstOrFail();
        $this->assertSame('pending_approval', $assignment->status);

        $assignmentApproval = Approval::query()->where('approvable_type', ProgramSectionAssignment::class)->where('approvable_id', $assignment->id)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workflows.index'))
            ->assertOk()
            ->assertSee('Workflow & Approvals')
            ->assertSee('Assign Program Section');

        $this->actingAs($admin)
            ->post(route('workflows.approvals.approve', $assignmentApproval))
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame('assigned', $assignment->status);
        $this->assertDatabaseHas('communication_deliveries', [
            'recipient_contact' => $assignee->email,
            'event_type' => 'ProgramSectionAssigned',
            'status' => 'queued',
        ]);

        $this->actingAs($assignee)
            ->post(route('program-section-assignments.accept', $assignment))
            ->assertRedirect();

        $this->assertSame('accepted', $assignment->fresh()->status);
        $this->assertGreaterThan(0, CommunicationDelivery::query()->where('event_type', 'ApprovalRequested')->count());
    }

    public function test_workflow_dashboard_create_and_import_actions_are_functional(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workflows.index'))
            ->assertOk()
            ->assertSee('Active Workflows')
            ->assertSee('New Workflow')
            ->assertSee(route('workflows.store'), false)
            ->assertSee(route('workflows.import'), false)
            ->assertDontSee('Instances (');

        $this->actingAs($admin)
            ->post(route('workflows.store'), [
                'name' => 'Event Creation Approval',
                'module' => 'events',
                'description' => 'Approval for new events and sessions.',
                'status' => 'active',
                'approval_type' => 'sequential',
                'timeout_hours' => 72,
                'steps' => [
                    ['label' => 'Request Intake', 'role' => 'Requester', 'mode' => 'auto', 'instructions' => 'Submit the proposed event details.'],
                    ['label' => 'Pastoral Review', 'role' => 'Senior Pastor', 'mode' => 'required', 'instructions' => 'Confirm ministry alignment.'],
                    ['label' => 'Finance Review', 'role' => 'Finance Officer', 'mode' => 'required', 'instructions' => 'Confirm budget readiness.'],
                    ['label' => 'Administrator Approval', 'role' => 'Church Administrator', 'mode' => 'required', 'instructions' => 'Approve final scheduling.'],
                ],
            ])
            ->assertRedirect(route('workflows.index'));

        $this->assertDatabaseHas('workflows', [
            'name' => 'Event Creation Approval',
            'module' => 'events',
            'status' => 'active',
        ]);

        $workflow = Workflow::query()->where('name', 'Event Creation Approval')->firstOrFail();
        $this->assertCount(4, $workflow->steps['steps']);
        $this->assertSame('Finance Review', $workflow->steps['steps'][2]['label']);
        $this->assertSame('Finance Officer', $workflow->steps['steps'][2]['role']);

        $this->actingAs($admin)
            ->put(route('workflows.update', $workflow), [
                'name' => 'Updated Event Approval',
                'module' => 'events',
                'description' => 'Updated approval for new events.',
                'status' => 'draft',
                'approval_type' => 'parallel',
                'timeout_hours' => 48,
                'steps' => [
                    ['label' => 'Request Intake', 'role' => 'Requester', 'mode' => 'auto', 'instructions' => 'Start the workflow.'],
                    ['label' => 'Finance Review', 'role' => 'Finance Officer', 'mode' => 'required', 'instructions' => 'Confirm budget readiness.'],
                    ['label' => 'Final Admin Review', 'role' => 'Church Administrator', 'mode' => 'required', 'instructions' => 'Make the final decision.'],
                ],
            ])
            ->assertRedirect(route('workflows.index', ['workflow' => $workflow->opaqueId()]));

        $workflow->refresh();
        $this->assertSame('Updated Event Approval', $workflow->name);
        $this->assertSame('draft', $workflow->status);
        $this->assertSame('parallel', $workflow->steps['approval_type']);
        $this->assertSame(48, $workflow->steps['timeout_hours']);
        $this->assertCount(3, $workflow->steps['steps']);
        $this->assertSame('Final Admin Review', $workflow->steps['steps'][2]['label']);
        $this->assertSame(3, $workflow->steps['steps'][2]['position']);

        $approval = Approval::query()->create([
            'church_id' => $admin->church_id,
            'workflow_id' => $workflow->id,
            'action' => 'event_creation',
            'requested_by' => $admin->id,
            'status' => 'pending',
            'payload' => ['title' => 'Custom Flow Request'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('workflows.approvals.approve', $approval))
            ->assertRedirect()
            ->assertSessionHas('status', 'Approval step approved and moved to the next approver.');

        $approval->refresh();
        $this->assertSame('pending', $approval->status);
        $this->assertSame('Church Administrator', $approval->payload['_workflow']['current_role']);

        $this->actingAs($admin)
            ->post(route('workflows.approvals.approve', $approval))
            ->assertRedirect();

        $approval->refresh();
        $this->assertSame('approved', $approval->status);
        $this->assertCount(2, $approval->payload['_workflow']['history']);

        $this->actingAs($admin)
            ->post(route('workflows.import'), [
                'name' => 'Imported Facility Booking',
                'module' => 'facilities',
                'definition' => json_encode([
                    'description' => 'Imported facility booking workflow.',
                    'approval_type' => 'parallel',
                    'timeout_hours' => 48,
                    'steps' => [
                        ['label' => 'Facility Review', 'role' => 'Facility Manager', 'mode' => 'required'],
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('workflows.index'));

        $imported = Workflow::query()->where('name', 'Imported Facility Booking')->firstOrFail();
        $this->assertSame('draft', $imported->status);
        $this->assertSame('parallel', $imported->steps['approval_type']);

        $this->actingAs($admin)
            ->delete(route('workflows.destroy', $imported))
            ->assertRedirect(route('workflows.index'));

        $this->assertSoftDeleted('workflows', [
            'id' => $imported->id,
        ]);
    }

    public function test_meeting_integrations_can_be_saved_and_tested(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)
            ->put(route('meeting-integrations.update'), [
                'providers' => [
                    'zoom' => [
                        'enabled' => '1',
                        'internal_endpoint' => '/meetings',
                        'webhook_secret' => 'webhook-123',
                        'webhook_event' => 'internal.participant_joined',
                        'room_prefix' => 'kingdomlife',
                        'identity_field' => 'email',
                        'recording_retention_days' => 45,
                    ],
                ],
            ])
            ->assertRedirect();

        $integration = MeetingIntegration::query()
            ->where('church_id', $user->church_id)
            ->where('provider', 'zoom')
            ->firstOrFail();

        $this->assertTrue($integration->enabled);
        $this->assertSame('/meetings', $integration->settings['internal_endpoint']);
        $this->assertSame('email', $integration->settings['identity_field']);
        $this->assertSame(45, $integration->settings['recording_retention_days']);
        $this->assertTrue($integration->settings['webhook_secret_configured']);

        $this->actingAs($user)
            ->post(route('meeting-integrations.test', 'zoom'))
            ->assertRedirect();

        $integration->refresh();
        $this->assertSame('healthy', $integration->settings['last_test_status']);
        $this->assertSame('Built-in meeting adapter is ready inside EcclesiaOS.', $integration->settings['last_test_message']);
        $this->assertNotNull($integration->last_tested_at);
    }

    public function test_livekit_integration_uses_real_credentials_and_generates_participant_token(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)
            ->put(route('meeting-integrations.update'), [
                'providers' => [
                    'livekit' => [
                        'enabled' => '1',
                        'server_url' => 'wss://meet.techallowed.cloud',
                        'room_prefix' => 'church',
                        'api_key' => 'APIkey1',
                        'api_secret' => 'secret1changeme2026cce',
                        'participant_token_ttl' => '2 hr',
                    ],
                ],
            ])
            ->assertRedirect();

        $integration = MeetingIntegration::query()
            ->where('church_id', $user->church_id)
            ->where('provider', 'livekit')
            ->firstOrFail();

        $this->assertTrue($integration->enabled);
        $this->assertSame('wss://meet.techallowed.cloud', $integration->settings['server_url']);
        $this->assertSame('church', $integration->settings['room_prefix']);
        $this->assertSame('APIkey1', $integration->settings['api_key']);
        $this->assertTrue($integration->settings['api_secret_configured']);
        $this->assertSame(7200, $integration->settings['participant_token_ttl_seconds']);
        $this->assertSame('2 hrs', $integration->settings['participant_token_ttl_label']);

        $this->actingAs($user)
            ->post(route('meeting-integrations.test', 'livekit'))
            ->assertRedirect();

        $integration->refresh();
        $this->assertContains($integration->settings['last_test_status'], ['healthy', 'warning']);
        $this->assertSame('church-test-room', $integration->settings['last_test_room']);
        $this->assertStringContainsString('wss://meet.techallowed.cloud', $integration->settings['last_test_message']);
        $this->assertStringContainsString('TTL 2 hrs', $integration->settings['last_test_message']);
        $this->assertArrayHasKey('last_connectivity_check', $integration->settings);

        $program = Program::query()->firstOrFail();
        $event = Event::query()->where('program_id', $program->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('event-sessions.store', [$program, $event]), [
                'title' => 'LiveKit Token Session',
                'session_date' => '2026-08-21',
                'starts_at' => '10:00',
                'ends_at' => '11:00',
                'meeting_type' => 'online',
                'venue' => null,
                'address' => null,
                'capacity' => 100,
                'status' => 'scheduled',
                'meeting_links' => [
                    'livekit' => ['enabled' => '1', 'room' => 'church-live-service', 'access_code' => 'LK-100'],
                ],
            ])
            ->assertRedirect();

        $session = EventSession::query()->where('title', 'LiveKit Token Session')->firstOrFail();
        $attendanceSession = AttendanceSession::query()->where('event_session_id', $session->id)->firstOrFail();

        $this->assertSame(0, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->count());

        $response = $this->actingAs($user)
            ->get(route('meetings.rooms.show', [$session, 'livekit']))
            ->assertOk()
            ->assertSee('LiveKit Connection')
            ->assertSee('wss://meet.techallowed.cloud')
            ->assertSee('church-live-service');

        $this->assertSame(0, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->count());

        preg_match('/eyJ[^<\\s]+/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);

        $tokenParts = explode('.', $matches[0]);
        $this->assertCount(3, $tokenParts);
        $claims = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('APIkey1', $claims['iss']);
        $this->assertSame('church-live-service', $claims['video']['room']);
        $this->assertTrue($claims['video']['roomJoin']);
        $this->assertSame(7200, $claims['exp'] - $claims['nbf']);

        $this->actingAs($user)
            ->postJson(route('meetings.rooms.attendance.store', [$session, 'livekit']), [
                'connected' => true,
                'room' => 'church-live-service',
                'remote_participants' => 0,
            ])
            ->assertOk()
            ->assertJson([
                'marked' => true,
                'participant_count' => 1,
            ]);

        $this->assertSame(1, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->where('final_method', 'livekit')->count());
        $liveKitRecord = AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->where('final_method', 'livekit')->firstOrFail();
        $this->assertSame('online', $liveKitRecord->metadata['online_status']);

        $this->actingAs($user)
            ->postJson(route('meetings.rooms.checkout.store', [$session, 'livekit']), [
                'room' => 'church-live-service',
            ])
            ->assertOk()
            ->assertJson([
                'checked_out' => true,
                'participant_count' => 0,
            ]);

        $liveKitRecord->refresh();
        $this->assertSame('checked_out', $liveKitRecord->metadata['online_status']);
        $this->assertNotEmpty($liveKitRecord->metadata['checked_out_at']);
    }

    public function test_only_enabled_and_selected_builtin_meeting_methods_are_joinable(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $program = Program::query()->firstOrFail();
        $event = Event::query()->where('program_id', $program->id)->firstOrFail();

        MeetingIntegration::query()
            ->where('church_id', $user->church_id)
            ->where('provider', 'jitsi')
            ->update(['enabled' => false]);

        $this->actingAs($user)
            ->get(route('event-sessions.index', [$program, $event]))
            ->assertOk()
            ->assertSee('Zoom')
            ->assertDontSee('jitsi room ID');

        $this->actingAs($user)
            ->post(route('event-sessions.store', [$program, $event]), [
                'title' => 'Selected Provider Session',
                'session_date' => '2026-08-15',
                'starts_at' => '10:00',
                'ends_at' => '11:00',
                'meeting_type' => 'online',
                'venue' => null,
                'address' => null,
                'capacity' => 100,
                'status' => 'scheduled',
                'meeting_links' => [
                    'zoom' => ['enabled' => '1', 'room' => 'selected-zoom-room', 'access_code' => 'Z-100'],
                    'google_meet' => ['room' => 'not-selected-room'],
                    'jitsi' => ['enabled' => '1', 'room' => 'disabled-provider-room'],
                ],
            ])
            ->assertRedirect();

        $session = EventSession::query()->where('title', 'Selected Provider Session')->firstOrFail();
        $attendanceSession = AttendanceSession::query()->where('event_session_id', $session->id)->firstOrFail();

        $this->assertSame(['zoom'], array_keys($session->meeting_links));
        $this->assertSame(['zoom'], $attendanceSession->methods);

        $this->actingAs($user)
            ->get(route('attendance.methods', $attendanceSession))
            ->assertOk()
            ->assertSee('Zoom')
            ->assertDontSee('Google Meet')
            ->assertDontSee('Jitsi Meet');

        $this->actingAs($user)
            ->get(route('meetings.rooms.show', [$session, 'google_meet']))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('meetings.rooms.show', [$session, 'zoom']))
            ->assertOk()
            ->assertSee('Built-in Zoom Room');
    }
}
