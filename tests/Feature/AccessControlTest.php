<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
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
            ->assertSee('Authentication')
            ->assertSee('Role Permission Matrix');
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
}
