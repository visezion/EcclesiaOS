<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\CommunicationDelivery;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Models\EventSession;
use App\Models\LeadershipReport;
use App\Models\MeetingIntegration;
use App\Models\MeetingPoll;
use App\Models\MeetingQnaItem;
use App\Models\MeetingScene;
use App\Models\MeetingStudioState;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Permission;
use App\Models\Program;
use App\Models\ProgramSection;
use App\Models\ProgramSectionAssignment;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee(route('programs.store'), false)
            ->assertSee(route('programs.update', $program), false)
            ->assertSee(route('programs.destroy', $program), false);

        $editableProgram = Program::query()->create([
            'church_id' => $program->church_id,
            'campus_id' => $program->campus_id,
            'name' => 'Editable Program',
            'description' => 'Program to edit and delete.',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
            'status' => 'upcoming',
        ]);

        $this->actingAs($user)
            ->put(route('programs.update', $editableProgram), [
                'name' => 'Editable Program Updated',
                'description' => 'Updated program description.',
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDays(2)->toDateString(),
                'status' => 'ongoing',
            ])
            ->assertRedirect(route('programs.index'));

        $editableProgram->refresh();
        $this->assertSame('Editable Program Updated', $editableProgram->name);
        $this->assertSame('ongoing', $editableProgram->status);

        $this->actingAs($user)
            ->delete(route('programs.destroy', $editableProgram))
            ->assertRedirect(route('programs.index'));

        $this->assertSoftDeleted('programs', ['id' => $editableProgram->id]);

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
            ->assertSee('Attendance Sessions')
            ->assertSee(route('attendance.destroy', $attendanceSession), false);

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

        $this->withHeaders([
            'X-Meeting-Webhook-Secret' => 'seeded-secret',
            'X-Meeting-Webhook-Timestamp' => (string) now()->timestamp,
        ])
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

        $memberRecord = AttendanceRecord::query()
            ->where('attendance_session_id', $attendanceSession->id)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('attendance.records.show', [$attendanceSession, $member->opaqueId()]))
            ->assertOk()
            ->assertSee(route('attendance.records.update', $memberRecord), false)
            ->assertSee(route('attendance.records.destroy', $memberRecord), false);

        $this->actingAs($user)
            ->put(route('attendance.records.update', $memberRecord), [
                'status' => 'late',
                'final_method' => 'manual',
                'service_date' => now()->toDateString(),
                'checked_in_at' => now()->format('Y-m-d H:i:s'),
                'admin_note' => 'Corrected by administrator.',
            ])
            ->assertRedirect();

        $memberRecord->refresh();
        $this->assertSame('late', $memberRecord->status);
        $this->assertSame('manual', $memberRecord->final_method);
        $this->assertSame('Corrected by administrator.', $memberRecord->metadata['admin_note']);

        $this->actingAs($user)
            ->delete(route('attendance.records.destroy', $memberRecord))
            ->assertRedirect(route('event-sessions.attendance', $attendanceSession->eventSession));

        $this->assertDatabaseMissing('attendance_records', ['id' => $memberRecord->id]);

        $this->actingAs($user)
            ->delete(route('attendance.destroy', $attendanceSession))
            ->assertRedirect(route('attendance.index'));

        $this->assertSoftDeleted('attendance_sessions', ['id' => $attendanceSession->id]);
        $this->assertSame(0, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->count());
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
            'status' => 'delivered',
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
            ->assertSee('<option value="Ministry Leader">Ministry Leader</option>', false)
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

    public function test_leadership_reports_dashboard_and_actions_are_functional(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('leadership-reports.index'))
            ->assertOk()
            ->assertSee('Pastor & Leadership Reports')
            ->assertSee('Reports Overview')
            ->assertSee(route('leadership-reports.store'), false)
            ->assertSee(route('leadership-reports.summary'), false)
            ->assertSee(route('leadership-reports.export'), false)
            ->assertSee('Use Recorded Attendance')
            ->assertSee('Reports Trend');

        $event = Event::query()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'title' => 'Leadership Attendance Source Service',
            'starts_at' => now()->startOfWeek()->setTime(10, 0),
            'ends_at' => now()->startOfWeek()->setTime(11, 30),
            'venue' => 'Main Hall',
            'category' => 'Service',
            'status' => 'completed',
        ]);
        $eventSession = EventSession::query()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'event_id' => $event->id,
            'title' => 'Leadership Attendance Source Service',
            'session_date' => now()->startOfWeek()->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '11:30',
            'timezone' => config('app.timezone'),
            'meeting_type' => 'physical',
            'venue' => 'Main Hall',
            'capacity' => 120,
            'status' => 'completed',
        ]);
        $attendanceSession = AttendanceSession::query()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'event_session_id' => $eventSession->id,
            'title' => 'Leadership Attendance Source Service',
            'opens_at' => now()->startOfWeek()->setTime(9, 30),
            'closes_at' => now()->startOfWeek()->setTime(12, 0),
            'methods' => ['manual'],
            'expected_attendance' => 10,
            'status' => 'closed',
        ]);
        Member::factory()
            ->count(8)
            ->create(['church_id' => $admin->church_id, 'campus_id' => $admin->campus_id])
            ->each(function (Member $member) use ($admin, $event, $attendanceSession): void {
                AttendanceRecord::query()->create([
                    'church_id' => $admin->church_id,
                    'campus_id' => $admin->campus_id,
                    'event_id' => $event->id,
                    'attendance_session_id' => $attendanceSession->id,
                    'member_id' => $member->id,
                    'service_date' => now()->startOfWeek()->toDateString(),
                    'status' => 'present',
                    'final_method' => 'manual',
                    'checked_in_at' => now()->startOfWeek()->setTime(10, 5),
                ]);
            });

        $this->actingAs($admin)
            ->post(route('leadership-reports.store'), [
                'title' => 'Senior Pastor Weekly Leadership Report',
                'report_type' => 'weekly',
                'campus_id' => $admin->campus_id,
                'ministry_id' => null,
                'assigned_to' => $admin->id,
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->endOfWeek()->toDateString(),
                'priority' => 'high',
                'summary' => 'Leadership summary for the current week with attendance, discipleship, care, and volunteer coverage metrics.',
                'attendance_session_ids' => [$attendanceSession->id],
                'attendance_score' => 91,
                'discipleship_score' => 87,
                'care_followups' => 11,
                'volunteer_coverage' => 83,
                'action_items' => "Follow up with campus pastors\nPrepare ministry leader notes",
                'submit' => '1',
            ])
            ->assertRedirect();

        $report = LeadershipReport::query()->where('title', 'Senior Pastor Weekly Leadership Report')->firstOrFail();
        $this->assertSame('submitted', $report->status);
        $this->assertSame(80, $report->metrics['attendance_score']);
        $this->assertSame('recorded', $report->metrics['attendance_source']);
        $this->assertSame(8, $report->metrics['attendance_total']);
        $this->assertSame(10, $report->metrics['attendance_expected']);
        $this->assertCount(2, $report->action_items);

        $this->actingAs($admin)
            ->get(route('leadership-reports.show', $report))
            ->assertOk()
            ->assertSee('Report Detail')
            ->assertSee('Recorded Attendance Source')
            ->assertSee('Leadership summary for the current week')
            ->assertSee(route('leadership-reports.review', $report), false);

        $this->actingAs($admin)
            ->put(route('leadership-reports.review', $report), [
                'decision' => 'approved',
                'review_notes' => 'Approved for the weekly senior leadership packet.',
            ])
            ->assertRedirect(route('leadership-reports.index', ['report' => $report->opaqueId()]));

        $report->refresh();
        $this->assertSame('approved', $report->status);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);

        $this->actingAs($admin)
            ->post(route('leadership-reports.summary'))
            ->assertRedirect()
            ->assertSessionHas('status');

        foreach (['my', 'to-me', 'all', 'analytics', 'templates', 'settings'] as $tab) {
            $this->actingAs($admin)
                ->get(route('leadership-reports.index', ['tab' => $tab]))
                ->assertOk();
        }

        $this->actingAs($admin)
            ->get(route('leadership-reports.index', ['tab' => 'settings']))
            ->assertOk()
            ->assertSee('Search reviewer by name, title, or email')
            ->assertSee('Escalation Window');

        $this->actingAs($admin)
            ->post(route('leadership-reports.reminders'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $churchSettingsBefore = $admin->church()->firstOrFail()->settings ?? [];

        $this->actingAs($admin)
            ->put(route('leadership-reports.settings.update'), [
                'default_reviewer_id' => $admin->id,
                'weekly_due_day' => 'tuesday',
                'auto_reminders' => '1',
                'require_action_items' => '1',
                'escalation_hours' => 48,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Leadership report settings saved.');

        $admin->refresh();
        $this->assertSame($admin->id, data_get($admin->account_settings, 'leadership_reports.default_reviewer_id'));
        $this->assertSame('tuesday', data_get($admin->account_settings, 'leadership_reports.weekly_due_day'));
        $this->assertSame(48, data_get($admin->account_settings, 'leadership_reports.escalation_hours'));
        $this->assertTrue(data_get($admin->account_settings, 'leadership_reports.auto_reminders'));
        $this->assertTrue(data_get($admin->account_settings, 'leadership_reports.require_action_items'));
        $this->assertSame($churchSettingsBefore, $admin->church()->firstOrFail()->refresh()->settings ?? []);
        $this->assertNull(data_get(User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail()->account_settings, 'leadership_reports.weekly_due_day'));

        $this->actingAs($admin)
            ->post(route('leadership-reports.store'), [
                'title' => 'Template Created Leadership Report',
                'report_type' => 'ministry',
                'assigned_to' => $admin->id,
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->endOfWeek()->toDateString(),
                'priority' => 'normal',
                'summary' => 'Template generated report with detailed leadership inputs.',
                'attendance_score' => 86,
                'discipleship_score' => 91,
                'care_followups' => 6,
                'volunteer_coverage' => 78,
                'action_items' => "Update ministry leader roster\nEscalate volunteer coverage gaps",
                'submit' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leadership_reports', [
            'title' => 'Template Created Leadership Report',
            'status' => 'draft',
        ]);

        $draft = LeadershipReport::query()->where('title', 'Template Created Leadership Report')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('leadership-reports.show', $draft))
            ->assertOk()
            ->assertSee('Edit Draft')
            ->assertSee(route('leadership-reports.update', $draft), false)
            ->assertSee(route('leadership-reports.destroy', $draft), false);

        $deletableDraft = LeadershipReport::query()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'submitted_by' => $admin->id,
            'assigned_to' => $admin->id,
            'title' => 'Deletable Draft Leadership Report',
            'report_type' => 'weekly',
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
            'status' => 'draft',
            'priority' => 'normal',
            'summary' => 'Draft report that should be removable by its owner only.',
            'metrics' => ['attendance_score' => 80],
            'action_items' => ['Confirm draft delete flow'],
            'due_at' => now()->addDays(3),
        ]);
        $otherAdministrator = User::query()
            ->where('email', 'sarah.johnson@klgc.org')
            ->firstOrFail();

        $this->actingAs($otherAdministrator)
            ->delete(route('leadership-reports.destroy', $deletableDraft))
            ->assertForbidden();

        $this->assertNotSoftDeleted('leadership_reports', ['id' => $deletableDraft->id]);

        $this->actingAs($admin)
            ->get(route('leadership-reports.index', ['tab' => 'my', 'report' => $deletableDraft->opaqueId()]))
            ->assertOk()
            ->assertSee('Delete Draft')
            ->assertSee(route('leadership-reports.destroy', $deletableDraft), false);

        $this->actingAs($admin)
            ->delete(route('leadership-reports.destroy', $deletableDraft))
            ->assertRedirect(route('leadership-reports.index', ['tab' => 'my']))
            ->assertSessionHas('status', 'Draft report deleted.');

        $this->assertSoftDeleted('leadership_reports', ['id' => $deletableDraft->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'leadership_report_deleted']);

        $this->actingAs($admin)
            ->put(route('leadership-reports.update', $draft), [
                'title' => 'Edited Leadership Report',
                'report_type' => 'weekly',
                'campus_id' => $admin->campus_id,
                'ministry_id' => null,
                'assigned_to' => $admin->id,
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->endOfWeek()->toDateString(),
                'priority' => 'high',
                'summary' => 'Edited draft summary ready for leadership review.',
                'attendance_score' => 92,
                'discipleship_score' => 89,
                'care_followups' => 10,
                'volunteer_coverage' => 84,
                'service_notes' => 'Updated service notes.',
                'issues' => 'Updated support needs.',
                'plans' => 'Updated plans.',
                'supporting_links' => 'https://example.test/report-pack',
                'action_items' => "Confirm reviewer\nSend final packet",
                'submit' => '1',
            ])
            ->assertRedirect(route('leadership-reports.show', $draft));

        $draft->refresh();
        $this->assertSame('Edited Leadership Report', $draft->title);
        $this->assertSame('submitted', $draft->status);
        $this->assertSame(92, $draft->metrics['attendance_score']);
        $this->assertSame(['https://example.test/report-pack'], $draft->metrics['supporting_links']);
        $this->assertSame(['Confirm reviewer', 'Send final packet'], $draft->action_items);
        $this->assertNotNull($draft->submitted_at);

        $this->actingAs($admin)
            ->delete(route('leadership-reports.destroy', $draft))
            ->assertForbidden();

        $this->assertNotSoftDeleted('leadership_reports', ['id' => $draft->id]);

        $this->actingAs($admin)
            ->get(route('leadership-reports.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_leadership_report_escalation_settings_require_review_permission(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $viewPermission = Permission::query()->where('name', 'view leadership reports')->firstOrFail();
        $viewOnlyRole = Role::query()->create([
            'name' => 'Leadership Report View Only',
            'slug' => 'leadership-report-view-only',
            'description' => 'Can create and view leadership reports without review escalation control.',
        ]);
        $viewOnlyRole->permissions()->attach($viewPermission);

        $viewOnlyUser = User::factory()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'name' => 'Leadership View Only User',
            'email' => 'leadership.view.only@example.test',
            'account_settings' => [
                'leadership_reports' => [
                    'default_reviewer_id' => null,
                    'weekly_due_day' => 'friday',
                    'auto_reminders' => true,
                    'require_action_items' => true,
                    'escalation_hours' => 96,
                ],
            ],
        ]);
        $viewOnlyUser->roles()->attach($viewOnlyRole);

        $submittedReport = LeadershipReport::query()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'submitted_by' => $admin->id,
            'assigned_to' => $viewOnlyUser->id,
            'title' => 'Submitted Report For View Only User',
            'report_type' => 'weekly',
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
            'status' => 'submitted',
            'priority' => 'normal',
            'summary' => 'Submitted report visible to a user who cannot review.',
            'metrics' => ['attendance_score' => 80],
            'action_items' => ['Read only'],
            'submitted_at' => now(),
            'due_at' => now()->addDays(3),
        ]);

        $this->actingAs($viewOnlyUser)
            ->get(route('leadership-reports.index', ['tab' => 'settings']))
            ->assertOk()
            ->assertSee('Search reviewer by name, title, or email')
            ->assertDontSee('Escalation Window');

        $this->actingAs($viewOnlyUser)
            ->put(route('leadership-reports.settings.update'), [
                'default_reviewer_id' => $admin->id,
                'weekly_due_day' => 'monday',
                'auto_reminders' => '1',
                'require_action_items' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Leadership report settings saved.');

        $viewOnlyUser->refresh();
        $this->assertSame('monday', data_get($viewOnlyUser->account_settings, 'leadership_reports.weekly_due_day'));
        $this->assertSame(96, data_get($viewOnlyUser->account_settings, 'leadership_reports.escalation_hours'));

        $this->actingAs($viewOnlyUser)
            ->put(route('leadership-reports.review', $submittedReport), [
                'decision' => 'approved',
                'review_notes' => 'Should not be allowed.',
            ])
            ->assertForbidden();
    }

    public function test_campus_leader_reports_are_limited_to_assigned_campus_ministries(): void
    {
        $this->seed();
        $leader = User::query()->where('email', 'david.wilson@klgc.org')->firstOrFail();
        $ownCampus = $leader->campus()->firstOrFail();
        $otherCampus = Campus::query()
            ->where('church_id', $leader->church_id)
            ->whereKeyNot($ownCampus->id)
            ->firstOrFail();
        $otherMinistry = Ministry::query()->create([
            'church_id' => $leader->church_id,
            'campus_id' => $otherCampus->id,
            'name' => 'Other Campus Outreach Report Team',
            'description' => 'Should not be visible to this campus leader.',
            'status' => 'active',
        ]);

        $this->actingAs($leader)
            ->get(route('leadership-reports.index'))
            ->assertOk()
            ->assertSee($ownCampus->name)
            ->assertDontSee('All Leadership Reports', false)
            ->assertDontSee('Other Campus Outreach Report Team');

        $this->actingAs($leader)
            ->get(route('leadership-reports.index', ['tab' => 'all']))
            ->assertOk()
            ->assertDontSee('All Leadership Reports', false)
            ->assertSee('Recent Reports', false);

        $this->actingAs($leader)
            ->post(route('leadership-reports.store'), [
                'title' => 'Campus Scoped Leadership Report',
                'report_type' => 'campus',
                'campus_id' => $otherCampus->id,
                'ministry_id' => null,
                'assigned_to' => $leader->id,
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->endOfWeek()->toDateString(),
                'priority' => 'normal',
                'summary' => 'Campus leader report should be saved against the assigned campus only.',
                'submit' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leadership_reports', [
            'title' => 'Campus Scoped Leadership Report',
            'campus_id' => $ownCampus->id,
        ]);
        $this->assertDatabaseMissing('leadership_reports', [
            'title' => 'Campus Scoped Leadership Report',
            'campus_id' => $otherCampus->id,
        ]);

        $this->actingAs($leader)
            ->post(route('leadership-reports.store'), [
                'title' => 'Blocked Cross Campus Ministry Report',
                'report_type' => 'ministry',
                'campus_id' => $ownCampus->id,
                'ministry_id' => $otherMinistry->id,
                'assigned_to' => $leader->id,
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->endOfWeek()->toDateString(),
                'priority' => 'normal',
                'summary' => 'This ministry belongs to a different campus and must be blocked.',
                'submit' => '1',
            ])
            ->assertForbidden();
    }

    public function test_church_administrator_can_create_leadership_reports_for_any_campus(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $targetCampus = Campus::query()
            ->where('church_id', $administrator->church_id)
            ->whereKeyNot($administrator->campus_id)
            ->firstOrFail();
        $targetMinistry = Ministry::query()->create([
            'church_id' => $administrator->church_id,
            'campus_id' => $targetCampus->id,
            'name' => 'Administrator Cross Campus Report Ministry',
            'description' => 'Confirms full campus leadership report scope.',
            'status' => 'active',
        ]);

        $this->actingAs($administrator)
            ->get(route('leadership-reports.index'))
            ->assertOk()
            ->assertSee('All Leadership Reports', false)
            ->assertSee($targetCampus->name, false)
            ->assertSee($targetMinistry->name, false);

        $this->actingAs($administrator)
            ->post(route('leadership-reports.store'), [
                'title' => 'Administrator Cross Campus Leadership Report',
                'report_type' => 'campus',
                'campus_id' => $targetCampus->id,
                'ministry_id' => $targetMinistry->id,
                'assigned_to' => $administrator->id,
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->endOfWeek()->toDateString(),
                'priority' => 'normal',
                'summary' => 'Administrator can submit a leadership report for any campus when granted full campus privileges.',
                'attendance_score' => 75,
                'discipleship_score' => 80,
                'care_followups' => 3,
                'volunteer_coverage' => 70,
                'submit' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leadership_reports', [
            'title' => 'Administrator Cross Campus Leadership Report',
            'campus_id' => $targetCampus->id,
            'ministry_id' => $targetMinistry->id,
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

        $shortRoomUrl = route('meetings.rooms.short', [$this->shortRoomCode($session), 'livekit']);

        $this->assertStringContainsString(str_replace('/', '\\/', $shortRoomUrl), $response->getContent());

        $this->actingAs($user)
            ->get($shortRoomUrl)
            ->assertRedirect(route('meetings.rooms.show', [$session, 'livekit']));

        $studioUrl = route('meetings.rooms.studio', [$session, 'livekit']);
        $this->actingAs($user)
            ->get($studioUrl)
            ->assertOk()
            ->assertSee('Live')
            ->assertDontSee('LiveNow')
            ->assertSee('Programs &amp; Attendance', false)
            ->assertSee("sceneTab = 'sources'", false)
            ->assertSee('studioLiveVideo', false)
            ->assertSee('Main Screen')
            ->assertSee('Main Cam')
            ->assertSee('Input Source')
            ->assertSee('Save Source')
            ->assertSee('Scene Screen')
            ->assertSee('Participant Screen')
            ->assertSee('Countdown minutes')
            ->assertSee('Verse text to show on screen')
            ->assertSee('Add Screen')
            ->assertSee('Add Media Scene')
            ->assertSee('Save Bottom Title')
            ->assertSee('Upload Background Image')
            ->assertSee('Create background')
            ->assertSee('Current Poll');

        $eventOnlyRole = Role::query()->create([
            'name' => 'Event Manager Only',
            'slug' => 'event-manager-only',
            'description' => 'Event manager without studio access',
        ]);
        $eventOnlyRole->permissions()->attach(Permission::query()->where('name', 'manage events')->firstOrFail());
        $eventOnlyUser = User::factory()->create([
            'church_id' => $session->church_id,
            'campus_id' => $session->campus_id,
            'email' => 'event.only@kingdomhub.test',
        ]);
        $eventOnlyUser->roles()->attach($eventOnlyRole);

        $studioRole = Role::query()->create([
            'name' => 'Studio Operator',
            'slug' => 'studio-operator',
            'description' => 'Studio backroom operator',
        ]);
        $studioRole->permissions()->attach(Permission::query()->where('name', 'manage studio')->firstOrFail());
        $studioUser = User::factory()->create([
            'church_id' => $session->church_id,
            'campus_id' => $session->campus_id,
            'email' => 'studio.operator@kingdomhub.test',
        ]);
        $studioUser->roles()->attach($studioRole);

        $this->actingAs($eventOnlyUser)
            ->get(route('meetings.rooms.show', [$session, 'livekit']))
            ->assertOk()
            ->assertDontSee($studioUrl, false)
            ->assertDontSee('Upload title background');

        $this->actingAs($eventOnlyUser)
            ->get($studioUrl)
            ->assertForbidden();

        $this->actingAs($eventOnlyUser)
            ->put(route('meetings.rooms.studio.state.update', [$session, 'livekit']), [
                'speaker_name' => 'Unauthorized Operator',
            ])
            ->assertForbidden();

        $this->actingAs($studioUser)
            ->get($studioUrl)
            ->assertOk()
            ->assertSee('Programs &amp; Attendance', false);

        $scene = MeetingScene::query()->where('event_session_id', $session->id)->where('title', 'Scripture Slide')->firstOrFail();

        $this->actingAs($user)
            ->put(route('meetings.rooms.studio.scenes.source', [$session, 'livekit', $scene]), [
                'source_identity' => 'victor.adams@members.klgc.org',
                'source_name' => 'Pastor Victor Camera',
                'source_kind' => 'camera',
            ])
            ->assertRedirect();

        $this->assertSame('victor.adams@members.klgc.org', $scene->fresh()->settings['source_identity']);
        $this->assertSame('camera', $scene->fresh()->settings['source_kind']);

        $cameraScene = MeetingScene::query()->where('event_session_id', $session->id)->where('title', 'Main Camera')->firstOrFail();
        $screenScene = MeetingScene::query()->where('event_session_id', $session->id)->where('title', 'Worship Band')->firstOrFail();

        $this->actingAs($user)
            ->put(route('meetings.rooms.studio.scenes.source', [$session, 'livekit', $cameraScene]), [
                'source_identity' => 'camera.user@members.klgc.org',
                'source_name' => 'Camera User',
                'source_kind' => 'camera',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('meetings.rooms.studio.scenes.source', [$session, 'livekit', $screenScene]), [
                'source_identity' => 'screen.user@members.klgc.org',
                'source_name' => 'Screen User screen',
                'source_kind' => 'screen',
            ])
            ->assertRedirect();

        $this->assertSame('camera.user@members.klgc.org', $cameraScene->fresh()->settings['source_identity']);
        $this->assertSame('camera', $cameraScene->fresh()->settings['source_kind']);
        $this->assertSame('screen.user@members.klgc.org', $screenScene->fresh()->settings['source_identity']);
        $this->assertSame('screen', $screenScene->fresh()->settings['source_kind']);

        $this->actingAs($user)
            ->post(route('meetings.rooms.studio.scenes.live', [$session, 'livekit', $scene]))
            ->assertRedirect();

        $this->assertSame($scene->id, MeetingStudioState::query()->where('event_session_id', $session->id)->where('provider', 'livekit')->firstOrFail()->live_scene_id);

        $this->actingAs($user)
            ->put(route('meetings.rooms.studio.state.update', [$session, 'livekit']), [
                'speaker_name' => 'Pastor Victor',
                'speaker_role' => 'Senior Pastor',
                'service_label' => 'Sunday Worship',
                'scripture_reference' => 'Matthew 18:20',
                'scripture_text' => 'For where two or three are gathered in my name, there am I among them.',
                'ticker_text' => 'Welcome to worship',
                'chat_visible' => '1',
                'qna_enabled' => '1',
                'poll_visible' => '1',
                'stream_status' => 'live',
                'audio_mixer' => ['pastor_mic' => '-1', 'audience' => '-8'],
                'quick_actions' => ['transition' => 'cut', 'transition_duration' => '0.5s'],
                'destination_name' => 'Church Website',
                'destination_status' => 'ready',
            ])
            ->assertRedirect();

        Storage::fake('public');
        $this->actingAs($user)
            ->put(route('meetings.rooms.studio.state.update', [$session, 'livekit']), [
                'lower_third_background' => UploadedFile::fake()->image('lower-third.png', 1200, 260),
            ])
            ->assertRedirect();

        $state = MeetingStudioState::query()->where('event_session_id', $session->id)->where('provider', 'livekit')->firstOrFail();
        $this->assertSame(-1, $state->audio_mixer['pastor_mic']);
        $this->assertSame('cut', $state->quick_actions['transition']);
        $this->assertContains('Church Website', collect($state->destinations)->pluck('name')->all());
        $this->assertStringContainsString('/storage/studio/lower-thirds/', $state->lower_third['background_url']);

        $this->actingAs($user)
            ->put(route('meetings.rooms.studio.state.update', [$session, 'livekit']), [
                'lower_third_background_preset' => 'gold-sanctuary',
            ])
            ->assertRedirect();

        $state = $state->fresh();
        $this->assertNull($state->lower_third['background_url']);
        $this->assertSame('Gold Sanctuary', $state->lower_third['background_label']);
        $this->assertStringContainsString('rgba(245,158,11', $state->lower_third['background_style']);

        $this->actingAs($user)
            ->post(route('meetings.rooms.studio.scenes.store', [$session, 'livekit']), [
                'title' => 'Guest Testimony Screen',
                'scene_type' => 'camera',
                'description' => 'Guest camera testimony',
            ])
            ->assertRedirect();

        $extraScene = MeetingScene::query()->where('event_session_id', $session->id)->where('title', 'Guest Testimony Screen')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('meetings.rooms.studio.scenes.destroy', [$session, 'livekit', $extraScene]))
            ->assertRedirect();

        $this->assertSoftDeleted($extraScene);

        $this->actingAs($user)
            ->post(route('meetings.rooms.studio.scenes.store', [$session, 'livekit']), [
                'title' => 'Guest Screen Share',
                'scene_type' => 'screen',
                'description' => 'Guest shares presentation',
                'source_identity' => 'guest-presenter',
                'source_name' => 'Guest Presenter screen',
            ])
            ->assertRedirect();

        $screenShareScene = MeetingScene::query()->where('event_session_id', $session->id)->where('title', 'Guest Screen Share')->firstOrFail();
        $this->assertSame('screen', $screenShareScene->scene_type);
        $this->assertSame('screen', $screenShareScene->settings['source_kind']);
        $this->assertSame('guest-presenter', $screenShareScene->settings['source_identity']);

        $this->actingAs($user)
            ->post(route('meetings.rooms.studio.scenes.store', [$session, 'livekit']), [
                'title' => 'Offering Countdown',
                'scene_type' => 'countdown',
                'description' => 'Offering timer',
                'countdown_minutes' => 7,
            ])
            ->assertRedirect();

        $countdownScene = MeetingScene::query()->where('event_session_id', $session->id)->where('title', 'Offering Countdown')->firstOrFail();
        $this->assertSame(7, $countdownScene->settings['minutes']);

        $this->actingAs($user)
            ->post(route('meetings.rooms.studio.scenes.store', [$session, 'livekit']), [
                'title' => 'Memory Verse',
                'scene_type' => 'scripture',
                'scripture_reference' => 'John 3:16',
                'scripture_text' => 'For God so loved the world.',
            ])
            ->assertRedirect();

        $scriptureScene = MeetingScene::query()->where('event_session_id', $session->id)->where('title', 'Memory Verse')->firstOrFail();
        $this->assertSame('John 3:16', $scriptureScene->settings['reference']);
        $this->assertSame('For God so loved the world.', $scriptureScene->settings['text']);

        $this->actingAs($user)
            ->post(route('meetings.rooms.studio.polls.store', [$session, 'livekit']), [
                'question' => 'Which area should this meeting focus on next?',
                'options' => ['Prayer', 'Outreach', 'Leadership', 'Operations'],
            ])
            ->assertRedirect();

        $poll = MeetingPoll::query()->where('event_session_id', $session->id)->latest()->firstOrFail();
        $this->assertSame(['Prayer', 'Outreach', 'Leadership', 'Operations'], $poll->options()->pluck('label')->all());

        $this->app['auth']->guard()->logout();
        $this->flushSession();

        $this->get($shortRoomUrl)
            ->assertOk()
            ->assertSee('Join as guest')
            ->assertSee('Log in instead')
            ->assertSee('Your name');

        $shortRoomCode = $this->shortRoomCode($session);

        $this->postJson(route('meetings.rooms.short.qna.store', [$shortRoomCode, 'livekit']), [
            'body' => 'Unjoined guest question',
        ])->assertForbidden();

        $unjoinedOption = $poll->options()->firstOrFail();
        $this->postJson(route('meetings.rooms.short.polls.vote', [$shortRoomCode, 'livekit', $poll]), [
            'option' => $unjoinedOption->id,
        ])->assertForbidden();

        $this->post(route('meetings.rooms.short.join', [$shortRoomCode, 'livekit']), [
            'guest_name' => 'Guest Visitor',
        ])
            ->assertRedirect($shortRoomUrl);

        $guestResponse = $this->get($shortRoomUrl)
            ->assertOk()
            ->assertSee('Guest Visitor')
            ->assertSee('Guest access')
            ->assertSee('toggleLiveKit()', false)
            ->assertSee('toggleMute()', false)
            ->assertSee('toggleCamera()', false)
            ->assertSee('toggleScreenShare()', false);

        $this->assertStringContainsString('\u0022mark_attendance_url\u0022:null', $guestResponse->getContent());

        preg_match('/eyJ[^<\\s]+/', $guestResponse->getContent(), $guestMatches);
        $this->assertNotEmpty($guestMatches);

        $guestTokenParts = explode('.', $guestMatches[0]);
        $this->assertCount(3, $guestTokenParts);
        $guestClaims = json_decode(base64_decode(strtr($guestTokenParts[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertStringStartsWith('guest-'.$session->getKey().'-livekit-', $guestClaims['sub']);
        $this->assertSame('Guest Visitor', $guestClaims['name']);
        $this->assertTrue($guestClaims['video']['roomJoin']);
        $this->assertTrue($guestClaims['video']['canPublish']);
        $this->assertTrue($guestClaims['video']['canSubscribe']);
        $this->assertTrue($guestClaims['video']['canPublishData']);

        $this->postJson(route('meetings.rooms.short.qna.store', [$shortRoomCode, 'livekit']), [
            'body' => 'Can you pray for the outreach team?',
        ])->assertOk();
        $this->assertTrue(MeetingQnaItem::query()->where('event_session_id', $session->id)->where('body', 'Can you pray for the outreach team?')->exists());

        $option = $poll->options()->where('label', 'Prayer')->firstOrFail();
        $this->postJson(route('meetings.rooms.short.polls.vote', [$shortRoomCode, 'livekit', $poll]), [
            'option' => $option->id,
        ])->assertOk();
        $this->assertSame(1, $option->fresh()->votes_count);

        $this->getJson(route('meetings.rooms.short.state', [$shortRoomCode, 'livekit']))
            ->assertOk()
            ->assertJsonPath('live_scene.title', 'Scripture Slide')
            ->assertJsonPath('live_scene.settings.source_identity', 'victor.adams@members.klgc.org')
            ->assertJsonPath('lower_third.speaker_name', 'Pastor Victor')
            ->assertJsonPath('poll.question', 'Which area should this meeting focus on next?')
            ->assertJsonPath('qna.0.body', 'Can you pray for the outreach team?');

        $this->assertSame(0, AttendanceRecord::query()->where('attendance_session_id', $attendanceSession->id)->count());

        preg_match('/eyJ[^<\\s]+/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);

        $tokenParts = explode('.', $matches[0]);
        $this->assertCount(3, $tokenParts);
        $claims = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('APIkey1', $claims['iss']);
        $this->assertSame('church-live-service', $claims['video']['room']);
        $this->assertTrue($claims['video']['roomJoin']);
        $this->assertTrue($claims['video']['canPublish']);
        $this->assertTrue($claims['video']['canSubscribe']);
        $this->assertTrue($claims['video']['canPublishData']);
        $this->assertSame(7200, $claims['exp'] - $claims['nbf']);

        $this->actingAs($user)
            ->postJson(route('meetings.rooms.attendance.store', [$session, 'livekit']), [
                'connected' => true,
                'room' => 'church-live-service',
                'identity' => $claims['sub'],
                'participant_name' => 'Victor Adams',
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
        $this->assertSame($claims['sub'], $liveKitRecord->metadata['livekit_identity']);
        $this->assertSame('Victor Adams', $liveKitRecord->metadata['participant_name']);

        $this->actingAs($user)
            ->get($studioUrl)
            ->assertOk()
            ->assertSee($claims['sub'])
            ->assertSee('1 active')
            ->assertSee('Put screen share on Main Screen', false);

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

        $church = $user->church()->firstOrFail();
        $church->forceFill([
            'settings' => array_merge($church->settings ?? [], [
                'disabled_modules' => ['meetings.rooms.studio'],
            ]),
        ])->save();

        $this->actingAs($user)
            ->get(route('meetings.rooms.show', [$session, 'livekit']))
            ->assertOk()
            ->assertDontSee(route('meetings.rooms.studio', [$session, 'livekit']), false);

        $this->actingAs($user)
            ->get($studioUrl)
            ->assertNotFound();

        $this->actingAs($user)
            ->put(route('meetings.rooms.studio.state.update', [$session, 'livekit']), [
                'speaker_name' => 'Disabled Studio',
            ])
            ->assertNotFound();

        $this->getJson(route('meetings.rooms.short.state', [$shortRoomCode, 'livekit']))
            ->assertNotFound();
    }

    public function test_livekit_tokens_use_raw_secret_when_existing_settings_were_legacy_encrypted(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $integration = MeetingIntegration::query()
            ->where('church_id', $user->church_id)
            ->where('provider', 'livekit')
            ->firstOrFail();

        $integration->update([
            'settings' => [
                ...$integration->settings,
                'api_key' => 'APIkey1',
                'api_secret_encrypted' => encrypt('secret1changeme2026cce'),
                'api_secret_configured' => true,
            ],
        ]);

        $session = EventSession::query()->firstOrFail();

        $response = $this->actingAs($user)
            ->get(route('meetings.rooms.show', [$session, 'livekit']))
            ->assertOk();

        preg_match('/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);

        [$header, $payload, $signature] = explode('.', $matches[0]);
        $signedContent = $header.'.'.$payload;
        $expectedRawSecretSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', $signedContent, 'secret1changeme2026cce', true)), '+/', '-_'), '=');
        $legacySerializedSecretSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', $signedContent, 's:22:"secret1changeme2026cce";', true)), '+/', '-_'), '=');

        $this->assertSame($expectedRawSecretSignature, $signature);
        $this->assertNotSame($legacySerializedSecretSignature, $signature);

        $integration->update([
            'settings' => [
                ...$integration->settings,
                'api_secret_encrypted' => Crypt::encryptString('secret1changeme2026cce'),
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('meetings.rooms.show', [$session, 'livekit']))
            ->assertOk();

        preg_match('/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/', $response->getContent(), $freshMatches);
        $this->assertNotEmpty($freshMatches);

        [$freshHeader, $freshPayload, $freshSignature] = explode('.', $freshMatches[0]);
        $freshSignedContent = $freshHeader.'.'.$freshPayload;
        $freshExpectedSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', $freshSignedContent, 'secret1changeme2026cce', true)), '+/', '-_'), '=');

        $this->assertSame($freshExpectedSignature, $freshSignature);
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

    private function shortRoomCode(EventSession $eventSession): string
    {
        $encodedId = strtolower(base_convert((string) $eventSession->getKey(), 10, 36));
        $signature = substr(hash_hmac('sha256', EventSession::class.':'.$eventSession->getKey(), (string) config('app.key')), 0, 16);

        return $encodedId.'-'.$signature;
    }
}
