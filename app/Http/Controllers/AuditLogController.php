<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorizeAuditLogs($request);

        $filters = $this->filters($request);
        $logs = $this->filteredQuery($filters, $request)
            ->with(['user.roles', 'church', 'campus', 'subject'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.audit-logs', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => $this->visibleUsers($request)->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'churches' => $this->visibleChurches($request)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'actions' => $this->scopeLogs(ActivityLog::query(), $request)->select('action')->distinct()->orderBy('action')->pluck('action'),
            'stats' => $this->stats($filters, $request),
            'trends' => $this->trends($filters, $request),
            'securityOverview' => $this->securityOverview($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Audit Logs', 'url' => null],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAuditLogs($request);

        $filters = $this->filters($request);
        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters, $request): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Time', 'User', 'Email', 'Role', 'Church', 'Campus', 'Action', 'Resource', 'Details', 'IP Address', 'Risk Level', 'Status']);

            $this->filteredQuery($filters, $request)
                ->with(['user.roles', 'church', 'campus'])
                ->latest()
                ->lazy(100)
                ->each(function (ActivityLog $log) use ($handle): void {
                    fputcsv($handle, [
                        $log->created_at->toDateTimeString(),
                        $log->user?->name ?? 'Unknown User',
                        $log->user?->email ?? 'system',
                        $log->user?->roles->pluck('name')->join(', '),
                        $log->church?->name ?? 'Global',
                        $log->campus?->name ?? 'All Campuses',
                        str($log->action)->headline(),
                        $log->properties['resource'] ?? $log->module,
                        $log->description,
                        $log->ip_address ?? 'local',
                        $log->properties['risk'] ?? 'low',
                        $log->properties['status'] ?? 'success',
                    ]);
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'tab' => $request->string('tab')->toString() ?: 'activity',
            'user' => $this->queryId($request, 'user', User::class),
            'role' => $this->queryId($request, 'role', Role::class),
            'church' => $this->queryId($request, 'church', Church::class),
            'campus' => $this->queryId($request, 'campus', Campus::class),
            'action' => $request->string('action')->toString() ?: null,
            'risk' => $request->string('risk')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'date_range' => $request->string('date_range')->toString() ?: '7_days',
            'ip' => $request->string('ip')->toString() ?: null,
            'keyword' => $request->string('keyword')->toString() ?: null,
            'ids' => OpaqueId::decodeMany($request->collect('ids')->all(), ActivityLog::class),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ActivityLog>
     */
    private function filteredQuery(array $filters, Request $request): Builder
    {
        return $this->scopeLogs(ActivityLog::query(), $request)
            ->when($filters['tab'] === 'authentication', fn (Builder $query) => $query->where('module', 'Authentication'))
            ->when($filters['tab'] === 'policies', fn (Builder $query) => $query->where(fn (Builder $policyQuery) => $policyQuery
                ->where('module', 'Access Policy')
                ->orWhere('action', 'like', '%permission%')
                ->orWhere('action', 'like', '%policy%')
                ->orWhere('action', 'like', '%role%')))
            ->when($filters['user'], fn (Builder $query, int $user) => $query->where('user_id', $user))
            ->when($filters['role'], fn (Builder $query, int $role) => $query->whereHas('user.roles', fn (Builder $roleQuery) => $roleQuery->whereKey($role)))
            ->when($filters['church'], fn (Builder $query, int $church) => $query->where('church_id', $church))
            ->when($filters['campus'], fn (Builder $query, int $campus) => $query->where('campus_id', $campus))
            ->when($filters['action'], fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['risk'], fn (Builder $query, string $risk) => $query->where('properties->risk', $risk))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('properties->status', $status))
            ->when($filters['ip'], fn (Builder $query, string $ip) => $query->where('ip_address', 'like', "%{$ip}%"))
            ->when($filters['ids'], fn (Builder $query, array $ids) => $query->whereKey($ids))
            ->when($filters['keyword'], fn (Builder $query, string $keyword) => $query->where(fn (Builder $keywordQuery) => $keywordQuery
                ->where('description', 'like', "%{$keyword}%")
                ->orWhere('module', 'like', "%{$keyword}%")
                ->orWhere('action', 'like', "%{$keyword}%")))
            ->when($filters['date_range'] === 'today', fn (Builder $query) => $query->whereDate('created_at', today()))
            ->when($filters['date_range'] === '7_days', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7)))
            ->when($filters['date_range'] === '30_days', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)));
    }

    /**
     * @return array<string, int>
     */
    private function stats(array $filters, Request $request): array
    {
        $query = $this->timeScopedQuery($filters, $request);
        $failed = (clone $query)->where('action', 'failed_login')->count();
        $highRisk = (clone $query)->where('properties->risk', 'high')->count();
        $users = max($this->visibleUsers($request)->count(), 1);
        $mfaPercent = (int) round(($this->visibleUsers($request)->where('mfa_enabled', true)->count() / $users) * 100);

        return [
            'security_score' => max(0, min(100, 100 - ($failed * 2) - ($highRisk * 3))),
            'failed_logins' => $failed,
            'suspicious' => $highRisk,
            'mfa' => $mfaPercent,
            'total' => (clone $query)->count(),
        ];
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function trends(array $filters, Request $request): array
    {
        $mfaPercent = $this->stats($filters, $request)['mfa'];

        return [
            'security' => $this->trendFor($filters, $request, function (Builder $query): int {
                $failed = (clone $query)->where('action', 'failed_login')->count();
                $highRisk = (clone $query)->where('properties->risk', 'high')->count();

                return max(0, min(100, 100 - ($failed * 2) - ($highRisk * 3)));
            }),
            'failed' => $this->trendFor($filters, $request, fn (Builder $query): int => $query->where('action', 'failed_login')->count()),
            'suspicious' => $this->trendFor($filters, $request, fn (Builder $query): int => $query->where('properties->risk', 'high')->count()),
            'mfa' => array_fill(0, 7, $mfaPercent),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function trendFor(array $filters, Request $request, callable $valueForDay): array
    {
        return collect(range(6, 0))->map(function (int $daysAgo) use ($filters, $request, $valueForDay): int {
            $date = now()->subDays($daysAgo)->toDateString();
            $query = $this->timeScopedQuery(array_merge($filters, ['date_range' => 'all']), $request)
                ->whereDate('created_at', $date);

            return (int) $valueForDay($query);
        })->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ActivityLog>
     */
    private function timeScopedQuery(array $filters, Request $request): Builder
    {
        return $this->scopeLogs(ActivityLog::query(), $request)
            ->when($filters['user'], fn (Builder $query, int $user) => $query->where('user_id', $user))
            ->when($filters['role'], fn (Builder $query, int $role) => $query->whereHas('user.roles', fn (Builder $roleQuery) => $roleQuery->whereKey($role)))
            ->when($filters['church'], fn (Builder $query, int $church) => $query->where('church_id', $church))
            ->when($filters['campus'], fn (Builder $query, int $campus) => $query->where('campus_id', $campus))
            ->when($filters['ip'], fn (Builder $query, string $ip) => $query->where('ip_address', 'like', "%{$ip}%"))
            ->when($filters['keyword'], fn (Builder $query, string $keyword) => $query->where(fn (Builder $keywordQuery) => $keywordQuery
                ->where('description', 'like', "%{$keyword}%")
                ->orWhere('module', 'like', "%{$keyword}%")
                ->orWhere('action', 'like', "%{$keyword}%")))
            ->when($filters['date_range'] === 'today', fn (Builder $query) => $query->whereDate('created_at', today()))
            ->when($filters['date_range'] === '7_days', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7)))
            ->when($filters['date_range'] === '30_days', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)));
    }

    /**
     * @return array<string, array<int, array{label: string, value: string, icon: string, state: string}>>
     */
    private function securityOverview(Request $request): array
    {
        $mfaEnabled = $this->visibleUsers($request)->where('mfa_enabled', true)->exists();
        $ssoEnabled = (bool) config('services.sso.enabled', false);
        $lockoutAttempts = (string) config('auth.lockout.max_attempts', 5);
        $sessionLifetime = (string) config('session.lifetime', 120);

        return [
            'authentication' => [
                ['label' => 'Multi-Factor Authentication', 'value' => $mfaEnabled ? 'Enabled' : 'Not enabled', 'icon' => 'shield-check', 'state' => $mfaEnabled ? 'good' : 'warn'],
                ['label' => 'Single Sign-On (SSO)', 'value' => $ssoEnabled ? 'Enabled' : 'Not configured', 'icon' => 'log-in', 'state' => $ssoEnabled ? 'good' : 'neutral'],
                ['label' => 'Password Policy', 'value' => 'Strong (14+ chars)', 'icon' => 'key-round', 'state' => 'good'],
                ['label' => 'Account Lockout Policy', 'value' => $lockoutAttempts.' attempts', 'icon' => 'lock', 'state' => 'neutral'],
                ['label' => 'Session Management', 'value' => 'Active', 'icon' => 'monitor-play', 'state' => 'good'],
                ['label' => 'Audit Logging', 'value' => $this->scopeLogs(ActivityLog::query(), $request)->exists() ? 'Enabled' : 'No events yet', 'icon' => 'clipboard-list', 'state' => $this->scopeLogs(ActivityLog::query(), $request)->exists() ? 'good' : 'warn'],
            ],
            'authorization' => [
                ['label' => 'Role-Based Access Control', 'value' => Role::query()->whereHas('permissions')->exists() ? 'Enforced' : 'Needs setup', 'icon' => 'users', 'state' => Role::query()->whereHas('permissions')->exists() ? 'good' : 'warn'],
                ['label' => 'IP Restriction', 'value' => config('security.ip_restriction.enabled', false) ? 'Enabled' : 'Not configured', 'icon' => 'shield-alert', 'state' => config('security.ip_restriction.enabled', false) ? 'good' : 'neutral'],
                ['label' => 'Device Trust', 'value' => config('security.device_trust.required', false) ? 'Required' : 'Optional', 'icon' => 'monitor-play', 'state' => config('security.device_trust.required', false) ? 'good' : 'neutral'],
                ['label' => 'Session Timeout', 'value' => $sessionLifetime.' minutes', 'icon' => 'calendar-days', 'state' => 'neutral'],
                ['label' => 'Approval Workflow', 'value' => config('security.approval_workflow.enabled', true) ? 'Enabled' : 'Disabled', 'icon' => 'git-branch', 'state' => config('security.approval_workflow.enabled', true) ? 'good' : 'warn'],
            ],
        ];
    }

    private function authorizeAuditLogs(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('view audit log'), 403);
    }

    private function scopeLogs(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function visibleUsers(Request $request): Builder
    {
        $query = User::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function visibleChurches(Request $request): Builder
    {
        $query = Church::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        return $query->where('id', $user?->church_id);
    }

    private function visibleCampuses(Request $request): Builder
    {
        $query = Campus::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where('id', $user->campus_id);
        }

        return $query;
    }

    private function queryId(Request $request, string $key, string $scope): ?int
    {
        return OpaqueId::decode($request->query($key), $scope);
    }
}
