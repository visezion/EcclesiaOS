<?php

namespace Tests\Unit;

use App\Models\Member;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_service_returns_expected_structure(): void
    {
        $this->seed();

        $data = (new DashboardService)->getDashboardData();

        $this->assertArrayHasKey('summaryMetrics', $data);
        $this->assertArrayHasKey('attendanceTrend', $data);
        $this->assertArrayHasKey('givingOverview', $data);
        $this->assertArrayHasKey('quickActions', $data);
        $this->assertCount(7, $data['summaryMetrics']);
        $this->assertNotEmpty($data['attendanceTrend']['values']);
        $this->assertSame(number_format(Member::query()->count()), $data['summaryMetrics'][0]['value']);
    }
}
