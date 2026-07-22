<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Member;
use App\Models\MeetingIntegration;
use App\Models\Program;
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
