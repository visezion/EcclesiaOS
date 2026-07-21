<x-app-layout title="Audit Logs" :breadcrumbs="$breadcrumbs">
    @php
        $activeTab = $filters['tab'] ?? 'activity';
        $tabQuery = fn (string $tab): array => array_merge(request()->except('page'), ['tab' => $tab]);
        $exportQuery = request()->except(['ids']);
        $riskTone = [
            'low' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'medium' => 'bg-orange-50 text-orange-700 ring-orange-200',
            'high' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ];
        $statusTone = [
            'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'failed' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ];
        $actionTone = function (string $action): array {
            return match ($action) {
                'login' => ['icon' => 'log-in', 'class' => 'text-emerald-600'],
                'logout' => ['icon' => 'log-out', 'class' => 'text-blue-600'],
                'failed_login' => ['icon' => 'shield-alert', 'class' => 'text-rose-600'],
                'password_reset_requested', 'password_reset_completed', 'admin_password_reset' => ['icon' => 'key-round', 'class' => 'text-orange-600'],
                'mfa_enabled', 'profile_preview_started', 'user_impersonation_started' => ['icon' => 'shield-check', 'class' => 'text-blue-600'],
                'role_assigned', 'role_permissions_updated', 'role_created', 'role_cloned', 'role_reset' => ['icon' => 'users', 'class' => 'text-violet-600'],
                'user_created', 'user_updated', 'bulk_user_update' => ['icon' => 'user-plus', 'class' => 'text-emerald-600'],
                default => ['icon' => 'settings', 'class' => 'text-violet-600'],
            };
        };
        $dateLabel = match ($filters['date_range'] ?? '7_days') {
            'today' => 'Today',
            '30_days' => 'Last 30 Days',
            'all' => 'All Time',
            default => 'Last 7 Days',
        };
    @endphp

    <div x-data="{ moreFilters: {{ ($filters['status'] || $filters['ip'] || $filters['keyword']) ? 'true' : 'false' }}, selected: [] }" class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-full bg-violet-100 text-violet-600">
                    <i data-lucide="shield-check" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-950">Audit Logs</h1>
                    <p class="text-sm text-slate-500">Monitor system activity, authentication events, and access policies to ensure security and compliance.</p>
                </div>
            </div>
            <form method="GET" action="{{ route('audit-logs.index') }}">
                @foreach (request()->except(['date_range', 'page']) as $key => $value)
                    @foreach ((array) $value as $item)
                        <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $item }}">
                    @endforeach
                @endforeach
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                    <i data-lucide="calendar-days" class="size-4 text-slate-500"></i>
                    <select name="date_range" onchange="this.form.submit()" class="border-0 bg-transparent p-0 text-sm">
                        <option value="7_days" @selected(($filters['date_range'] ?? '7_days') === '7_days')>Last 7 Days</option>
                        <option value="today" @selected(($filters['date_range'] ?? '') === 'today')>Today</option>
                        <option value="30_days" @selected(($filters['date_range'] ?? '') === '30_days')>Last 30 Days</option>
                        <option value="all" @selected(($filters['date_range'] ?? '') === 'all')>All Time</option>
                    </select>
                </label>
            </form>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="grid gap-4 xl:grid-cols-[1fr_380px]">
            <main class="min-w-0 space-y-4">
                <nav class="border-b border-slate-200">
                    <div class="flex gap-6 overflow-x-auto text-sm font-semibold text-slate-500">
                        <a href="{{ route('audit-logs.index', $tabQuery('activity')) }}" class="whitespace-nowrap border-b-2 px-4 py-3 {{ $activeTab === 'activity' ? 'border-violet-600 text-violet-600' : 'border-transparent hover:text-slate-900' }}">Activity Logs</a>
                        <a href="{{ route('audit-logs.index', $tabQuery('authentication')) }}" class="whitespace-nowrap border-b-2 px-4 py-3 {{ $activeTab === 'authentication' ? 'border-violet-600 text-violet-600' : 'border-transparent hover:text-slate-900' }}">Authentication Events</a>
                        <a href="{{ route('audit-logs.index', $tabQuery('policies')) }}" class="whitespace-nowrap border-b-2 px-4 py-3 {{ $activeTab === 'policies' ? 'border-violet-600 text-violet-600' : 'border-transparent hover:text-slate-900' }}">Access Policies</a>
                    </div>
                </nav>

                <section class="dashboard-card">
                    <form method="GET" action="{{ route('audit-logs.index') }}" class="space-y-4">
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>User</span>
                                <select name="user" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Users</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->opaqueId() }}" @selected($filters['user'] === $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Role</span>
                                <select name="role" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Roles</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->opaqueId() }}" @selected($filters['role'] === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Church</span>
                                <select name="church" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Churches</option>
                                    @foreach ($churches as $church)
                                        <option value="{{ $church->opaqueId() }}" @selected($filters['church'] === $church->id)>{{ $church->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Campus</span>
                                <select name="campus" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Campuses</option>
                                    @foreach ($campuses as $campus)
                                        <option value="{{ $campus->opaqueId() }}" @selected($filters['campus'] === $campus->id)>{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Action Type</span>
                                <select name="action" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Actions</option>
                                    @foreach ($actions as $action)
                                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ str($action)->headline() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-[220px_220px_1fr_auto_auto_auto]">
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Date Range</span>
                                <select name="date_range" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="7_days" @selected(($filters['date_range'] ?? '7_days') === '7_days')>{{ $dateLabel === 'Last 7 Days' ? 'Last 7 Days' : 'Last 7 Days' }}</option>
                                    <option value="today" @selected(($filters['date_range'] ?? '') === 'today')>Today</option>
                                    <option value="30_days" @selected(($filters['date_range'] ?? '') === '30_days')>Last 30 Days</option>
                                    <option value="all" @selected(($filters['date_range'] ?? '') === 'all')>All Time</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Risk Level</span>
                                <select name="risk" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Risk Levels</option>
                                    <option value="low" @selected($filters['risk'] === 'low')>Low</option>
                                    <option value="medium" @selected($filters['risk'] === 'medium')>Medium</option>
                                    <option value="high" @selected($filters['risk'] === 'high')>High</option>
                                </select>
                            </label>
                            <div></div>
                            <button type="button" @click="moreFilters = ! moreFilters" class="self-end inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="sliders-horizontal" class="size-4"></i>
                                More Filters
                            </button>
                            <a href="{{ route('audit-logs.index', ['tab' => $activeTab]) }}" class="self-end px-3 py-2.5 text-sm font-semibold text-violet-600">Clear</a>
                            <button type="submit" class="self-end inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                                <i data-lucide="settings" class="size-4"></i>
                                Apply Filters
                            </button>
                        </div>

                        <div x-cloak x-show="moreFilters" class="grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-3">
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Status</span>
                                <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Statuses</option>
                                    <option value="success" @selected($filters['status'] === 'success')>Success</option>
                                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>IP Address</span>
                                <input name="ip" value="{{ $filters['ip'] }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700" placeholder="192.168">
                            </label>
                            <label class="space-y-1 text-xs font-semibold text-slate-500">
                                <span>Keyword</span>
                                <input name="keyword" value="{{ $filters['keyword'] }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700" placeholder="Search details or resource">
                            </label>
                        </div>
                    </form>
                </section>

                <section class="dashboard-card p-0">
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <i data-lucide="clipboard-list" class="size-5 text-violet-600"></i>
                            <h2 class="text-base font-semibold text-slate-950">Activity Logs</h2>
                            <span class="text-sm text-slate-500">Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }} events</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('audit-logs.export', $exportQuery) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="download" class="size-4"></i>
                                Export
                            </a>
                            <form method="GET" action="{{ route('audit-logs.export') }}" class="inline-flex">
                                @foreach ($exportQuery as $key => $value)
                                    @if (is_array($value))
                                        @foreach ($value as $item)
                                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                        @endforeach
                                    @else
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <template x-for="id in selected" :key="id">
                                    <input type="hidden" name="ids[]" :value="id">
                                </template>
                                <button type="submit" :disabled="selected.length === 0" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    <i data-lucide="file-search" class="size-4"></i>
                                    <span x-text="selected.length ? `Export Selected (${selected.length})` : 'Export Selected'">Export Selected</span>
                                </button>
                            </form>
                            <a href="{{ route('audit-logs.index', request()->query()) }}" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" title="Refresh audit logs">
                                <i data-lucide="refresh-cw" class="size-4"></i>
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-compact min-w-[1120px]">
                            <thead>
                                <tr>
                                    <th class="w-10"><input type="checkbox" class="rounded border-slate-300" @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('[data-log-id]')).map(row => row.dataset.logId) : []"></th>
                                    <th>Time & Date</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Resource</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                    <th>Risk Level</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    @php
                                        $risk = strtolower($log->properties['risk'] ?? 'low');
                                        $status = strtolower($log->properties['status'] ?? 'success');
                                        $tone = $actionTone($log->action);
                                    @endphp
                                    <tr data-log-id="{{ $log->opaqueId() }}">
                                        <td><input type="checkbox" x-model="selected" value="{{ $log->opaqueId() }}" class="rounded border-slate-300"></td>
                                        <td>{{ $log->created_at->format('M d, Y') }}<div class="text-xs text-slate-500">{{ $log->created_at->format('h:i:s A') }}</div></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                @if ($log->user?->avatar_src)
                                                    <img src="{{ $log->user->avatar_src }}" alt="{{ $log->user->name }}" class="size-9 rounded-full object-cover">
                                                @else
                                                    <div class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-600"><i data-lucide="user-round" class="size-4"></i></div>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="truncate font-semibold text-slate-900">{{ $log->user?->name ?? 'Unknown User' }}</div>
                                                    <div class="truncate text-xs text-slate-500">{{ $log->user?->email ?? 'system' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-2 text-sm font-semibold {{ $tone['class'] }}">
                                                <i data-lucide="{{ $tone['icon'] }}" class="size-4"></i>
                                                {{ str($log->action)->headline() }}
                                            </span>
                                        </td>
                                        <td>{{ $log->properties['resource'] ?? $log->module }}</td>
                                        <td>{{ $log->description }}</td>
                                        <td>{{ $log->ip_address ?? 'local' }}</td>
                                        <td><span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ring-1 {{ $riskTone[$risk] ?? $riskTone['low'] }}"><span class="size-2 rounded-full {{ $risk === 'high' ? 'bg-rose-500' : ($risk === 'medium' ? 'bg-orange-500' : 'bg-emerald-500') }}"></span>{{ $risk }}</span></td>
                                        <td><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ring-1 {{ $statusTone[$status] ?? $statusTone['success'] }}">{{ $status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-10 text-center text-sm text-slate-500">No audit events match the current filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <span>Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }} events</span>
                        <div>{{ $logs->onEachSide(1)->links() }}</div>
                    </div>
                </section>
            </main>

            <aside class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <section class="dashboard-card audit-metric-card">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-600"><i data-lucide="shield-check" class="size-6"></i></span>
                            <div class="min-w-0">
                                <div class="text-xs font-medium text-slate-500">Security Score</div>
                                <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $stats['security_score'] }}<span class="text-sm font-medium">/100</span></div>
                                <div class="mt-1 text-xs font-medium text-emerald-600"><i data-lucide="arrow-up" class="inline size-3"></i> Excellent</div>
                            </div>
                        </div>
                        <canvas class="audit-sparkline mt-3" data-chart="sparkline" data-values='@js($trends['security'])' data-color="#10b981"></canvas>
                    </section>
                    <section class="dashboard-card audit-metric-card">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-rose-50 text-rose-600"><i data-lucide="lock" class="size-6"></i></span>
                            <div class="min-w-0">
                                <div class="text-xs font-medium text-slate-500">Failed Logins</div>
                                <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $stats['failed_logins'] }}</div>
                                <div class="mt-1 text-xs font-medium text-rose-600"><i data-lucide="arrow-up" class="inline size-3"></i> Requires review</div>
                            </div>
                        </div>
                        <canvas class="audit-sparkline mt-3" data-chart="sparkline" data-values='@js($trends['failed'])' data-color="#f43f5e"></canvas>
                    </section>
                    <section class="dashboard-card audit-metric-card">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-orange-50 text-orange-600"><i data-lucide="triangle-alert" class="size-6"></i></span>
                            <div class="min-w-0">
                                <div class="text-xs font-medium text-slate-500">Suspicious Activity</div>
                                <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $stats['suspicious'] }}</div>
                                <div class="mt-1 text-xs font-medium text-orange-600">High risk events</div>
                            </div>
                        </div>
                        <canvas class="audit-sparkline mt-3" data-chart="sparkline" data-values='@js($trends['suspicious'])' data-color="#f97316"></canvas>
                    </section>
                    <section class="dashboard-card audit-metric-card">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-violet-50 text-violet-600"><i data-lucide="fingerprint" class="size-6"></i></span>
                            <div class="min-w-0">
                                <div class="text-xs font-medium text-slate-500">MFA Adoption</div>
                                <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $stats['mfa'] }}%</div>
                                <div class="mt-1 text-xs font-medium text-emerald-600"><i data-lucide="arrow-up" class="inline size-3"></i> Enabled users</div>
                            </div>
                        </div>
                        <canvas class="audit-sparkline mt-3" data-chart="sparkline" data-values='@js($trends['mfa'])' data-color="#6d4aff"></canvas>
                    </section>
                </div>

                <section class="dashboard-card">
                    <div class="mb-4 border-b border-slate-100 pb-4">
                        <h2 class="text-base font-semibold text-slate-950">Security & Access Overview</h2>
                    </div>
                    <div class="space-y-5 text-sm">
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-slate-950">Authentication Middleware</h3>
                            @foreach ($securityOverview['authentication'] as $item)
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <span class="flex min-w-0 items-center gap-2 text-slate-600"><i data-lucide="{{ $item['icon'] }}" class="size-4 text-violet-500"></i><span class="truncate">{{ $item['label'] }}</span></span>
                                    <span class="font-semibold {{ $item['state'] === 'good' ? 'text-emerald-600' : ($item['state'] === 'warn' ? 'text-rose-600' : 'text-slate-700') }}">{{ $item['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-slate-950">Authorization Policies</h3>
                            @foreach ($securityOverview['authorization'] as $item)
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <span class="flex min-w-0 items-center gap-2 text-slate-600"><i data-lucide="{{ $item['icon'] }}" class="size-4 text-violet-500"></i><span class="truncate">{{ $item['label'] }}</span></span>
                                    <span class="font-semibold {{ $item['state'] === 'good' ? 'text-emerald-600' : ($item['state'] === 'warn' ? 'text-rose-600' : 'text-slate-700') }}">{{ $item['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ route('settings.index') }}" class="mt-5 flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-violet-600 hover:bg-violet-50">
                        <i data-lucide="shield-check" class="size-5"></i>
                        View All Security Settings
                    </a>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
