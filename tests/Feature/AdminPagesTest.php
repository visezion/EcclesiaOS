<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CareTask;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backed_admin_pages_render(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        foreach ([
            'users.index' => 'Users Management',
            'roles.index' => 'Roles &amp; Permissions',
            'campuses.index' => 'Churches &amp; Campuses',
            'audit-logs.index' => 'Audit Logs',
        ] as $route => $text) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee($text, false);
        }
    }

    public function test_user_directory_can_be_exported(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('users.export'));

        $response->assertOk();
        $this->assertStringContainsString('Name,Email,Phone,Role,Church,Campus,Status,"MFA Enabled","Last Login"', $response->streamedContent());
        $this->assertStringContainsString('Pastor John', $response->streamedContent());
    }

    public function test_user_directory_renders_complete_donut_charts(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Users by Status')
            ->assertSee('Users by Role')
            ->assertSee('Users by Campus')
            ->assertSee('Inactive')
            ->assertSee('Suspended')
            ->assertSee('Other Roles')
            ->assertSee('Other Campuses')
            ->assertSee('data-chart="doughnut"', false)
            ->assertSee('data-colors=', false)
            ->assertSee('conic-gradient(', false);
    }

    public function test_user_directory_links_to_full_admin_profile_pages(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $target = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.show', $target), false)
            ->assertSee(route('users.show', ['user' => $target, 'edit' => 1]), false)
            ->assertSee(route('users.message', $target), false)
            ->assertSee(route('users.impersonate', $target), false);
    }

    public function test_user_directory_message_action_is_persistent(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $target = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('users.message', $target), [
                'channel' => 'portal',
                'subject' => 'Schedule update',
                'message' => 'Please review your updated ministry schedule.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'Communications',
            'action' => 'user_message_sent',
            'subject_type' => $target->getMorphClass(),
            'subject_id' => $target->id,
        ]);
    }

    public function test_administrator_can_manage_full_user_profile_and_impersonate(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $target = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $role = Role::query()->where('name', 'Staff')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertSee('User Profile')
            ->assertSee('Profile Completion')
            ->assertSee('Back to Users')
            ->assertSee(route('users.update', $target), false)
            ->assertSee(route('users.impersonate', $target), false);

        $this->actingAs($admin)
            ->put(route('users.update', $target), [
                'name' => 'Sarah Johnson Profile',
                'title' => 'Campus Operations Lead',
                'email' => 'sarah.profile@klgc.org',
                'phone' => '+1 (555) 222-7777',
                'date_of_birth' => '1980-04-12',
                'gender' => 'Female',
                'address' => '456 Admin Way, Dallas, TX',
                'timezone' => 'America/Chicago',
                'emergency_contact_name' => 'Pastor John',
                'emergency_contact_relationship' => 'Supervisor',
                'emergency_contact_phone' => '+1 (555) 111-2222',
                'recovery_email' => 'sarah.profile.recovery@klgc.org',
                'status' => 'active',
                'church_id' => $admin->church_id,
                'campus_id' => $admin->campus_id,
                'roles' => [$role->id],
            ])
            ->assertRedirect();

        $target->refresh();
        $dateOfBirth = $target->getAttribute('date_of_birth');

        $this->assertSame('Sarah Johnson Profile', $target->name);
        $this->assertSame('Campus Operations Lead', $target->title);
        $this->assertInstanceOf(CarbonInterface::class, $dateOfBirth);
        $this->assertSame('1980-04-12', $dateOfBirth->toDateString());
        $this->assertTrue($target->roles()->where('name', 'Staff')->exists());

        $this->actingAs($admin)
            ->put(route('users.password', $target), [
                'password' => 'AdminReset!234',
                'password_confirmation' => 'AdminReset!234',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('AdminReset!234', $target->fresh()->password));

        $this->actingAs($admin)
            ->post(route('users.impersonate', $target))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('impersonator_id', $admin->id);

        $this->assertAuthenticatedAs($target);

        $this->post(route('users.impersonation.stop'))
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_audit_logs_render_filters_metrics_and_export(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('audit-logs.index', ['tab' => 'authentication', 'risk' => 'high', 'date_range' => 'all']))
            ->assertOk()
            ->assertSee('Authentication Events')
            ->assertSee('Security Score')
            ->assertSee('Failed Logins')
            ->assertSee('Security & Access Overview', false)
            ->assertSee('data-chart="sparkline"', false)
            ->assertSee('administration/audit-logs/export?tab=authentication&amp;risk=high&amp;date_range=all', false)
            ->assertSee('Failed Login');

        $response = $this->actingAs($admin)->get(route('audit-logs.export', ['risk' => 'high', 'date_range' => 'all']));

        $response->assertOk();
        $this->assertStringContainsString('Time,User,Email,Role,Church,Campus,Action,Resource,Details,"IP Address","Risk Level",Status', $response->streamedContent());
        $this->assertStringContainsString('Failed Login', $response->streamedContent());

        $selectedLog = ActivityLog::query()->where('action', 'failed_login')->firstOrFail();
        $selectedExport = $this->actingAs($admin)->get(route('audit-logs.export', ['ids' => [$selectedLog->id], 'date_range' => 'all']));

        $selectedExport->assertOk();
        $this->assertStringContainsString($selectedLog->description, $selectedExport->streamedContent());
    }

    public function test_audit_logs_sidebar_link_is_active(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('href="'.route('audit-logs.index').'"', false)
            ->assertSee('Audit Logs')
            ->assertSee('aria-current="page"', false)
            ->assertSee('pointer-events-none', false);
    }

    public function test_sidebar_background_can_be_uploaded_and_reset(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('settings.branding.sidebar-background'), [
                'sidebar_background' => UploadedFile::fake()->image('sidebar.png', 520, 260),
            ])
            ->assertRedirect();

        $church = Church::query()->firstOrFail()->refresh();
        $path = data_get($church->settings, 'sidebar_background');

        $this->assertIsString($path);
        $this->assertStringStartsWith('branding/sidebar-background-', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Sidebar Background')
            ->assertSee('storage/'.$path, false);

        $this->actingAs($admin)
            ->delete(route('settings.branding.sidebar-background.reset'))
            ->assertRedirect();

        $this->assertNull(data_get(Church::query()->firstOrFail()->refresh()->settings, 'sidebar_background'));
    }

    public function test_system_settings_can_be_saved_reset_and_connection_tested(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $payload = $this->systemSettingsPayload($admin);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('System Health')
            ->assertSee('Compliance & Security', false)
            ->assertSee(route('settings.system.update'), false)
            ->assertSee(route('settings.system.reset'), false)
            ->assertSee(route('settings.system.test-connection'), false);

        $this->actingAs($admin)
            ->put(route('settings.system.update'), array_merge($payload, [
                'system_name' => 'EcclesiaOS Live',
                'church_name' => 'Kingdom Life Updated',
                'primary_email' => 'info@updated.test',
                'smtp_server' => 'smtp.updated.test',
                'default_user_role' => 'Staff',
                'primary_color' => '#0EA5E9',
                'secondary_color' => '#14B8A6',
                'theme_mode' => 'dark',
                'font_family' => 'Roboto',
                'font_scale' => 'comfortable',
                'page_background' => '#EEF2FF',
                'sidebar_start_color' => '#111827',
                'sidebar_middle_color' => '#1E3A8A',
                'sidebar_end_color' => '#030712',
                'sidebar_text_color' => '#F8FAFC',
                'sidebar_profile_color' => '#172554',
                'card_radius' => 12,
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', 'System settings saved.');

        $church = Church::query()->firstOrFail()->refresh();
        $this->assertSame('Kingdom Life Updated', $church->name);
        $this->assertSame('EcclesiaOS Live', data_get($church->settings, 'system_name'));
        $this->assertSame('smtp.updated.test', data_get($church->settings, 'smtp_server'));
        $this->assertSame('#0EA5E9', data_get($church->settings, 'primary_color'));
        $this->assertSame('dark', data_get($church->settings, 'theme_mode'));
        $this->assertSame('Roboto', data_get($church->settings, 'font_family'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'system_settings_updated']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Settings Default User',
                'email' => 'settings.default@kingdomhub.test',
                'status' => 'active',
                'password' => 'Password!234',
                'password_confirmation' => 'Password!234',
            ])
            ->assertRedirect();

        $defaultedUser = User::query()->where('email', 'settings.default@kingdomhub.test')->firstOrFail();
        $this->assertSame($admin->campus_id, $defaultedUser->campus_id);
        $this->assertSame('America/Chicago', $defaultedUser->timezone);
        $this->assertTrue($defaultedUser->roles()->where('name', 'Staff')->exists());

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('--brand-primary: #0EA5E9', false)
            ->assertSee('--font-app: Roboto', false)
            ->assertSee('--sidebar-start: #111827', false)
            ->assertSee('value="#0EA5E9"', false);

        $this->actingAs($admin)
            ->post(route('settings.system.test-connection'), ['service' => 'smtp'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($admin)
            ->put(route('settings.system.reset'))
            ->assertRedirect()
            ->assertSessionHas('status', 'System settings reset to defaults.');

        $this->assertSame(config('app.name'), data_get(Church::query()->firstOrFail()->refresh()->settings, 'system_name'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'system_settings_reset']);
    }

    public function test_campus_directory_renders_map_donut_and_assignment_panel(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('campuses.index'))
            ->assertOk()
            ->assertSee('Church & Campus Distribution', false)
            ->assertSee('Role Allocation Overview')
            ->assertSee('Assignment Overview')
            ->assertSee('Assign User to Church & Campus', false)
            ->assertSee('data-campus-row', false)
            ->assertSee('data-chart="doughnut"', false)
            ->assertSee('data-colors=', false)
            ->assertSee('conic-gradient(', false)
            ->assertSee('data-lucide="map-pin"', false);
    }

    public function test_administrator_can_create_campus_from_directory(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('campuses.store'), [
                'church_id' => $admin->church_id,
                'name' => 'Test Outreach Campus',
                'type' => 'Regional Campus',
                'status' => 'active',
                'city' => 'Austin',
                'country' => 'USA',
                'address' => 'Austin, TX',
                'capacity' => 450,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campuses', [
            'name' => 'Test Outreach Campus',
            'city' => 'Austin',
            'capacity' => 450,
        ]);
    }

    public function test_members_management_renders_live_directory(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Members Management')
            ->assertSee('Members Directory')
            ->assertSee('Members by Status')
            ->assertSee('Members by Campus')
            ->assertSee('Import Members')
            ->assertSee(route('members.export'), false)
            ->assertSee('data-lucide="user-plus"', false);
    }

    public function test_members_management_actions_are_persistent(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $ministry = Ministry::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('members.store'), [
                'church_id' => $admin->church_id,
                'campus_id' => $admin->campus_id,
                'ministry_id' => $ministry->id,
                'first_name' => 'Jordan',
                'last_name' => 'Member',
                'email' => 'jordan.member@klgc.org',
                'phone' => '+1 (555) 333-4444',
                'status' => 'active',
                'joined_at' => '2026-07-01',
            ])
            ->assertRedirect();

        $member = Member::query()->where('email', 'jordan.member@klgc.org')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['action' => 'member_created']);
        $this->assertTrue($member->volunteers()->where('ministry_id', $ministry->id)->exists());

        $this->actingAs($admin)
            ->get(route('members.index', ['view' => $member->opaqueId()]))
            ->assertOk()
            ->assertSee('Member Profile')
            ->assertSee('Jordan Member');

        $this->actingAs($admin)
            ->put(route('members.update', $member), [
                'church_id' => $admin->church_id,
                'campus_id' => $admin->campus_id,
                'first_name' => 'Jordan',
                'last_name' => 'Updated',
                'email' => 'jordan.updated@klgc.org',
                'phone' => '+1 (555) 333-5555',
                'status' => 'follow-up',
                'joined_at' => '2026-07-02',
                'family_name' => 'Updated Household',
                'preferred_name' => 'Jordy',
                'date_of_birth' => '1990-05-15',
                'gender' => 'Male',
                'marital_status' => 'Married',
                'occupation' => 'Operations Lead',
                'employer' => 'Kingdom Life Global Church',
                'address_line' => '123 Updated Way',
                'city' => 'Dallas',
                'state' => 'TX',
                'postal_code' => '75201',
                'country' => 'USA',
                'alternate_email' => 'jordan.alt@klgc.org',
                'home_phone' => '+1 (555) 333-6666',
                'emergency_contact_name' => 'Casey Updated',
                'emergency_contact_relationship' => 'Spouse',
                'emergency_contact_phone' => '+1 (555) 333-7777',
                'care_level' => 'follow-up',
                'care_notes' => 'Needs a pastoral care call next week.',
                'volunteer_hours' => 48,
                'skills' => 'Operations, Hospitality',
                'preferred_contact' => 'email',
                'email_notifications' => '1',
                'sms_notifications' => '1',
                'mailing_mail' => '1',
                'salvation_date' => '2015-06-01',
                'baptism_date' => '2015-07-01',
                'discipleship_class' => 'Completed',
                'membership_class' => 'Completed',
            ])
            ->assertRedirect();

        $member->refresh();
        $family = $member->family()->firstOrFail();

        $this->assertSame('Updated', $member->last_name);
        $this->assertSame('follow-up', $member->status);
        $this->assertSame('Updated Household', $family->getAttribute('name'));
        $this->assertDatabaseHas('member_profiles', [
            'member_id' => $member->id,
            'preferred_name' => 'Jordy',
            'occupation' => 'Operations Lead',
            'care_level' => 'follow-up',
            'volunteer_hours' => 48,
        ]);

        $this->actingAs($admin)
            ->get(route('members.show', $member))
            ->assertOk()
            ->assertSee('Operations Lead')
            ->assertSee('Casey Updated')
            ->assertSee('Operations');

        $deleteMember = Member::query()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'first_name' => 'Delete',
            'last_name' => 'Member',
            'email' => 'delete.member@klgc.org',
            'phone' => '+1 (555) 333-8888',
            'status' => 'active',
            'joined_at' => '2026-07-04',
        ]);

        $this->actingAs($admin)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee(route('members.destroy', $deleteMember), false);

        $this->actingAs($admin)
            ->delete(route('members.destroy', $deleteMember))
            ->assertRedirect(route('members.index'));

        $this->assertSoftDeleted('members', ['id' => $deleteMember->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'member_deleted']);

        $this->actingAs($admin)
            ->post(route('members.bulk'), [
                'action' => 'inactive',
                'members' => [$member->opaqueId()],
            ])
            ->assertRedirect();

        $this->assertSame('inactive', $member->fresh()?->status);

        $response = $this->actingAs($admin)->get(route('members.export'));
        $response->assertOk();
        $this->assertStringContainsString('"Member ID","Full Name",Email,Phone,Status,Campus,Family,Ministry,"Joined At","Attendance 30 Days","Giving Status"', $response->streamedContent());
        $this->assertStringContainsString('Jordan Updated', $response->streamedContent());
        $this->assertStringContainsString('Operations Lead', $response->streamedContent());
        $this->assertStringContainsString('follow-up,48', $response->streamedContent());
    }

    public function test_members_can_be_imported_from_csv(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'members.csv',
            "first_name,last_name,email,phone,status,joined_at\nImport,Member,import.member@klgc.org,+1 (555) 444-5555,active,2026-07-03\n",
        );

        $this->actingAs($admin)
            ->post(route('members.import'), ['members_file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('members', [
            'first_name' => 'Import',
            'last_name' => 'Member',
            'email' => 'import.member@klgc.org',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'members_imported']);
    }

    public function test_member_create_and_profile_pages_are_connected(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $ministry = Ministry::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('members.create'))
            ->assertOk()
            ->assertSee('Add New Member')
            ->assertSee(route('members.store'), false)
            ->assertSee('Onboarding Progress');

        $this->actingAs($admin)
            ->get(route('members.show', $member))
            ->assertOk()
            ->assertSee('Member Profile')
            ->assertSee($member->first_name.' '.$member->last_name)
            ->assertSee('mailto:'.$member->email, false)
            ->assertSee(route('members.update', $member), false)
            ->assertSee(route('members.check-in', $member), false)
            ->assertSee(route('members.assign-ministry', $member), false)
            ->assertSee(route('members.destroy', $member), false)
            ->assertSee(route('care-tasks.store'), false);

        $this->actingAs($admin)
            ->post(route('members.check-in', $member))
            ->assertRedirect();

        $this->assertTrue($member->attendanceRecords()->whereDate('service_date', today())->exists());

        $this->actingAs($admin)
            ->post(route('members.assign-ministry', $member), ['ministry_id' => $ministry->id])
            ->assertRedirect();

        $this->assertTrue($member->volunteers()->where('ministry_id', $ministry->id)->exists());
    }

    public function test_families_management_is_database_backed(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('families.index'))
            ->assertOk()
            ->assertSee('Families &amp; Households Management', false)
            ->assertSee('Households Directory')
            ->assertSee(route('families.export'), false);

        $this->actingAs($admin)
            ->post(route('families.store'), [
                'name' => 'Feature Household',
                'campus_id' => $admin->campus_id,
                'primary_contact_id' => $member->id,
                'member_ids' => [$member->opaqueId()],
                'address' => '100 Feature Way',
            ])
            ->assertRedirect();

        $family = Family::query()->where('name', 'Feature Household')->firstOrFail();
        $this->assertSame($family->id, $member->fresh()->family_id);

        $response = $this->actingAs($admin)->get(route('families.export'));
        $response->assertOk();
        $this->assertStringContainsString('Feature Household', $response->streamedContent());
    }

    public function test_pastoral_care_tasks_are_database_backed(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('members.follow-up'))
            ->assertOk()
            ->assertSee('Member Follow-up &amp; Pastoral Care', false)
            ->assertSee('Members Needing Attention')
            ->assertSee(route('care-tasks.export'), false);

        $this->actingAs($admin)
            ->post(route('care-tasks.store'), [
                'member_id' => $member->opaqueId(),
                'assigned_user_id' => $admin->opaqueId(),
                'type' => 'Counseling',
                'priority' => 'high',
                'status' => 'pending',
                'next_action' => 'Schedule follow-up call',
                'notes' => 'Member requested pastoral care.',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $task = CareTask::query()->where('member_id', $member->id)->where('next_action', 'Schedule follow-up call')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('care-tasks.bulk'), [
                'action' => 'resolved',
                'tasks' => [$task->opaqueId()],
            ])
            ->assertRedirect();

        $this->assertSame('resolved', $task->fresh()?->status);
        $this->assertNotNull($task->fresh()?->resolved_at);

        $response = $this->actingAs($admin)->get(route('care-tasks.export'));
        $response->assertOk();
        $this->assertStringContainsString('Schedule follow-up call', $response->streamedContent());
    }

    public function test_bulk_user_actions_update_selected_users(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('users.bulk'), [
                'action' => 'suspend',
                'users' => [$user->opaqueId()],
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $user->fresh()?->status);
    }

    public function test_user_directory_edit_form_updates_user(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $role = Role::query()->where('name', 'Staff')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Sarah Johnson Updated',
                'title' => 'Operations Coordinator',
                'email' => 'sarah.updated@klgc.org',
                'phone' => '+1 (555) 222-3333',
                'status' => 'inactive',
                'church_id' => $admin->church_id,
                'campus_id' => $admin->campus_id,
                'roles' => [$role->id],
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Sarah Johnson Updated', $user->name);
        $this->assertSame('inactive', $user->status);
        $this->assertTrue($user->roles()->where('name', 'Staff')->exists());
    }

    public function test_role_report_can_be_exported(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('roles.report'));

        $response->assertOk();
        $this->assertStringContainsString('Role,Type,"Users Assigned",Permission', $response->streamedContent());
        $this->assertStringContainsString('"Super Administrator",System', $response->streamedContent());
    }

    public function test_administrator_can_create_clone_and_reset_roles(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $permission = Permission::query()->where('name', 'manage members')->firstOrFail();
        $source = Role::query()->where('name', 'Staff')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Hospitality Lead',
                'description' => 'Coordinates hospitality access',
                'permissions' => [$permission->id],
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::query()->where('name', 'Hospitality Lead')->firstOrFail();
        $this->assertTrue($role->permissions()->where('name', 'manage members')->exists());

        $this->actingAs($admin)
            ->post(route('roles.clone', $source), [
                'name' => 'Staff Copy',
                'description' => 'Cloned from Staff',
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertTrue(Role::query()->where('name', 'Staff Copy')->firstOrFail()->permissions()->where('name', 'view dashboard')->exists());

        $role->permissions()->sync([$permission->id]);

        $this->actingAs($admin)
            ->put(route('roles.reset', $role))
            ->assertRedirect();

        $this->assertFalse($role->fresh()?->permissions()->exists());
    }

    /**
     * @return array<string, mixed>
     */
    private function systemSettingsPayload(User $admin): array
    {
        return [
            'system_name' => 'KingdomHub',
            'church_name' => 'Kingdom Life Global Church',
            'primary_email' => 'info@klgc.org',
            'support_email' => 'support@klgc.org',
            'phone' => '+1 (555) 012-3456',
            'address' => '123 Kingdom Way, Dallas, TX',
            'timezone' => 'America/Chicago',
            'date_format' => 'M d, Y',
            'currency' => 'USD',
            'language' => 'English (US)',
            'primary_color' => '#6C4DFF',
            'secondary_color' => '#A855F7',
            'page_background' => '#F6F8FC',
            'card_radius' => 8,
            'font_family' => 'Inter',
            'font_scale' => 'default',
            'theme_mode' => 'light',
            'sidebar_start_color' => '#061633',
            'sidebar_middle_color' => '#082851',
            'sidebar_end_color' => '#061633',
            'sidebar_text_color' => '#E2E8F0',
            'sidebar_profile_color' => '#020617',
            'email_template_branding' => 'Use Custom Branding',
            'mfa_required' => '1',
            'login_notifications' => '1',
            'password_policy' => 'Strong (Recommended)',
            'session_timeout' => 30,
            'sso_provider' => 'Google Workspace',
            'ip_restriction' => 'Disabled',
            'device_trust' => 'Trusted Devices Only',
            'account_lockout_policy' => '5 attempts, 15 min lock',
            'default_user_role' => 'Member',
            'approval_requirements' => 'Manager Approval',
            'policy_enforcement' => 'Strict',
            'data_access_scope' => 'Role-Based Access',
            'branch_visibility_rules' => 'By Assignment',
            'headquarters_church_id' => $admin->church_id,
            'default_campus_id' => $admin->campus_id,
            'multi_campus_access' => 'Role-Based Access',
            'branch_code_prefix' => 'KLGC',
            'smtp_server' => 'smtp.klgc.org',
            'sms_provider' => 'Twilio',
            'whatsapp_integration' => '360dialog',
            'notification_preferences' => 'Standard (Recommended)',
            'receipt_numbering' => 'Auto Increment',
            'giving_categories' => '10 Categories',
            'tax_handling' => 'Tax Exempt',
            'fiscal_year_start' => 'January',
            'depreciation_method' => 'Straight Line',
            'maintenance_alerts' => '30 Days Before',
            'asset_categories' => '12 Categories',
            'stock_threshold_alert' => '10 Items',
            'low_stock_alerts' => '1',
            'sku_format' => 'KLGC-YYYY-####',
            'order_approval_workflow' => 'Manager Approval',
            'payment_methods' => 'Card, PayPal, Bank Transfer',
            'backup_frequency' => 'Every 6 hours',
            'backup_retention' => '90 days',
            'audit_retention' => '7 years',
            'localization_region' => 'United States',
        ];
    }
}
