<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_coming_soon_pages_return_http_200(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Members')
            ->assertSee('This module is currently under development');
    }
}
