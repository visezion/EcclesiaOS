<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_for_authenticated_users(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Members')
            ->assertSee('dashboard-stat-grid', false)
            ->assertDontSee('repeat(auto-fit, minmax(220px, 1fr))', false)
            ->assertSee('Attendance Trend')
            ->assertSee('AI Insights &amp; Smart Recommendations', false);
    }

    public function test_dashboard_hides_information_for_disabled_modules(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $church = Church::query()->firstOrFail();

        $church->forceFill([
            'settings' => array_merge($church->settings ?? [], [
                'disabled_modules' => ['finance.index', 'assets.index', 'reports.index'],
            ]),
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Attendance Trend')
            ->assertSee('--dashboard-span: 6', false)
            ->assertDontSee('Total Giving')
            ->assertDontSee('Giving Overview')
            ->assertDontSee('Asset Health Score')
            ->assertDontSee('Asset Inventory Overview')
            ->assertDontSee('AI Insights &amp; Smart Recommendations', false)
            ->assertDontSee(route('finance.index'), false)
            ->assertDontSee(route('assets.index'), false)
            ->assertDontSee(route('reports.index'), false);
    }

    public function test_active_navigation_state_is_rendered(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }
}
