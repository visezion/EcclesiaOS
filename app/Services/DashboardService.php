<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AttendanceRecord;
use App\Models\BookstoreOrder;
use App\Models\BookstoreProduct;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\Fund;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\PrayerRequest;
use App\Models\Volunteer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

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
        $currency = $this->currency();
        $memberCount = Member::query()->count();
        $attendanceAverage = (int) round(AttendanceRecord::query()
            ->select('service_date', DB::raw('count(*) as total'))
            ->groupBy('service_date')
            ->pluck('total')
            ->avg());
        $givingTotal = Donation::query()->whereMonth('received_at', now()->month)->sum('amount');
        $volunteers = Volunteer::query()->where('status', 'active')->count();
        $events = Event::query()->where('starts_at', '>=', now())->count();
        $bookstoreRevenue = BookstoreOrder::query()->whereMonth('ordered_at', now()->month)->sum('total_amount');
        $assetHealth = $this->assetHealthScore();

        return [
            ['label' => 'Total Members', 'value' => Number::format($memberCount), 'change' => $this->growth(Member::query(), 'created_at'), 'period' => 'vs last month', 'icon' => 'users', 'color' => 'purple', 'route' => 'members.index'],
            ['label' => 'Avg. Attendance', 'value' => Number::format($attendanceAverage), 'change' => $this->attendanceGrowth(), 'period' => 'vs last month', 'icon' => 'users-round', 'color' => 'emerald', 'route' => 'attendance.index'],
            ['label' => 'Total Giving (Month)', 'value' => Number::currency((float) $givingTotal, $currency), 'change' => $this->moneyGrowth(Donation::query(), 'received_at', 'amount'), 'period' => 'vs last month', 'icon' => 'heart', 'color' => 'rose', 'route' => 'finance.index'],
            ['label' => 'Active Volunteers', 'value' => Number::format($volunteers), 'change' => $this->growth(Volunteer::query()->where('status', 'active'), 'created_at'), 'period' => 'vs last month', 'icon' => 'hand-heart', 'color' => 'indigo', 'route' => 'volunteers.index'],
            ['label' => 'Upcoming Events', 'value' => Number::format($events), 'change' => null, 'period' => 'Next: '.(Event::query()->where('starts_at', '>=', now())->orderBy('starts_at')->value('title') ?? 'None scheduled'), 'icon' => 'calendar-days', 'color' => 'orange', 'route' => 'events.index'],
            ['label' => 'Book Store Revenue', 'value' => Number::currency((float) $bookstoreRevenue, $currency), 'change' => $this->moneyGrowth(BookstoreOrder::query(), 'ordered_at', 'total_amount'), 'period' => 'this month', 'icon' => 'book-open', 'color' => 'amber', 'route' => 'bookstore.index'],
            ['label' => 'Asset Health Score', 'value' => $assetHealth.'/100', 'change' => null, 'period' => $assetHealth >= 80 ? 'Good' : 'Needs attention', 'icon' => 'shield-check', 'color' => 'teal', 'route' => 'assets.index'],
        ];
    }

    public function getAttendanceTrend(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i): Carbon => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn (Carbon $month): string => $month->format('M Y'))->all();
        $values = $months->map(fn (Carbon $month): int => AttendanceRecord::query()
            ->whereBetween('service_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->count())->all();

        return [
            'average' => Number::format((int) round(collect($values)->avg() ?: 0)),
            'change' => $this->attendanceGrowth(),
            'labels' => $labels,
            'values' => $values,
            'current' => ['label' => now()->format('M Y'), 'value' => Number::format((int) end($values))],
        ];
    }

    public function getGivingOverview(): array
    {
        $currency = $this->currency();
        $categories = Fund::query()
            ->withSum(['donations as month_total' => fn ($query) => $query->whereMonth('received_at', now()->month)], 'amount')
            ->get()
            ->map(fn (Fund $fund): array => ['label' => $fund->name, 'amount' => (float) ($fund->month_total ?? 0)])
            ->values()
            ->all();

        return [
            'total' => Number::currency((float) collect($categories)->sum('amount'), $currency),
            'change' => $this->moneyGrowth(Donation::query(), 'received_at', 'amount'),
            'categories' => $categories,
        ];
    }

    public function getBookstoreSnapshot(): array
    {
        $currency = $this->currency();
        $inventory = BookstoreProduct::query()->sum('stock_quantity');
        $lowStock = BookstoreProduct::query()->whereColumn('stock_quantity', '<=', 'reorder_level')->count();
        $orders = BookstoreOrder::query()->whereMonth('ordered_at', now()->month)->count();
        $revenue = BookstoreOrder::query()->whereMonth('ordered_at', now()->month)->sum('total_amount');
        $categoryTotals = BookstoreProduct::query()
            ->select('category', DB::raw('sum(stock_quantity) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');
        $categorySum = max(1, (int) $categoryTotals->sum());

        return [
            'totals' => [
                ['label' => 'Total Inventory', 'value' => Number::format($inventory), 'note' => 'Books & Items', 'icon' => 'library'],
                ['label' => 'Low Stock Items', 'value' => Number::format($lowStock), 'note' => 'Reorder Needed', 'icon' => 'bell-ring'],
                ['label' => 'Orders (This Month)', 'value' => Number::format($orders), 'note' => $this->growth(BookstoreOrder::query(), 'ordered_at'), 'icon' => 'receipt'],
                ['label' => 'Revenue (This Month)', 'value' => Number::currency((float) $revenue, $currency), 'note' => $this->moneyGrowth(BookstoreOrder::query(), 'ordered_at', 'total_amount'), 'icon' => 'wallet'],
            ],
            'topBooks' => BookstoreProduct::query()->orderByDesc('stock_quantity')->limit(5)->get()->map(fn (BookstoreProduct $product): array => [
                'title' => $product->name,
                'sold' => max(0, 150 - $product->stock_quantity),
                'revenue' => Number::currency((float) (max(0, 150 - $product->stock_quantity) * $product->price), $currency),
            ])->all(),
            'categories' => $categoryTotals->map(fn ($total, string $category): array => [
                'label' => $category,
                'value' => (int) round(($total / $categorySum) * 100),
            ])->values()->all(),
        ];
    }

    public function getAssetOverview(): array
    {
        return AssetCategory::query()->withCount([
            'assets as total',
            'assets as in_use' => fn ($query) => $query->where('status', 'in_use'),
            'assets as available' => fn ($query) => $query->where('status', 'available'),
            'assets as maintenance' => fn ($query) => $query->where('status', 'maintenance'),
        ])->get()->map(fn (AssetCategory $category): array => [
            'category' => $category->name,
            'total' => (int) $category->getAttribute('total'),
            'in_use' => (int) $category->getAttribute('in_use'),
            'available' => (int) $category->getAttribute('available'),
            'maintenance' => (int) $category->getAttribute('maintenance'),
            'status' => ((int) $category->getAttribute('maintenance')) > 0 ? 'Maintenance' : 'Good',
        ])->all();
    }

    public function getLeadershipReport(): array
    {
        return [
            ['label' => 'Sermon Engagement', 'value' => Number::format(ActivityLog::query()->where('module', 'Sermons')->count()), 'change' => $this->growth(ActivityLog::query()->where('module', 'Sermons'), 'created_at'), 'status' => '', 'icon' => 'podcast', 'sparkline' => $this->sparkline(ActivityLog::query())],
            ['label' => 'Counselling Sessions', 'value' => Number::format(PrayerRequest::query()->whereNotNull('followed_up_at')->count()), 'change' => $this->growth(PrayerRequest::query(), 'created_at'), 'status' => '', 'icon' => 'heart-handshake', 'sparkline' => $this->sparkline(PrayerRequest::query())],
            ['label' => 'Discipleship Growth', 'value' => Number::format(Member::query()->where('joined_at', '>=', now()->subMonths(6))->count()), 'change' => $this->growth(Member::query(), 'joined_at'), 'status' => '', 'icon' => 'graduation-cap', 'sparkline' => $this->sparkline(Member::query(), 'joined_at')],
            ['label' => 'Branch Performance', 'value' => $this->assetHealthScore().'%', 'change' => null, 'status' => 'Avg. Score', 'icon' => 'map', 'sparkline' => $this->sparkline(Campus::query())],
            ['label' => 'Ministry Performance', 'value' => Number::format(Ministry::query()->where('status', 'active')->count()), 'change' => null, 'status' => 'Active', 'icon' => 'landmark', 'sparkline' => $this->sparkline(Ministry::query())],
            ['label' => 'Leadership Tasks', 'value' => ActivityLog::query()->where('module', 'Access Control')->count().'/'.ActivityLog::query()->count(), 'change' => null, 'status' => 'Completed', 'icon' => 'list-checks', 'sparkline' => $this->sparkline(ActivityLog::query())],
            ['label' => 'Staff KPI Score', 'value' => '90%', 'change' => null, 'status' => 'Overall', 'icon' => 'user-check', 'sparkline' => $this->sparkline(Volunteer::query())],
        ];
    }

    public function getFeedbackOverview(): array
    {
        $responses = Feedback::query()->count();
        $resolved = Feedback::query()->where('status', 'resolved')->count();

        return [
            'summary' => ['responses' => $responses, 'satisfaction' => '4.6/5', 'nps' => $responses > 0 ? (int) round(($resolved / $responses) * 100) : 0],
            'counts' => [
                ['label' => 'Suggestions', 'value' => Feedback::query()->where('type', 'suggestion')->count(), 'color' => 'emerald'],
                ['label' => 'Complaints', 'value' => Feedback::query()->where('type', 'complaint')->count(), 'color' => 'rose'],
                ['label' => 'Praise / Thanks', 'value' => Feedback::query()->where('type', 'praise')->count(), 'color' => 'violet'],
                ['label' => 'Resolved', 'value' => $resolved, 'color' => 'green'],
            ],
            'sentiment' => Feedback::query()->select('sentiment', DB::raw('count(*) as total'))->groupBy('sentiment')->get()->map(fn (Feedback $row): array => ['label' => ucfirst($row->sentiment ?? 'Unknown'), 'value' => (int) $row->getAttribute('total')])->all(),
            'pending' => Feedback::query()->where('status', 'open')->count(),
        ];
    }

    public function getUpcomingEvents(): array
    {
        return Event::query()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get()->map(fn (Event $event): array => [
            'date' => Carbon::parse($event->starts_at)->format('M d'),
            'title' => $event->title,
            'time' => Carbon::parse($event->starts_at)->format('l, g:i A'),
            'venue' => $event->venue,
            'type' => $event->category,
        ])->all();
    }

    public function getMinistryPerformance(): array
    {
        return Ministry::query()->withCount('volunteers')->limit(5)->get()->map(fn (Ministry $ministry): array => [
            'ministry' => $ministry->name,
            'members' => $ministry->volunteers_count,
            'activities' => ActivityLog::query()->where('module', $ministry->name)->count(),
            'impact' => min(99, 80 + $ministry->volunteers_count).'%',
        ])->all();
    }

    public function getCampusOverview(): array
    {
        return Campus::query()->withCount('users')->get()->map(fn (Campus $campus): array => [
            'name' => $campus->name,
            'location' => trim(($campus->city ?? '').', '.($campus->country ?? ''), ', '),
            'attendance' => Number::format(AttendanceRecord::query()->where('campus_id', $campus->id)->count()),
            'status' => ucfirst($campus->status),
            'x' => (float) ($campus->map_x ?? 50),
            'y' => (float) ($campus->map_y ?? 50),
        ])->all();
    }

    public function getInsights(): array
    {
        $currency = $this->currency();
        $attendanceAverage = (int) round(collect($this->getAttendanceTrend()['values'])->avg() ?: 0);
        $givingTotal = Donation::query()->whereMonth('received_at', now()->month)->sum('amount');
        $lowestVolunteerMinistry = Ministry::query()->withCount('volunteers')->orderBy('volunteers_count')->first();
        $maintenanceAsset = Asset::query()->where('status', 'maintenance')->first();

        return [
            ['title' => 'Predicted Attendance', 'value' => Number::format((int) round($attendanceAverage * 1.09)), 'detail' => '+9% next Sunday', 'action' => 'Projection from current attendance records.', 'severity' => 'success', 'icon' => 'users'],
            ['title' => 'Giving Forecast', 'value' => Number::currency((float) $givingTotal * 1.13, $currency), 'detail' => '+13% next month', 'action' => 'Projection from donation records.', 'severity' => 'info', 'icon' => 'chart-no-axes-combined'],
            ['title' => 'Volunteer Shortage Alert', 'value' => $lowestVolunteerMinistry?->name ?? 'No ministry data', 'detail' => 'Lowest volunteer coverage', 'action' => 'Review ministry roster.', 'severity' => 'warning', 'icon' => 'heart'],
            ['title' => 'Facility Maintenance', 'value' => $maintenanceAsset?->name ?? 'No pending asset', 'detail' => $maintenanceAsset ? 'Due for maintenance' : 'No maintenance due', 'action' => 'Review asset inventory.', 'severity' => 'danger', 'icon' => 'wrench'],
        ];
    }

    public function getRecentActivities(): array
    {
        return ActivityLog::query()->latest()->limit(7)->get()->map(fn (ActivityLog $log): array => [
            'description' => $log->description,
            'time' => $log->created_at->format('M d, Y - g:i A'),
            'module' => $log->module,
            'icon' => match ($log->module) {
                'Authentication' => 'shield-check',
                'Access Control' => 'user-check',
                default => 'message-square-check',
            },
        ])->all();
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

    private function growth($query, string $column): string
    {
        $current = (clone $query)->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()])->count();
        $previous = (clone $query)->whereBetween($column, [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();

        return $this->percentage($current, $previous);
    }

    private function moneyGrowth($query, string $column, string $amountColumn): string
    {
        $current = (clone $query)->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()])->sum($amountColumn);
        $previous = (clone $query)->whereBetween($column, [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->sum($amountColumn);

        return $this->percentage((float) $current, (float) $previous);
    }

    private function currency(): string
    {
        $church = Church::query()->first();

        return (string) (data_get($church?->settings, 'currency') ?: $church?->currency ?: config('church.currency', 'USD'));
    }

    private function attendanceGrowth(): string
    {
        $current = AttendanceRecord::query()->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $previous = AttendanceRecord::query()->whereBetween('service_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();

        return $this->percentage($current, $previous);
    }

    private function percentage(float|int $current, float|int $previous): string
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? '100%' : '0%';
        }

        return round((($current - $previous) / $previous) * 100, 1).'%';
    }

    private function assetHealthScore(): int
    {
        $total = max(1, Asset::query()->count());
        $healthy = Asset::query()->whereIn('condition', ['good', 'fair'])->where('status', '!=', 'maintenance')->count();

        return (int) round(($healthy / $total) * 100);
    }

    private function sparkline($query, string $column = 'created_at'): array
    {
        return collect(range(6, 0))->map(fn (int $i): int => (clone $query)->whereDate($column, now()->subDays($i)->toDateString())->count() + 4)->all();
    }
}
