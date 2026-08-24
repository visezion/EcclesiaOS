<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceVerification;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Member;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_and_kiosk_check_in_record_the_selected_member_and_operator(): void
    {
        [$admin, $member, $attendance] = $this->attendanceFixture('open');

        $this->actingAs($admin)
            ->get(route('attendance.methods', $attendance))
            ->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self)')
            ->assertSee('Session QR Code')
            ->assertSee('face_evidence', false);

        foreach (['manual', 'kiosk'] as $method) {
            $this->actingAs($admin)
                ->post(route('attendance.check-in', $attendance), [
                    'member_id' => $member->opaqueId(),
                    'method' => $method,
                    'provider' => $method,
                ])
                ->assertRedirect();
        }

        $record = AttendanceRecord::query()->where('attendance_session_id', $attendance->id)->where('member_id', $member->id)->sole();
        $this->assertSame(2, $record->verifications()->count());
        $this->assertSame($admin->id, data_get($record->verifications()->where('method', 'manual')->sole()->metadata, 'recorded_by_user_id'));
        $this->assertSame($admin->id, data_get($record->verifications()->where('method', 'kiosk')->sole()->metadata, 'kiosk_operator_user_id'));

        $otherChurch = Church::factory()->create();
        $otherCampus = Campus::factory()->for($otherChurch)->create();
        $otherMember = Member::factory()->for($otherChurch)->for($otherCampus)->create();
        $this->actingAs($admin)
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $otherMember->opaqueId(), 'method' => 'manual', 'provider' => 'manual',
            ])
            ->assertForbidden();
    }

    public function test_qr_check_in_requires_the_current_signed_session_token_and_open_status(): void
    {
        [$admin, $member, $attendance] = $this->attendanceFixture('scheduled');
        $token = $this->qrToken($attendance);

        $this->actingAs($admin)
            ->from(route('attendance.methods', $attendance))
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $member->opaqueId(), 'method' => 'qr', 'provider' => 'qr', 'qr_token' => $token,
            ])
            ->assertSessionHasErrors('method');

        $attendance->update(['status' => 'open']);

        $this->actingAs($admin)
            ->from(route('attendance.methods', $attendance))
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $member->opaqueId(), 'method' => 'qr', 'provider' => 'qr', 'qr_token' => str_repeat('a', 64),
            ])
            ->assertSessionHasErrors('qr_token');

        $this->actingAs($admin)
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $member->opaqueId(), 'method' => 'qr', 'provider' => 'qr', 'qr_token' => $token,
            ])
            ->assertRedirect();

        $verification = AttendanceVerification::query()->where('method', 'qr')->sole();
        $this->assertSame('success', $verification->status);
        $this->assertNotEmpty(data_get($verification->metadata, 'qr_token_fingerprint'));
    }

    public function test_geolocation_check_in_enforces_the_configured_venue_radius(): void
    {
        [$admin, $member, $attendance] = $this->attendanceFixture('open');

        $this->actingAs($admin)
            ->from(route('attendance.methods', $attendance))
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $member->opaqueId(), 'method' => 'geolocation', 'provider' => 'geolocation',
                'latitude' => 35.2000, 'longitude' => 33.4000,
            ])
            ->assertSessionHasErrors('latitude');

        $this->actingAs($admin)
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $member->opaqueId(), 'method' => 'geolocation', 'provider' => 'geolocation',
                'latitude' => 35.18501, 'longitude' => 33.38201,
            ])
            ->assertRedirect();

        $verification = AttendanceVerification::query()->where('method', 'geolocation')->sole();
        $this->assertLessThanOrEqual(100, (float) data_get($verification->metadata, 'distance_meters'));
        $this->assertSame(100, data_get($verification->metadata, 'approved_radius_meters'));
    }

    public function test_face_check_in_stores_private_reviewable_image_evidence(): void
    {
        Storage::fake('local');
        [$admin, $member, $attendance] = $this->attendanceFixture('open');

        $this->actingAs($admin)
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $member->opaqueId(), 'method' => 'face', 'provider' => 'face',
                'face_evidence' => UploadedFile::fake()->image('member-face.jpg', 480, 480),
            ])
            ->assertRedirect();

        $verification = AttendanceVerification::query()->where('method', 'face')->sole();
        $this->assertSame('pending_review', $verification->status);
        $path = data_get($verification->metadata, 'face_evidence_path');
        Storage::disk('local')->assertExists($path);

        $this->actingAs($admin)
            ->get(route('attendance.verifications.face-evidence', $verification))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $record = AttendanceRecord::query()->where('attendance_session_id', $attendance->id)->where('member_id', $member->id)->sole();
        $this->actingAs($admin)->delete(route('attendance.records.destroy', $record))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_linked_member_can_self_check_in_but_cannot_use_manager_methods_or_another_profile(): void
    {
        [$admin, $member, $attendance, $church, $campus] = $this->attendanceFixture('open');
        $memberUser = User::factory()->create([
            'church_id' => $church->id, 'campus_id' => $campus->id, 'member_id' => $member->id,
            'email' => $member->email,
        ]);
        $otherMember = Member::factory()->for($church)->for($campus)->create();

        $this->actingAs($memberUser)
            ->get(route('attendance.methods', $attendance))
            ->assertOk()
            ->assertDontSee('Manual Check-in')
            ->assertDontSee('Use a venue kiosk check-in station.');

        $this->actingAs($memberUser)
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $member->opaqueId(), 'method' => 'qr', 'provider' => 'qr', 'qr_token' => $this->qrToken($attendance),
            ])
            ->assertRedirect();

        $this->actingAs($memberUser)
            ->post(route('attendance.check-in', $attendance), [
                'member_id' => $otherMember->opaqueId(), 'method' => 'qr', 'provider' => 'qr', 'qr_token' => $this->qrToken($attendance),
            ])
            ->assertForbidden();
    }

    public function test_unlinked_user_is_not_silently_attached_to_the_first_member(): void
    {
        [$admin, $member, $attendance, $church, $campus] = $this->attendanceFixture('open');
        $unlinked = User::factory()->create(['church_id' => $church->id, 'campus_id' => $campus->id, 'member_id' => null]);

        $this->actingAs($unlinked)
            ->get(route('attendance.methods', $attendance))
            ->assertForbidden();

        $this->assertDatabaseMissing('attendance_records', ['attendance_session_id' => $attendance->id, 'member_id' => $member->id]);
    }

    /** @return array{User, Member, AttendanceSession, Church, Campus} */
    private function attendanceFixture(string $status): array
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->for($church)->create();
        $admin = User::factory()->create(['church_id' => $church->id, 'campus_id' => $campus->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $admin->roles()->attach($role);
        $member = Member::factory()->for($church)->for($campus)->create(['email' => 'member@example.test']);
        $program = Program::query()->create(['church_id' => $church->id, 'campus_id' => $campus->id, 'name' => 'Sunday Program', 'status' => 'ongoing']);
        $event = Event::query()->create(['church_id' => $church->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'title' => 'Sunday Gathering', 'starts_at' => now(), 'status' => 'scheduled']);
        $session = EventSession::query()->create([
            'church_id' => $church->id, 'campus_id' => $campus->id, 'event_id' => $event->id,
            'title' => 'Main Service', 'session_date' => today(), 'starts_at' => '10:00', 'ends_at' => '12:00',
            'meeting_type' => 'physical', 'status' => 'scheduled',
        ]);
        $attendance = AttendanceSession::query()->create([
            'church_id' => $church->id, 'campus_id' => $campus->id, 'event_session_id' => $session->id,
            'title' => 'Main Service Attendance', 'opens_at' => now()->subHour(), 'closes_at' => now()->addHour(),
            'methods' => ['manual', 'qr', 'geolocation', 'kiosk', 'face'], 'verification_policy' => 'best_confidence',
            'require_authenticated' => true, 'allow_guests' => false, 'geo_latitude' => 35.1850000,
            'geo_longitude' => 33.3820000, 'geo_radius_meters' => 100, 'expected_attendance' => 100, 'status' => $status,
        ]);

        return [$admin, $member, $attendance, $church, $campus];
    }

    private function qrToken(AttendanceSession $attendance): string
    {
        return hash_hmac('sha256', 'attendance-session:'.$attendance->id, (string) config('app.key'));
    }
}
