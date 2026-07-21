<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_bulk_user_actions_update_selected_users(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('users.bulk'), [
                'action' => 'suspend',
                'users' => [$user->id],
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
}
