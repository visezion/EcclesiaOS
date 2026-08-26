<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AiCopilotPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_copilot_requires_its_dedicated_permission(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $manageMembers = Permission::query()->where('name', 'manage members')->firstOrFail();
        $useCopilot = Permission::query()->where('name', 'use ai copilot')->firstOrFail();
        $role = Role::query()->create([
            'name' => 'Members Without Copilot',
            'slug' => 'members-without-copilot',
            'description' => 'Confirms unrelated module permissions do not grant Copilot access.',
        ]);
        $role->permissions()->attach($manageMembers);
        $user = User::factory()->create([
            'church_id' => $administrator->church_id,
            'campus_id' => $administrator->campus_id,
            'email' => 'copilot.permission@example.test',
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('ai-copilot.index'))
            ->assertForbidden();

        $role->permissions()->attach($useCopilot);
        $user->unsetRelation('roles');

        $this->actingAs($user)
            ->get(route('ai-copilot.index'))
            ->assertOk()
            ->assertSee('AI Reports &amp; Analytics Copilot', false);
    }

    public function test_production_permission_migration_preserves_existing_copilot_access(): void
    {
        $this->seed();
        $manageMembers = Permission::query()->where('name', 'manage members')->firstOrFail();
        $useCopilot = Permission::query()->where('name', 'use ai copilot')->firstOrFail();
        $role = Role::query()->create([
            'name' => 'Legacy Copilot Role',
            'slug' => 'legacy-copilot-role',
            'description' => 'Previously received Copilot access through a module permission.',
        ]);
        $role->permissions()->attach($manageMembers);
        $role->permissions()->detach($useCopilot);

        $migration = require database_path('migrations/2026_08_27_000001_add_ai_copilot_permission.php');
        $migration->up();

        $this->assertDatabaseHas('permissions', [
            'name' => 'use ai copilot',
            'slug' => 'use-ai-copilot',
        ]);
        $this->assertTrue($role->refresh()->permissions()->whereKey($useCopilot->id)->exists());
    }
}
