<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicMemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_page_is_available_without_signing_in(): void
    {
        $church = Church::factory()->create(['name' => 'Grace Community Church']);
        Campus::factory()->for($church)->create(['name' => 'Central Campus']);

        $this->get(route('members.self-register'))
            ->assertOk()
            ->assertSee('Welcome to our church family.')
            ->assertSee('I’m new here')
            ->assertSee('I’m already a member')
            ->assertSee('Create my member login')
            ->assertSee('Securing your registration')
            ->assertSee('Please wait while we safely save your details', false)
            ->assertSee('Central Campus');
    }

    public function test_new_member_can_self_register_and_optionally_check_in(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->for($church)->create();

        $this->post(route('members.self-register.store'), [
            'registration_type' => 'new',
            'first_name' => 'Jordan',
            'last_name' => 'Rivera',
            'preferred_name' => 'Jordy',
            'email' => 'JORDAN@example.test',
            'phone' => '+1 (555) 200-4000',
            'date_of_birth' => '1992-05-14',
            'gender' => 'Prefer not to say',
            'campus_id' => $campus->id,
            'preferred_contact' => 'email',
            'interests' => ['membership', 'serving'],
            'how_heard' => 'friend_family',
            'support_note' => 'I would like to learn about membership.',
            'communications_consent' => '1',
            'check_in_today' => '1',
            'privacy_consent' => '1',
        ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHas('registration_complete');

        $member = Member::query()->where('email', 'jordan@example.test')->firstOrFail();

        $this->assertSame('new', $member->status);
        $this->assertSame($campus->id, $member->campus_id);
        $this->assertSame('Jordy', $member->memberProfile?->preferred_name);
        $this->assertSame(['membership', 'serving'], data_get($member->memberProfile?->spiritual_journey, 'connection_interests'));
        $this->assertSame('email', data_get($member->memberProfile?->communication_preferences, 'preferred_contact'));
        $this->assertDatabaseHas('attendance_records', [
            'member_id' => $member->id,
            'campus_id' => $campus->id,
            'service_date' => today()->startOfDay()->toDateTimeString(),
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'church_id' => $church->id,
            'subject_id' => $member->id,
            'action' => 'member_self_registered',
        ]);
        $this->assertDatabaseHas('communication_deliveries', [
            'member_id' => $member->id,
            'event_type' => 'RegistrationConfirmed',
            'status' => 'delivered',
        ]);
    }

    public function test_returning_member_is_matched_without_creating_a_duplicate(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->for($church)->create();
        $member = Member::factory()->for($church)->for($campus)->create([
            'first_name' => 'Taylor',
            'last_name' => 'Morgan',
            'email' => null,
            'phone' => '+1 (555) 321-9876',
        ]);

        $this->post(route('members.self-register.store'), [
            'registration_type' => 'returning',
            'first_name' => 'taylor',
            'last_name' => 'MORGAN',
            'phone' => '15553219876',
            'campus_id' => $campus->id,
            'preferred_contact' => 'phone',
            'interests' => ['small_groups'],
            'check_in_today' => '1',
            'privacy_consent' => '1',
        ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHas('registration_complete');

        $this->assertSame(1, Member::query()->count());
        $this->assertDatabaseHas('attendance_records', [
            'member_id' => $member->id,
            'service_date' => today()->startOfDay()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $member->id,
            'action' => 'member_self_registration_returned',
        ]);
    }

    public function test_contact_information_cannot_be_attached_to_a_different_name(): void
    {
        $church = Church::factory()->create();
        Campus::factory()->for($church)->create();
        Member::factory()->for($church)->create([
            'first_name' => 'Existing',
            'last_name' => 'Member',
            'email' => 'existing@example.test',
        ]);

        $this->from(route('members.self-register'))
            ->post(route('members.self-register.store'), [
                'registration_type' => 'new',
                'first_name' => 'Different',
                'last_name' => 'Person',
                'email' => 'existing@example.test',
                'preferred_contact' => 'email',
                'privacy_consent' => '1',
            ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHasErrors('identity');

        $this->assertSame(1, Member::query()->count());
    }

    public function test_returning_path_uses_a_generic_error_when_no_member_matches(): void
    {
        $church = Church::factory()->create();
        Campus::factory()->for($church)->create();

        $this->from(route('members.self-register'))
            ->post(route('members.self-register.store'), [
                'registration_type' => 'returning',
                'first_name' => 'Unknown',
                'last_name' => 'Person',
                'email' => 'unknown@example.test',
                'preferred_contact' => 'email',
                'privacy_consent' => '1',
            ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHasErrors('identity');

        $this->assertDatabaseCount('members', 0);
    }

    public function test_new_member_can_create_a_login_with_the_default_member_role(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->for($church)->create();

        $this->post(route('members.self-register.store'), [
            'registration_type' => 'new',
            'first_name' => 'Avery',
            'last_name' => 'Stone',
            'email' => 'avery@example.test',
            'phone' => '+1 555 100 2000',
            'campus_id' => $campus->id,
            'preferred_contact' => 'email',
            'create_account' => '1',
            'password' => 'SecurePass9',
            'password_confirmation' => 'SecurePass9',
            'privacy_consent' => '1',
        ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHas('registration_complete.account_created', true)
            ->assertSessionHas('registration_complete.email', 'avery@example.test');

        $member = Member::query()->where('email', 'avery@example.test')->firstOrFail();
        $user = User::query()->where('email', 'avery@example.test')->firstOrFail();

        $this->assertSame($member->id, $user->member_id);
        $this->assertSame($church->id, $user->church_id);
        $this->assertSame($campus->id, $user->campus_id);
        $this->assertSame('Member', $user->title);
        $this->assertTrue(Hash::check('SecurePass9', $user->password));
        $this->assertTrue($user->roles()->where('name', 'Member')->exists());
        $this->assertFalse($user->hasPermission('view dashboard'));
        $this->assertTrue($user->hasPermission('use bible'));
    }

    public function test_returning_member_can_claim_a_login_without_creating_a_duplicate_member(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->for($church)->create();
        $member = Member::factory()->for($church)->for($campus)->create([
            'first_name' => 'Morgan',
            'last_name' => 'Lee',
            'email' => 'morgan@example.test',
        ]);

        $this->post(route('members.self-register.store'), [
            'registration_type' => 'returning',
            'first_name' => 'Morgan',
            'last_name' => 'Lee',
            'email' => 'morgan@example.test',
            'preferred_contact' => 'email',
            'create_account' => '1',
            'password' => 'MemberPass8',
            'password_confirmation' => 'MemberPass8',
            'privacy_consent' => '1',
        ])->assertSessionHas('registration_complete.account_created', true);

        $this->assertDatabaseCount('members', 1);
        $this->assertSame($member->id, User::query()->sole()->member_id);
    }

    public function test_member_login_is_redirected_to_member_safe_content(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->for($church)->create();

        $this->post(route('members.self-register.store'), [
            'registration_type' => 'new',
            'first_name' => 'Jamie',
            'last_name' => 'Cole',
            'email' => 'jamie@example.test',
            'campus_id' => $campus->id,
            'preferred_contact' => 'email',
            'create_account' => '1',
            'password' => 'MemberPass8',
            'password_confirmation' => 'MemberPass8',
            'privacy_consent' => '1',
        ]);

        $this->post(route('login.store'), [
            'email' => 'jamie@example.test',
            'password' => 'MemberPass8',
        ])->assertRedirect(route('bible.index'));

        $this->assertAuthenticatedAs(User::query()->where('email', 'jamie@example.test')->firstOrFail());
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_existing_user_email_cannot_be_reused_for_a_member_login(): void
    {
        $church = Church::factory()->create();
        Campus::factory()->for($church)->create();
        User::factory()->create(['email' => 'staff@example.test']);

        $this->from(route('members.self-register'))
            ->post(route('members.self-register.store'), [
                'registration_type' => 'new',
                'first_name' => 'New',
                'last_name' => 'Member',
                'email' => 'staff@example.test',
                'preferred_contact' => 'email',
                'create_account' => '1',
                'password' => 'MemberPass8',
                'password_confirmation' => 'MemberPass8',
                'privacy_consent' => '1',
            ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('members', 0);
    }
}
