<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Role;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicMemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_page_is_available_without_signing_in(): void
    {
        $church = Church::factory()->create(['name' => 'Grace Community Church']);
        $campus = Campus::factory()->for($church)->create(['name' => 'Central Campus']);
        Ministry::query()->create([
            'church_id' => $church->id,
            'campus_id' => $campus->id,
            'name' => 'Community Care Department',
            'status' => 'active',
        ]);

        $this->get(route('members.self-register'))
            ->assertOk()
            ->assertSee('Welcome to our church family.')
            ->assertSee('I’m new here')
            ->assertSee('I’m already a member')
            ->assertSee('Create my member login')
            ->assertSee('Securing your registration')
            ->assertSee('Please wait while we safely save your details', false)
            ->assertSee('Central Campus')
            ->assertSee('Branch or Campus')
            ->assertSee('Department or Ministry')
            ->assertSee('Community Care Department')
            ->assertSee('Select a branch or campus first')
            ->assertDontSee('Church location');
    }

    public function test_public_registration_language_switches_and_persists_for_all_supported_languages(): void
    {
        $church = Church::factory()->create();
        Campus::factory()->for($church)->create(['name' => 'Central Campus']);

        $this->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect()
            ->assertSessionHas('locale', 'fr')
            ->assertCookie('locale', 'fr');

        $this->withSession(['locale' => 'fr'])
            ->get(route('members.self-register'))
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('Bienvenue dans notre famille d’église.')
            ->assertSee('Je suis déjà membre')
            ->assertSee('Branche ou campus');

        $this->withSession(['locale' => 'es'])
            ->get(route('members.self-register'))
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Bienvenido a nuestra familia de la iglesia.')
            ->assertSee('Ya soy miembro')
            ->assertSee('Sede o campus');

        $this->withSession(['locale' => 'en'])
            ->get(route('members.self-register'))
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Welcome to our church family.')
            ->assertSee('I’m already a member');
    }

    public function test_system_language_is_used_as_the_default_locale(): void
    {
        $church = Church::factory()->create(['settings' => ['language' => 'es']]);
        Campus::factory()->for($church)->create(['name' => 'Central Campus']);

        $this->get(route('members.self-register'))
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Bienvenido a nuestra familia de la iglesia.');
    }

    public function test_language_switch_rejects_unsupported_locales(): void
    {
        $this->post(route('locale.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    public function test_registration_validation_feedback_uses_the_selected_language(): void
    {
        $church = Church::factory()->create();
        Campus::factory()->for($church)->create();

        $this->withSession(['locale' => 'es'])
            ->from(route('members.self-register'))
            ->post(route('members.self-register.store'), [
                'registration_type' => 'new',
                'preferred_contact' => 'email',
                'privacy_consent' => '0',
            ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHasErrors([
                'first_name' => 'El campo nombre es obligatorio.',
                'last_name' => 'El campo apellido es obligatorio.',
                'email' => 'Introduce un correo electrónico o un número de teléfono.',
                'privacy_consent' => 'Confirma que la iglesia puede utilizar estos datos de forma segura para membresía y cuidado pastoral.',
            ]);
    }

    public function test_authenticated_language_switch_updates_the_account_preference(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'account_settings' => []]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'es'])
            ->assertRedirect()
            ->assertSessionHas('locale', 'es');

        $this->assertSame('es', data_get($user->fresh()->account_settings, 'preferences.language'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('¡Bienvenido de nuevo')
            ->assertSee('Panel de control')
            ->assertSee('Miembros')
            ->assertSee('Acciones rápidas');

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect()
            ->assertSessionHas('locale', 'fr');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('Bon retour')
            ->assertSee('Tableau de bord')
            ->assertSee('Membres')
            ->assertSee('Actions rapides');
    }

    public function test_admin_can_share_registration_link_from_members_and_dashboard(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Share registration link')
            ->assertSee('data-registration-share', false)
            ->assertSee('member-registration');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-registration-share', false)
            ->assertSee('Share member registration link');
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
        $originalCampus = Campus::factory()->for($church)->create(['name' => 'Original Campus']);
        $selectedCampus = Campus::factory()->for($church)->create(['name' => 'North Branch']);
        $ministry = Ministry::query()->create([
            'church_id' => $church->id,
            'campus_id' => $selectedCampus->id,
            'name' => 'Hospitality Department',
            'status' => 'active',
        ]);
        $member = Member::factory()->for($church)->for($originalCampus)->create([
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
            'campus_id' => $selectedCampus->id,
            'ministry_id' => $ministry->id,
            'preferred_contact' => 'phone',
            'interests' => ['small_groups'],
            'check_in_today' => '1',
            'privacy_consent' => '1',
        ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHas('registration_complete')
            ->assertSessionHas('registration_complete.campus_name', 'North Branch')
            ->assertSessionHas('registration_complete.ministry_name', 'Hospitality Department');

        $this->assertSame(1, Member::query()->count());
        $this->assertSame($selectedCampus->id, $member->refresh()->campus_id);
        $assignment = Volunteer::query()->where('member_id', $member->id)->where('ministry_id', $ministry->id)->firstOrFail();
        $this->assertSame($selectedCampus->id, $assignment->campus_id);
        $this->assertSame('Team Member', $assignment->role);
        $this->assertSame('active', $assignment->status);
        $this->assertDatabaseHas('attendance_records', [
            'member_id' => $member->id,
            'campus_id' => $selectedCampus->id,
            'service_date' => today()->startOfDay()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $member->id,
            'action' => 'member_self_registration_returned',
        ]);
    }

    public function test_returning_member_can_create_a_portal_login_for_the_existing_member_record(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->for($church)->create();
        $member = Member::factory()->for($church)->for($campus)->create([
            'first_name' => 'Morgan',
            'last_name' => 'Reed',
            'email' => null,
            'phone' => '+1 (555) 800-1234',
        ]);

        $this->post(route('members.self-register.store'), [
            'registration_type' => 'returning',
            'first_name' => 'Morgan',
            'last_name' => 'Reed',
            'email' => 'morgan.reed@example.test',
            'phone' => '+1 (555) 800-1234',
            'campus_id' => $campus->id,
            'preferred_contact' => 'email',
            'create_account' => '1',
            'password' => 'SecurePass9',
            'password_confirmation' => 'SecurePass9',
            'privacy_consent' => '1',
        ])->assertRedirect(route('members.self-register'))
            ->assertSessionHas('registration_complete.account_created', true);

        $member->refresh();
        $user = User::query()->where('email', 'morgan.reed@example.test')->firstOrFail();

        $this->assertSame('morgan.reed@example.test', $member->email);
        $this->assertSame($member->id, $user->member_id);
        $this->assertSame($church->id, $user->church_id);
        $this->assertTrue(Hash::check('SecurePass9', $user->password));
        $this->assertSame(1, Member::query()->count());
    }

    public function test_returning_member_cannot_select_a_ministry_from_another_branch_or_campus(): void
    {
        $church = Church::factory()->create();
        $selectedCampus = Campus::factory()->for($church)->create(['name' => 'Central Campus']);
        $otherCampus = Campus::factory()->for($church)->create(['name' => 'South Campus']);
        $otherMinistry = Ministry::query()->create([
            'church_id' => $church->id,
            'campus_id' => $otherCampus->id,
            'name' => 'South Campus Media',
            'status' => 'active',
        ]);
        $member = Member::factory()->for($church)->for($selectedCampus)->create([
            'first_name' => 'Taylor',
            'last_name' => 'Morgan',
            'email' => 'taylor@example.test',
        ]);

        $this->from(route('members.self-register'))
            ->post(route('members.self-register.store'), [
                'registration_type' => 'returning',
                'first_name' => 'Taylor',
                'last_name' => 'Morgan',
                'email' => 'taylor@example.test',
                'campus_id' => $selectedCampus->id,
                'ministry_id' => $otherMinistry->id,
                'preferred_contact' => 'email',
                'privacy_consent' => '1',
            ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHasErrors('ministry_id');

        $this->assertDatabaseMissing('volunteers', [
            'member_id' => $member->id,
            'ministry_id' => $otherMinistry->id,
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

    public function test_returning_path_creates_a_member_when_no_existing_record_matches(): void
    {
        $church = Church::factory()->create();
        Campus::factory()->for($church)->create();

        $this->post(route('members.self-register.store'), [
            'registration_type' => 'returning',
            'first_name' => 'Unknown',
            'last_name' => 'Person',
            'email' => 'unknown@example.test',
            'preferred_contact' => 'email',
            'create_account' => '1',
            'password' => 'SecurePass9',
            'password_confirmation' => 'SecurePass9',
            'privacy_consent' => '1',
        ])
            ->assertRedirect(route('members.self-register'))
            ->assertSessionHas('registration_complete.account_created', true);

        $member = Member::query()->where('email', 'unknown@example.test')->firstOrFail();
        $this->assertSame('active', $member->status);
        $this->assertDatabaseHas('users', [
            'email' => 'unknown@example.test',
            'member_id' => $member->id,
        ]);
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
