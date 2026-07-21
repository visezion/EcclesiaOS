<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_middleware_blocks_unauthorised_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.only'))
            ->assertForbidden();
    }
}
