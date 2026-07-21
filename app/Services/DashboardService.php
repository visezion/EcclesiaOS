<?php

declare(strict_types=1);

namespace App\Services;

final class DashboardService
{
    public function getDashboardData(): array
    {
        return [
            'summaryMetrics' => $this->getSummaryMetrics(),
            'attendanceTrend' => $this->getAttendanceTrend(),
            'givingOverview' => $this->getGivingOverview(),
            'bookstore' => $this->getBookstoreSnapshot(),
            'assets' => $this->getAssetOverview(),
            'leadership' => $this->getLeadershipReport(),
            'feedback' => $this->getFeedbackOverview(),
            'events' => $this->getUpcomingEvents(),
            'ministries' => $this->getMinistryPerformance(),
            'campuses' => $this->getCampusOverview(),
            'insights' => $this->getInsights(),
            'activities' => $this->getRecentActivities(),
            'quickActions' => $this->getQuickActions(),
        ];
    }

    public function getSummaryMetrics(): array
    {
        return [
            ['label' => 'Total Members', 'value' => '5,248', 'change' => '8.6%', 'period' => 'vs last month', 'icon' => 'users', 'color' => 'purple', 'route' => 'members.index'],
            ['label' => 'Avg. Attendance', 'value' => '1,873', 'change' => '6.3%', 'period' => 'vs last month', 'icon' => 'users-round', 'color' => 'emerald', 'route' => 'attendance.index'],
            ['label' => 'Total Giving (May)', 'value' => '$128,750', 'change' => '12.4%', 'period' => 'vs last month', 'icon' => 'heart', 'color' => 'rose', 'route' => 'finance.index'],
            ['label' => 'Active Volunteers', 'value' => '362', 'change' => '9.1%', 'period' => 'vs last month', 'icon' => 'hand-heart', 'color' => 'indigo', 'route' => 'volunteers.index'],
            ['label' => 'Upcoming Events', 'value' => '14', 'change' => null, 'period' => 'Next: Youth Camp', 'icon' => 'calendar-days', 'color' => 'orange', 'route' => 'events.index'],
            ['label' => 'Book Store Revenue', 'value' => '$8,420', 'change' => '15.7%', 'period' => 'this month', 'icon' => 'book-open', 'color' => 'amber', 'route' => 'bookstore.index'],
            ['label' => 'Asset Health Score', 'value' => '82/100', 'change' => null, 'period' => 'Good', 'icon' => 'shield-check', 'color' => 'teal', 'route' => 'assets.index'],
        ];
    }

    public function getAttendanceTrend(): array
    {
        return [
            'average' => '1,873',
            'change' => '6.3%',
            'labels' => ['Dec 2023', 'Jan 2024', 'Feb 2024', 'Mar 2024', 'Apr 2024', 'May 2024'],
            'values' => [1480, 1720, 2085, 2140, 2380, 2390],
            'current' => ['label' => 'May 2024', 'value' => '1,873'],
        ];
    }

    public function getGivingOverview(): array
    {
        return [
            'total' => '$128,750',
            'change' => '12.4%',
            'categories' => [
                ['label' => 'Tithes', 'amount' => 62300],
                ['label' => 'Offerings', 'amount' => 32490],
                ['label' => 'Building Fund', 'amount' => 20000],
                ['label' => 'Missions', 'amount' => 8750],
                ['label' => 'Other', 'amount' => 5250],
            ],
        ];
    }

    public function getBookstoreSnapshot(): array
    {
        return [
            'totals' => [
                ['label' => 'Total Inventory', 'value' => '1,245', 'note' => 'Books & Items', 'icon' => 'library'],
                ['label' => 'Low Stock Items', 'value' => '28', 'note' => 'Reorder Needed', 'icon' => 'bell-ring'],
                ['label' => 'Orders (This Month)', 'value' => '156', 'note' => '+14.3%', 'icon' => 'receipt'],
                ['label' => 'Revenue (This Month)', 'value' => '$8,420', 'note' => '+15.7%', 'icon' => 'wallet'],
            ],
            'topBooks' => [
                ['title' => 'Destined for Impact', 'sold' => 122, 'revenue' => '$1,220'],
                ['title' => 'Walking in Faith', 'sold' => 98, 'revenue' => '$980'],
                ['title' => 'Prayers that Avail Much', 'sold' => 85, 'revenue' => '$850'],
                ['title' => 'Grace for Today', 'sold' => 74, 'revenue' => '$740'],
                ['title' => 'Victory in Worship', 'sold' => 65, 'revenue' => '$650'],
            ],
            'categories' => [
                ['label' => 'Books', 'value' => 60],
                ['label' => 'Music', 'value' => 20],
                ['label' => 'Merchandise', 'value' => 12],
                ['label' => 'Accessories', 'value' => 8],
            ],
        ];
    }

