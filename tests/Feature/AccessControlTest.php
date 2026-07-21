<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\CareTask;
use App\Models\Family;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_access_control_page_loads_for_super_administrator(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Authentication & Security', false)
            ->assertSee('Save Changes');
    }

    public function test_administrator_can_create_user_with_role_and_assignment(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $role = Role::query()->where('name', 'Viewer')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Access Viewer',
                'title' => 'Viewer',
                'email' => 'viewer@kingdomhub.test',
                'status' => 'active',
                'church_id' => $admin->church_id,
                'campus_id' => $admin->campus_id,
                'password' => 'Password!234',
                'password_confirmation' => 'Password!234',
                'roles' => [$role->id],
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'viewer@kingdomhub.test')->firstOrFail();

        $this->assertTrue($user->roles()->where('name', 'Viewer')->exists());
        $this->assertDatabaseHas('activity_logs', ['action' => 'user_created']);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'inactive@kingdomhub.test',
            'status' => 'inactive',
        ]);

        $this->post(route('login.store'), [
            'email' => 'inactive@kingdomhub.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_without_permission_cannot_access_module(): void
    {
        $this->seed();
        $viewer = User::factory()->create(['church_id' => User::first()?->church_id]);
        $viewer->roles()->attach(Role::query()->where('name', 'Viewer')->firstOrFail());

        $this->actingAs($viewer)
            ->get(route('members.index'))
            ->assertForbidden();
    }

    public function test_logout_is_activity_logged(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertTrue(ActivityLog::query()->where('action', 'logout')->exists());
    }

    public function test_campus_scoped_member_manager_cannot_access_other_campus_member_ids(): void
    {
        $this->seed();

        $actor = User::query()->where('email', 'amanda.brown@klgc.org')->firstOrFail();
        $otherCampus = Campus::query()
            ->where('church_id', $actor->church_id)
            ->whereKeyNot($actor->campus_id)
            ->firstOrFail();
        $target = Member::factory()->create([
            'church_id' => $actor->church_id,
            'campus_id' => $otherCampus->id,
            'first_name' => 'Private',
            'last_name' => 'Member',
            'email' => 'private.member@security.test',
            'status' => 'active',
        ]);

        $this->actingAs($actor)
            ->get(route('members.show', $target))
            ->assertForbidden();

        $this->actingAs($actor)
            ->get(route('members.index', ['view' => $target->id]))
            ->assertOk()
            ->assertDontSee('private.member@security.test');

        $this->actingAs($actor)
            ->get(route('members.index', ['edit' => $target->id]))
            ->assertOk()
            ->assertDontSee('private.member@security.test');

        $this->actingAs($actor)
            ->put(route('members.update', $target), [
                'church_id' => $target->church_id,
                'campus_id' => $target->campus_id,
                'first_name' => 'Changed',
                'last_name' => 'Member',
                'email' => 'private.member@security.test',
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('members.bulk'), [
                'members' => [$target->id],
                'action' => 'archive',
            ])
            ->assertRedirect();

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_campus_scoped_admin_cannot_access_other_campus_user_ids(): void
    {
        $this->seed();

        $actor = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $otherCampus = Campus::query()
            ->where('church_id', $actor->church_id)
            ->whereKeyNot($actor->campus_id)
            ->firstOrFail();
        $target = User::factory()->create([
            'church_id' => $actor->church_id,
            'campus_id' => $otherCampus->id,
            'name' => 'Other Campus User',
            'email' => 'other-campus-user@security.test',
            'status' => 'active',
        ]);
        $target->roles()->attach(Role::query()->where('name', 'Viewer')->firstOrFail());

        $this->actingAs($actor)
            ->get(route('users.show', $target))
            ->assertForbidden();

        $this->actingAs($actor)
            ->put(route('users.update', $target), [
                'name' => 'Updated Other Campus User',
                'email' => 'other-campus-user@security.test',
                'status' => 'suspended',
                'church_id' => $target->church_id,
                'campus_id' => $target->campus_id,
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('other-campus-user@security.test');

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_non_super_admin_cannot_create_super_admin_or_assign_outside_campus(): void
    {
        $this->seed();

        $actor = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $superRole = Role::query()->where('name', 'Super Administrator')->firstOrFail();
        $otherCampus = Campus::query()
            ->where('church_id', $actor->church_id)
            ->whereKeyNot($actor->campus_id)
            ->firstOrFail();

        $this->actingAs($actor)
            ->post(route('users.store'), [
                'name' => 'Escalation Attempt',
                'title' => 'User',
                'email' => 'escalation-attempt@security.test',
                'status' => 'active',
                'church_id' => $actor->church_id,
                'campus_id' => $otherCampus->id,
                'password' => 'Password!234',
                'password_confirmation' => 'Password!234',
                'roles' => [$superRole->id],
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'escalation-attempt@security.test')->firstOrFail();

        $this->assertSame($actor->campus_id, $created->campus_id);
        $this->assertFalse($created->isSuperAdministrator());
        $this->assertFalse($created->roles()->where('name', 'Super Administrator')->exists());
    }

    public function test_family_and_pastoral_query_ids_are_scoped_to_actor_campus(): void
    {
        $this->seed();

        $actor = User::query()->where('email', 'amanda.brown@klgc.org')->firstOrFail();
        $otherCampus = Campus::query()
            ->where('church_id', $actor->church_id)
            ->whereKeyNot($actor->campus_id)
            ->firstOrFail();
        $targetMember = Member::factory()->create([
            'church_id' => $actor->church_id,
            'campus_id' => $otherCampus->id,
            'first_name' => 'Pastoral',
            'last_name' => 'Private',
            'email' => 'pastoral.private@security.test',
        ]);
        $family = Family::query()->create([
            'church_id' => $actor->church_id,
            'campus_id' => $otherCampus->id,
            'name' => 'Private Household',
            'primary_contact_id' => $targetMember->id,
        ]);
        $task = CareTask::query()->create([
            'church_id' => $actor->church_id,
            'campus_id' => $otherCampus->id,
            'member_id' => $targetMember->id,
            'assigned_user_id' => $actor->id,
            'type' => 'Counseling',
            'priority' => 'high',
            'status' => 'pending',
            'next_action' => 'Private follow up',
        ]);

        $this->actingAs($actor)
            ->get(route('families.index', ['selected' => $family->id]))
            ->assertOk()
            ->assertDontSee('Private Household');

        $this->actingAs($actor)
            ->put(route('families.update', $family), [
                'name' => 'Changed Private Household',
                'campus_id' => $family->campus_id,
                'primary_contact_id' => $targetMember->id,
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->get(route('members.follow-up', ['task' => $task->id]))
            ->assertOk()
            ->assertDontSee('Private follow up');

        $this->actingAs($actor)
            ->put(route('care-tasks.update', $task), [
                'member_id' => $targetMember->id,
                'campus_id' => $task->campus_id,
                'assigned_user_id' => $actor->id,
                'type' => 'Counseling',
                'priority' => 'high',
                'status' => 'resolved',
                'next_action' => 'Changed private follow up',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_audit_logs_require_permission_and_filter_ids_stay_scoped(): void
    {
        $this->seed();

        $viewer = User::query()->where('email', 'jessica.lee@klgc.org')->firstOrFail();
        $admin = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $otherCampus = Campus::query()
            ->where('church_id', $admin->church_id)
            ->whereKeyNot($admin->campus_id)
            ->firstOrFail();
        $privateLog = ActivityLog::query()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $otherCampus->id,
            'user_id' => $admin->id,
            'module' => 'Access Control',
            'action' => 'private_cross_campus_action',
            'description' => 'Private cross campus audit event.',
            'properties' => ['risk' => 'high', 'status' => 'success'],
        ]);

        $this->actingAs($viewer)
            ->get(route('audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('audit-logs.index', ['ids' => [$privateLog->id], 'date_range' => 'all']))
            ->assertOk()
            ->assertDontSee('Private cross campus audit event.');
    }

    public function test_non_super_admin_cannot_update_or_clone_super_administrator_role(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $superRole = Role::query()->where('name', 'Super Administrator')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('roles.update', $superRole), [
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('roles.clone', $superRole), [
                'name' => 'Full Access Copy',
                'description' => 'Should not be created.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Full Access Copy']);
        $this->assertTrue($superRole->permissions()->exists());
    }
}
