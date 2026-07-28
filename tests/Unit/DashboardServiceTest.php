<?php

namespace Tests\Unit;

use App\Models\Member;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_service_returns_expected_structure(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $data = (new DashboardService)->forUser($admin)->getDashboardData();

        $this->assertArrayHasKey('summaryMetrics', $data);
        $this->assertArrayHasKey('attendanceTrend', $data);
        $this->assertArrayHasKey('givingOverview', $data);
        $this->assertArrayHasKey('quickActions', $data);
        $this->assertArrayHasKey('dashboardSections', $data);
        $this->assertCount(7, $data['summaryMetrics']);
        $this->assertNotEmpty($data['attendanceTrend']['values']);
        $this->assertSame(number_format(Member::query()->count()), $data['summaryMetrics'][0]['value']);
    }

    public function test_dashboard_hides_finance_totals_for_ministry_finance_users(): void
    {
        $this->seed();

        $leader = User::query()->where('email', 'emily.davis@klgc.org')->firstOrFail();
        $data = (new DashboardService)->forUser($leader)->getDashboardData();

        $this->assertFalse($data['dashboardSections']['giving']);
        $this->assertFalse(collect($data['summaryMetrics'])->contains('label', 'Total Giving (Month)'));
    }
}