    public function getAssetOverview(): array
    {
        return [
            ['category' => 'Chairs', 'total' => 1250, 'in_use' => 980, 'available' => 220, 'maintenance' => 12, 'status' => 'Good'],
            ['category' => 'Microphones', 'total' => 48, 'in_use' => 36, 'available' => 10, 'maintenance' => 2, 'status' => 'Good'],
            ['category' => 'Cameras', 'total' => 16, 'in_use' => 12, 'available' => 3, 'maintenance' => 1, 'status' => 'Fair'],
            ['category' => 'Projectors', 'total' => 14, 'in_use' => 10, 'available' => 3, 'maintenance' => 1, 'status' => 'Good'],
            ['category' => 'Laptops', 'total' => 32, 'in_use' => 20, 'available' => 10, 'maintenance' => 2, 'status' => 'Good'],
            ['category' => 'Musical Instruments', 'total' => 29, 'in_use' => 18, 'available' => 8, 'maintenance' => 3, 'status' => 'Fair'],
            ['category' => 'Vehicles', 'total' => 7, 'in_use' => 5, 'available' => 1, 'maintenance' => 1, 'status' => 'Good'],
            ['category' => 'Generators', 'total' => 4, 'in_use' => 3, 'available' => 0, 'maintenance' => 1, 'status' => 'Maintenance'],
        ];
    }

    public function getLeadershipReport(): array
    {
        return [
            ['label' => 'Sermon Engagement', 'value' => '1,248', 'change' => '11.2%', 'status' => '', 'icon' => 'podcast', 'sparkline' => [14, 18, 16, 22, 17, 19, 24]],
            ['label' => 'Counselling Sessions', 'value' => '46', 'change' => '9.5%', 'status' => '', 'icon' => 'heart-handshake', 'sparkline' => [8, 10, 9, 13, 12, 14, 13]],
            ['label' => 'Discipleship Growth', 'value' => '87', 'change' => '13.6%', 'status' => '', 'icon' => 'graduation-cap', 'sparkline' => [9, 12, 11, 14, 12, 15, 16]],
            ['label' => 'Branch Performance', 'value' => '92%', 'change' => null, 'status' => 'Avg. Score', 'icon' => 'map', 'sparkline' => [18, 17, 21, 19, 22, 18, 20]],
            ['label' => 'Ministry Performance', 'value' => '88%', 'change' => null, 'status' => 'Avg. Score', 'icon' => 'landmark', 'sparkline' => [12, 15, 13, 16, 14, 17, 16]],
            ['label' => 'Leadership Tasks', 'value' => '28/36', 'change' => null, 'status' => 'Completed', 'icon' => 'list-checks', 'sparkline' => [5, 8, 6, 9, 7, 10, 8]],
            ['label' => 'Staff KPI Score', 'value' => '90%', 'change' => null, 'status' => 'Overall', 'icon' => 'user-check', 'sparkline' => [20, 22, 19, 23, 21, 18, 24]],
        ];
    }

    public function getFeedbackOverview(): array
    {
        return [
            'summary' => ['responses' => 324, 'satisfaction' => '4.6/5', 'nps' => 78],
            'counts' => [
                ['label' => 'Suggestions', 'value' => 58, 'color' => 'emerald'],
                ['label' => 'Complaints', 'value' => 12, 'color' => 'rose'],
                ['label' => 'Praise / Thanks', 'value' => 124, 'color' => 'violet'],
                ['label' => 'Resolved', 'value' => 42, 'color' => 'green'],
            ],
            'sentiment' => [
                ['label' => 'Positive', 'value' => 72],
                ['label' => 'Neutral', 'value' => 20],
                ['label' => 'Negative', 'value' => 8],
            ],
            'pending' => 18,
        ];
    }

    public function getUpcomingEvents(): array
    {
        return [
            ['date' => 'May 26', 'title' => 'Sunday Worship Service', 'time' => 'Sunday, 9:00 AM', 'venue' => 'Main Sanctuary', 'type' => 'Service'],
            ['date' => 'May 29', 'title' => 'Youth Night', 'time' => 'Wednesday, 7:00 PM', 'venue' => 'Youth Center', 'type' => 'Youth'],
            ['date' => 'Jun 02', 'title' => 'Communion Sunday', 'time' => 'Sunday, 9:00 AM', 'venue' => 'Main Sanctuary', 'type' => 'Service'],
            ['date' => 'Jun 07', 'title' => "Women's Fellowship", 'time' => 'Friday, 6:00 PM', 'venue' => 'Fellowship Hall', 'type' => 'Fellowship'],
            ['date' => 'Jun 15', 'title' => 'Youth Camp 2024', 'time' => 'Jun 15 - Jun 20', 'venue' => 'Camp Glory', 'type' => 'Event'],
        ];
    }

