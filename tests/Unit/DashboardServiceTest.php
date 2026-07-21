<?php

namespace Tests\Unit;

use App\Services\DashboardService;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    public function test_dashboard_service_returns_expected_structure(): void
    {
        $data = (new DashboardService)->getDashboardData();

        $this->assertArrayHasKey('summaryMetrics', $data);
        $this->assertArrayHasKey('attendanceTrend', $data);
        $this->assertArrayHasKey('givingOverview', $data);
        $this->assertArrayHasKey('quickActions', $data);
        $this->assertCount(7, $data['summaryMetrics']);
        $this->assertNotEmpty($data['attendanceTrend']['values']);
    }
}
