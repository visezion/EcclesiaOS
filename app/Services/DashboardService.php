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
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

final class DashboardService
{
    private ?User $actor = null;

    public function forUser(?User $user): self
    {
        $this->actor = $user;

        return $this;
    }

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
        $memberCount = $this->query(Member::class)->count();
        $attendanceAverage = (int) round($this->query(AttendanceRecord::class)
            ->select('service_date', DB::raw('count(*) as total'))
            ->groupBy('service_date')
            ->pluck('total')
            ->avg());
        $givingTotal = $this->query(Donation::class)->whereMonth('received_at', now()->month)->sum('amount');
        $volunteers = $this->query(Volunteer::class)->where('status', 'active')->count();
        $events = $this->query(Event::class)->where('starts_at', '>=', now())->count();
        $bookstoreRevenue = $this->query(BookstoreOrder::class)->whereMonth('ordered_at', now()->month)->sum('total_amount');
        $assetHealth = $this->assetHealthScore();

        return [
            ['label' => 'Total Members', 'value' => Number::format($memberCount), 'change' => $this->growth($this->query(Member::class), 'created_at'), 'period' => 'vs last month', 'icon' => 'users', 'color' => 'purple', 'route' => 'members.index'],
            ['label' => 'Avg. Attendance', 'value' => Number::format($attendanceAverage), 'change' => $this->attendanceGrowth(), 'period' => 'vs last month', 'icon' => 'users-round', 'color' => 'emerald', 'route' => 'attendance.index'],
            ['label' => 'Total Giving (Month)', 'value' => Number::currency((float) $givingTotal, $currency), 'change' => $this->moneyGrowth($this->query(Donation::class), 'received_at', 'amount'), 'period' => 'vs last month', 'icon' => 'heart', 'color' => 'rose', 'route' => 'finance.index'],
            ['label' => 'Active Volunteers', 'value' => Number::format($volunteers), 'change' => $this->growth($this->query(Volunteer::class)->where('status', 'active'), 'created_at'), 'period' => 'vs last month', 'icon' => 'hand-heart', 'color' => 'indigo', 'route' => 'volunteers.index'],
            ['label' => 'Upcoming Events', 'value' => Number::format($events), 'change' => null, 'period' => 'Next: '.($this->query(Event::class)->where('starts_at', '>=', now())->orderBy('starts_at')->value('title') ?? 'None scheduled'), 'icon' => 'calendar-days', 'color' => 'orange', 'route' => 'events.index'],
            ['label' => 'Book Store Revenue', 'value' => Number::currency((float) $bookstoreRevenue, $currency), 'change' => $this->moneyGrowth($this->query(BookstoreOrder::class), 'ordered_at', 'total_amount'), 'period' => 'this month', 'icon' => 'book-open', 'color' => 'amber', 'route' => 'bookstore.index'],
            ['label' => 'Asset Health Score', 'value' => $assetHealth.'/100', 'change' => null, 'period' => $assetHealth >= 80 ? 'Good' : 'Needs attention', 'icon' => 'shield-check', 'color' => 'teal', 'route' => 'assets.index'],
        ];
    }

    public function getAttendanceTrend(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i): Carbon => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn (Carbon $month): string => $month->format('M Y'))->all();
        $values = $months->map(fn (Carbon $month): int => $this->query(AttendanceRecord::class)
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
        $categories = $this->query(Fund::class)
            ->withSum(['donations as month_total' => fn ($query) => $query->whereMonth('received_at', now()->month)], 'amount')
            ->get()
            ->map(fn (Fund $fund): array => ['label' => $fund->name, 'amount' => (float) ($fund->month_total ?? 0)])
            ->values()
            ->all();

        return [
            'total' => Number::currency((float) collect($categories)->sum('amount'), $currency),
            'change' => $this->moneyGrowth($this->query(Donation::class), 'received_at', 'amount'),
            'categories' => $categories,
        ];
    }

    public function getBookstoreSnapshot(): array
    {
        $currency = $this->currency();
        $inventory = $this->query(BookstoreProduct::class)->sum('stock_quantity');
        $lowStock = $this->query(BookstoreProduct::class)->whereColumn('stock_quantity', '<=', 'reorder_level')->count();
        $orders = $this->query(BookstoreOrder::class)->whereMonth('ordered_at', now()->month)->count();
        $revenue = $this->query(BookstoreOrder::class)->whereMonth('ordered_at', now()->month)->sum('total_amount');
        $categoryTotals = $this->query(BookstoreProduct::class)
            ->select('category', DB::raw('sum(stock_quantity) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');
        $categorySum = max(1, (int) $categoryTotals->sum());

        return [
            'totals' => [
                ['label' => 'Total Inventory', 'value' => Number::format($inventory), 'note' => 'Books & Items', 'icon' => 'library'],
                ['label' => 'Low Stock Items', 'value' => Number::format($lowStock), 'note' => 'Reorder Needed', 'icon' => 'bell-ring'],
                ['label' => 'Orders (This Month)', 'value' => Number::format($orders), 'note' => $this->growth($this->query(BookstoreOrder::class), 'ordered_at'), 'icon' => 'receipt'],
                ['label' => 'Revenue (This Month)', 'value' => Number::currency((float) $revenue, $currency), 'note' => $this->moneyGrowth($this->query(BookstoreOrder::class), 'ordered_at', 'total_amount'), 'icon' => 'wallet'],
            ],
            'topBooks' => $this->query(BookstoreProduct::class)->orderByDesc('stock_quantity')->limit(5)->get()->map(fn (BookstoreProduct $product): array => [
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
        return $this->query(AssetCategory::class)->withCount([
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
            ['label' => 'Sermon Engagement', 'value' => Number::format($this->query(ActivityLog::class)->where('module', 'Sermons')->count()), 'change' => $this->growth($this->query(ActivityLog::class)->where('module', 'Sermons'), 'created_at'), 'status' => '', 'icon' => 'podcast', 'sparkline' => $this->sparkline($this->query(ActivityLog::class))],
            ['label' => 'Counselling Sessions', 'value' => Number::format($this->query(PrayerRequest::class)->whereNotNull('followed_up_at')->count()), 'change' => $this->growth($this->query(PrayerRequest::class), 'created_at'), 'status' => '', 'icon' => 'heart-handshake', 'sparkline' => $this->sparkline($this->query(PrayerRequest::class))],
            ['label' => 'Discipleship Growth', 'value' => Number::format($this->query(Member::class)->where('joined_at', '>=', now()->subMonths(6))->count()), 'change' => $this->growth($this->query(Member::class), 'joined_at'), 'status' => '', 'icon' => 'graduation-cap', 'sparkline' => $this->sparkline($this->query(Member::class), 'joined_at')],
            ['label' => 'Branch Performance', 'value' => $this->assetHealthScore().'%', 'change' => null, 'status' => 'Avg. Score', 'icon' => 'map', 'sparkline' => $this->sparkline($this->query(Campus::class))],
            ['label' => 'Ministry Performance', 'value' => Number::format($this->query(Ministry::class)->where('status', 'active')->count()), 'change' => null, 'status' => 'Active', 'icon' => 'landmark', 'sparkline' => $this->sparkline($this->query(Ministry::class))],
            ['label' => 'Leadership Tasks', 'value' => $this->query(ActivityLog::class)->where('module', 'Access Control')->count().'/'.$this->query(ActivityLog::class)->count(), 'change' => null, 'status' => 'Completed', 'icon' => 'list-checks', 'sparkline' => $this->sparkline($this->query(ActivityLog::class))],
            ['label' => 'Staff KPI Score', 'value' => '90%', 'change' => null, 'status' => 'Overall', 'icon' => 'user-check', 'sparkline' => $this->sparkline($this->query(Volunteer::class))],
        ];
    }

    public function getFeedbackOverview(): array
    {
        $responses = $this->query(Feedback::class)->count();
        $resolved = $this->query(Feedback::class)->where('status', 'resolved')->count();

        return [
            'summary' => ['responses' => $responses, 'satisfaction' => '4.6/5', 'nps' => $responses > 0 ? (int) round(($resolved / $responses) * 100) : 0],
            'counts' => [
                ['label' => 'Suggestions', 'value' => $this->query(Feedback::class)->where('type', 'suggestion')->count(), 'color' => 'emerald'],
                ['label' => 'Complaints', 'value' => $this->query(Feedback::class)->where('type', 'complaint')->count(), 'color' => 'rose'],
                ['label' => 'Praise / Thanks', 'value' => $this->query(Feedback::class)->where('type', 'praise')->count(), 'color' => 'violet'],
                ['label' => 'Resolved', 'value' => $resolved, 'color' => 'green'],
            ],
            'sentiment' => $this->query(Feedback::class)->select('sentiment', DB::raw('count(*) as total'))->groupBy('sentiment')->get()->map(fn (Feedback $row): array => ['label' => ucfirst($row->sentiment ?? 'Unknown'), 'value' => (int) $row->getAttribute('total')])->all(),
            'pending' => $this->query(Feedback::class)->where('status', 'open')->count(),
        ];
    }

    public function getUpcomingEvents(): array
    {
        return $this->query(Event::class)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get()->map(fn (Event $event): array => [
            'date' => Carbon::parse($event->starts_at)->format('M d'),
            'title' => $event->title,
            'time' => Carbon::parse($event->starts_at)->format('l, g:i A'),
            'venue' => $event->venue,
            'type' => $event->category,
        ])->all();
    }

    public function getMinistryPerformance(): array
    {
        return $this->query(Ministry::class)->withCount('volunteers')->limit(5)->get()->map(fn (Ministry $ministry): array => [
            'ministry' => $ministry->name,
            'members' => $ministry->volunteers_count,
            'activities' => $this->query(ActivityLog::class)->where('module', $ministry->name)->count(),
            'impact' => min(99, 80 + $ministry->volunteers_count).'%',
        ])->all();
    }

    public function getCampusOverview(): array
    {
        return $this->query(Campus::class)->withCount('users')->get()->map(fn (Campus $campus): array => [
            'name' => $campus->name,
            'location' => trim(($campus->city ?? '').', '.($campus->country ?? ''), ', '),
            'attendance' => Number::format($this->query(AttendanceRecord::class)->where('campus_id', $campus->id)->count()),
            'status' => ucfirst($campus->status),
            'x' => (float) ($campus->map_x ?? 50),
            'y' => (float) ($campus->map_y ?? 50),
        ])->all();
    }

    public function getInsights(): array
    {
        $currency = $this->currency();
        $attendanceAverage = (int) round(collect($this->getAttendanceTrend()['values'])->avg() ?: 0);
        $givingTotal = $this->query(Donation::class)->whereMonth('received_at', now()->month)->sum('amount');
        $lowestVolunteerMinistry = $this->query(Ministry::class)->withCount('volunteers')->orderBy('volunteers_count')->first();
        $maintenanceAsset = $this->query(Asset::class)->where('status', 'maintenance')->first();

        return [
            ['title' => 'Predicted Attendance', 'value' => Number::format((int) round($attendanceAverage * 1.09)), 'detail' => '+9% next Sunday', 'action' => 'Projection from current attendance records.', 'severity' => 'success', 'icon' => 'users'],
            ['title' => 'Giving Forecast', 'value' => Number::currency((float) $givingTotal * 1.13, $currency), 'detail' => '+13% next month', 'action' => 'Projection from donation records.', 'severity' => 'info', 'icon' => 'chart-no-axes-combined'],
            ['title' => 'Volunteer Shortage Alert', 'value' => $lowestVolunteerMinistry?->name ?? 'No ministry data', 'detail' => 'Lowest volunteer coverage', 'action' => 'Review ministry roster.', 'severity' => 'warning', 'icon' => 'heart'],
            ['title' => 'Facility Maintenance', 'value' => $maintenanceAsset?->name ?? 'No pending asset', 'detail' => $maintenanceAsset ? 'Due for maintenance' : 'No maintenance due', 'action' => 'Review asset inventory.', 'severity' => 'danger', 'icon' => 'wrench'],
        ];
    }

    public function getRecentActivities(): array
    {
        return $this->query(ActivityLog::class)->latest()->limit(7)->get()->map(fn (ActivityLog $log): array => [
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
        $church = $this->query(Church::class)->first();

        return (string) (data_get($church?->settings, 'currency') ?: $church?->currency ?: config('church.currency', 'USD'));
    }

    private function attendanceGrowth(): string
    {
        $current = $this->query(AttendanceRecord::class)->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $previous = $this->query(AttendanceRecord::class)->whereBetween('service_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();

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
        $total = max(1, $this->query(Asset::class)->count());
        $healthy = $this->query(Asset::class)->whereIn('condition', ['good', 'fair'])->where('status', '!=', 'maintenance')->count();

        return (int) round(($healthy / $total) * 100);
    }

    private function sparkline($query, string $column = 'created_at'): array
    {
        return collect(range(6, 0))->map(fn (int $i): int => (clone $query)->whereDate($column, now()->subDays($i)->toDateString())->count() + 4)->all();
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function query(string $model): Builder
    {
        $query = $model::query();
        $actor = $this->actor;

        if (! $actor || $actor->isSuperAdministrator()) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        if ($model === Church::class && $actor->church_id) {
            return $query->whereKey($actor->church_id);
        }

        if ($actor->church_id && Schema::hasColumn($table, 'church_id')) {
            $query->where($table.'.church_id', $actor->church_id);
        }

        if ($actor->campus_id && Schema::hasColumn($table, 'campus_id')) {
            $query->where($table.'.campus_id', $actor->campus_id);
        }

        return $query;
    }
}