    public function getMinistryPerformance(): array
    {
        return [
            ['ministry' => 'Worship Ministry', 'members' => 126, 'activities' => 8, 'impact' => '95%'],
            ['ministry' => "Children's Ministry", 'members' => 98, 'activities' => 12, 'impact' => '92%'],
            ['ministry' => 'Youth Ministry', 'members' => 110, 'activities' => 10, 'impact' => '90%'],
            ['ministry' => 'Outreach Ministry', 'members' => 85, 'activities' => 9, 'impact' => '88%'],
            ['ministry' => 'Prayer Ministry', 'members' => 76, 'activities' => 14, 'impact' => '93%'],
        ];
    }

    public function getCampusOverview(): array
    {
        return [
            ['name' => 'Headquarters', 'location' => 'Lagos, Nigeria', 'attendance' => '1,873', 'status' => 'Active', 'x' => 46, 'y' => 54],
            ['name' => 'Abuja Campus', 'location' => 'Abuja, Nigeria', 'attendance' => '843', 'status' => 'Active', 'x' => 48, 'y' => 48],
            ['name' => 'PH Branch', 'location' => 'Port Harcourt', 'attendance' => '612', 'status' => 'Active', 'x' => 49, 'y' => 59],
            ['name' => 'Kano Branch', 'location' => 'Kano, Nigeria', 'attendance' => '455', 'status' => 'Active', 'x' => 47, 'y' => 42],
            ['name' => 'Dubai Branch', 'location' => 'Dubai, UAE', 'attendance' => '320', 'status' => 'Active', 'x' => 64, 'y' => 42],
        ];
    }

    public function getInsights(): array
    {
        return [
            ['title' => 'Predicted Attendance', 'value' => '2,050', 'detail' => '+9% next Sunday', 'action' => 'Sample forecast until analytics engine is connected.', 'severity' => 'success', 'icon' => 'users'],
            ['title' => 'Giving Forecast', 'value' => '$146,000', 'detail' => '+13% next month', 'action' => 'Sample forecast based on demonstration data.', 'severity' => 'info', 'icon' => 'chart-no-axes-combined'],
            ['title' => 'Volunteer Shortage Alert', 'value' => "Children's Ministry", 'detail' => 'Need 6 more volunteers', 'action' => 'Review roster when volunteer workflows are live.', 'severity' => 'warning', 'icon' => 'heart'],
            ['title' => 'Facility Maintenance', 'value' => 'Projector in Main Hall', 'detail' => 'Due for maintenance', 'action' => 'Create a facility ticket after module activation.', 'severity' => 'danger', 'icon' => 'wrench'],
        ];
    }

    public function getRecentActivities(): array
    {
        return [
            ['description' => 'New member registered: Sarah Johnson', 'time' => 'May 19, 2024 - 10:30 AM', 'module' => 'Members', 'icon' => 'user-plus'],
            ['description' => 'Donation received: $500.00 from Michael Thompson', 'time' => 'May 19, 2024 - 9:45 AM', 'module' => 'Finance', 'icon' => 'badge-dollar-sign'],
            ['description' => 'Prayer request added: Healing for Mary Johnson', 'time' => 'May 19, 2024 - 8:20 AM', 'module' => 'Prayer', 'icon' => 'hand-heart'],
            ['description' => 'Event created: Youth Camp 2024', 'time' => 'May 18, 2024 - 4:10 PM', 'module' => 'Events', 'icon' => 'calendar-plus'],
            ['description' => 'Asset added: 2 New Microphones', 'time' => 'May 18, 2024 - 2:30 PM', 'module' => 'Assets', 'icon' => 'package-plus'],
            ['description' => 'Volunteer assigned to Sunday Worship', 'time' => 'May 18, 2024 - 1:20 PM', 'module' => 'Volunteers', 'icon' => 'handshake'],
            ['description' => 'Feedback resolved: Parking request', 'time' => 'May 17, 2024 - 5:10 PM', 'module' => 'Feedback', 'icon' => 'message-square-check'],
        ];
    }

    public function getQuickActions(): array
    {
        return [
            ['label' => 'Add Member', 'route' => 'members.index', 'icon' => 'user-plus', 'color' => 'purple'],
            ['label' => 'Record Attendance', 'route' => 'attendance.index', 'icon' => 'clipboard-check', 'color' => 'blue'],
            ['label' => 'Create Event', 'route' => 'events.index', 'icon' => 'calendar-plus', 'color' => 'emerald'],
            ['label' => 'Add Asset', 'route' => 'assets.index', 'icon' => 'package-plus', 'color' => 'orange'],
            ['label' => 'Add Book', 'route' => 'bookstore.index', 'icon' => 'book-plus', 'color' => 'violet'],
            ['label' => 'Send Message', 'route' => 'communications.index', 'icon' => 'send', 'color' => 'sky'],
            ['label' => 'Review Feedback', 'route' => 'feedback.index', 'icon' => 'message-circle-heart', 'color' => 'rose'],
            ['label' => 'Generate Report', 'route' => 'reports.index', 'icon' => 'file-chart-column', 'color' => 'teal'],
        ];
    }
}
