<x-app-layout title="Users Management" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4" x-data="userDirectory()">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-2xl bg-violet-100 text-violet-600"><i data-lucide="users" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Users Management</h1>
                    <p class="text-sm text-slate-500">Manage user accounts, church and campus assignments, roles, and account status across your organization.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('settings.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="settings" class="size-4"></i> User Settings
                </a>
                <button type="button" @click="inviteOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">
                    <i data-lucide="user-plus" class="size-4"></i> Add / Invite User
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <div class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <x-stat-card :metric="['label' => 'Total Users', 'value' => number_format($stats['total']), 'change' => 'DB', 'period' => 'live records', 'icon' => 'users', 'color' => 'purple', 'route' => 'users.index']" />
                    <x-stat-card :metric="['label' => 'Active Users', 'value' => number_format($stats['active']), 'change' => null, 'period' => 'active accounts', 'icon' => 'users-round', 'color' => 'emerald', 'route' => 'users.index']" />
                    <x-stat-card :metric="['label' => 'Pending Users', 'value' => number_format($stats['pending']), 'change' => null, 'period' => 'awaiting activation', 'icon' => 'user-plus', 'color' => 'orange', 'route' => 'users.index']" />
                    <x-stat-card :metric="['label' => 'Locked Accounts', 'value' => number_format($stats['locked']), 'change' => null, 'period' => 'require attention', 'icon' => 'shield-alert', 'color' => 'rose', 'route' => 'users.index']" />
                </div>

                <section class="dashboard-card">
                <div class="mb-4 grid gap-3 lg:grid-cols-[1fr_180px_180px_180px_auto]">
                    <input x-model.debounce.150ms="search" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Search users by name, email, or phone...">
                    <select x-model="role" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">All Roles</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select>
                    <select x-model="campus" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach</select>
                    <select x-model="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">All Statuses</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="inactive">Inactive</option></select>
                    <button type="button" @click="clearFilters()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Clear</button>
                </div>
                <form id="bulk-users-form" method="POST" action="{{ route('users.bulk') }}">
                    @csrf
                    <div class="mb-3 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="action" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600" required>
                                <option value="">Bulk Actions</option>
                                <option value="activate">Activate selected</option>
                                <option value="deactivate">Deactivate selected</option>
                                <option value="suspend">Suspend selected</option>
                                <option value="enable_mfa">Enable MFA</option>
                                <option value="disable_mfa">Disable MFA</option>
                            </select>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="selected.length === 0">
                                <i data-lucide="check" class="size-4"></i> Apply
                            </button>
                            <span class="text-xs font-semibold text-slate-400" x-text="selected.length ? `${selected.length} selected` : `${visibleCount()} users found`"></span>
                        </div>
                        <a href="{{ route('users.export') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                            <i data-lucide="download" class="size-4"></i> Export
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-compact min-w-[1180px]">
                            <thead>
                                <tr>
                                    <th class="w-10"><input type="checkbox" class="rounded border-slate-300" :checked="allVisibleSelected()" @change="toggleAll($event)"></th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Church</th>
                                    <th>Campus</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>MFA</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    @php
                                        $primaryRole = $user->roles->first();
                                        $viewPayload = [
                                             'id' => $user->opaqueId(),
                                            'name' => $user->name,
                                            'email' => $user->email,
                                            'phone' => $user->phone ?? 'No phone number',
                                            'title' => $user->title ?? 'No title',
                                            'role' => $primaryRole?->name ?? 'No Role',
                                            'church' => $user->church?->name ?? 'Global',
                                            'campus' => $user->campus?->name ?? 'All',
                                            'status' => $user->status,
                                            'mfa' => $user->mfa_enabled ? 'Enabled' : 'Disabled',
                                            'lastLogin' => $user->last_login_at?->format('M d, Y h:i A') ?? 'Never',
                                            'emailHref' => 'mailto:'.$user->email.'?subject='.rawurlencode('KingdomHub account'),
                                        ];
                                    @endphp
                                    <tr
                                        data-user-row
                                        data-user-id="{{ $user->opaqueId() }}"
                                        data-search="{{ Str::lower($user->name.' '.$user->email.' '.($user->phone ?? '')) }}"
                                        data-roles="{{ $user->roles->pluck('id')->join(',') }}"
                                        data-campus="{{ $user->campus_id ?? '' }}"
                                        data-status="{{ $user->status }}"
                                        x-show="matches($el)"
                                    >
                                        <td><input type="checkbox" name="users[]" value="{{ $user->opaqueId() }}" x-model="selected" class="rounded border-slate-300"></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                @if ($user->avatar_src)
                                                    <img src="{{ $user->avatar_src }}" alt="{{ $user->name }}" class="size-10 rounded-full object-cover ring-2 ring-white">
                                                @else
                                                    <div class="grid size-10 place-items-center rounded-full bg-violet-100 text-sm font-black text-violet-700">{{ Str::of($user->name)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join('') }}</div>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="truncate font-bold text-slate-900">{{ $user->name }}</div>
                                                    <div class="truncate text-xs font-normal text-slate-500">{{ $user->phone ?? 'No phone number' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td><x-status-badge :status="$primaryRole?->name ?? 'No Role'" /></td>
                                        <td>{{ $user->church?->name ?? 'Global' }}</td>
                                        <td><span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-emerald-500"></span>{{ $user->campus?->name ?? 'All' }}</span></td>
                                        <td><x-status-badge :status="$user->status" /></td>
                                        <td>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Never' }}</td>
                                        <td><i data-lucide="{{ $user->mfa_enabled ? 'shield-check' : 'minus' }}" class="size-4 {{ $user->mfa_enabled ? 'text-emerald-600' : 'text-slate-400' }}"></i></td>
                                        <td>
                                            <div class="flex justify-end gap-1">
                                                <a href="{{ $viewPayload['emailHref'] }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Email user"><i data-lucide="mail" class="size-4"></i></a>
                                                <a href="{{ route('users.show', $user) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="View user profile"><i data-lucide="eye" class="size-4"></i></a>
                                                <a href="{{ route('users.show', ['user' => $user, 'edit' => 1]) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Edit user profile"><i data-lucide="pencil" class="size-4"></i></a>
                                                <button type="button" @click="actioning = '{{ $user->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="More actions"><i data-lucide="more-vertical" class="size-4"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr x-show="visibleCount() === 0">
                                    <td colspan="10" class="py-8 text-center text-sm font-semibold text-slate-500">No users match the current filters.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
                        <span x-text="`Showing ${visibleCount()} of {{ $users->count() }} users`"></span>
                        <span class="rounded-lg border border-slate-200 px-3 py-2">10 per page</span>
                    </div>
                </form>
                @foreach ($users as $user)
                    <form id="single-user-activate-{{ $user->opaqueId() }}" method="POST" action="{{ route('users.bulk') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="users[]" value="{{ $user->opaqueId() }}">
                    </form>
                    <form id="single-user-suspend-{{ $user->opaqueId() }}" method="POST" action="{{ route('users.bulk') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="action" value="suspend">
                        <input type="hidden" name="users[]" value="{{ $user->opaqueId() }}">
                    </form>
                    <form id="single-user-deactivate-{{ $user->opaqueId() }}" method="POST" action="{{ route('users.bulk') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="users[]" value="{{ $user->opaqueId() }}">
                    </form>
                    <form id="single-user-mfa-{{ $user->opaqueId() }}" method="POST" action="{{ route('users.bulk') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="action" value="{{ $user->mfa_enabled ? 'disable_mfa' : 'enable_mfa' }}">
                        <input type="hidden" name="users[]" value="{{ $user->opaqueId() }}">
                    </form>
                @endforeach
                </section>
            </div>

            <aside class="space-y-4">
                @php
                    $roleColors = ['bg-violet-600', 'bg-blue-500', 'bg-emerald-500', 'bg-cyan-500', 'bg-orange-500', 'bg-amber-400'];
                    $campusColors = ['bg-violet-600', 'bg-blue-500', 'bg-purple-500', 'bg-orange-500', 'bg-rose-500', 'bg-teal-500'];
                    $chartHexColors = ['#6d4aff', '#2477f2', '#10b981', '#06b6d4', '#f97316', '#f59e0b'];
                    $campusHexColors = ['#6d4aff', '#2477f2', '#a855f7', '#f97316', '#f43f5e', '#14b8a6'];
                    $roleSourceRows = $roleDistribution->filter(fn ($role) => (int) $role->users_count > 0)->values();
                    $campusSourceRows = $campusDistribution->filter(fn ($campus) => (int) $campus->users_count > 0)->values();
                    $roleChartRows = $roleSourceRows->take(5)->map(fn ($role, $index) => [
                        'label' => $role->name,
                        'value' => (int) $role->users_count,
                        'color' => $roleColors[$index % count($roleColors)],
                        'hex' => $chartHexColors[$index % count($chartHexColors)],
                    ])->values();
                    $roleOtherCount = (int) $roleSourceRows->skip(5)->sum('users_count');
                    if ($roleOtherCount > 0) {
                        $roleChartRows->push(['label' => 'Other Roles', 'value' => $roleOtherCount, 'color' => 'bg-amber-400', 'hex' => '#f59e0b']);
                    }
                    $campusChartRows = $campusSourceRows->take(5)->map(fn ($campus, $index) => [
                        'label' => $campus->name,
                        'value' => (int) $campus->users_count,
                        'color' => $campusColors[$index % count($campusColors)],
                        'hex' => $campusHexColors[$index % count($campusHexColors)],
                    ])->values();
                    $campusOtherCount = (int) $campusSourceRows->skip(5)->sum('users_count');
                    if ($campusOtherCount > 0) {
                        $campusChartRows->push(['label' => 'Other Campuses', 'value' => $campusOtherCount, 'color' => 'bg-teal-500', 'hex' => '#14b8a6']);
                    }
                    $roleTotal = max($roleChartRows->sum('value'), 1);
                    $campusTotal = max($campusChartRows->sum('value'), 1);
                    $statusDistribution = collect([
                        ['label' => 'Active', 'value' => (int) $stats['active'], 'color' => 'bg-emerald-500', 'hex' => '#10b981'],
                        ['label' => 'Inactive', 'value' => (int) $stats['pending'], 'color' => 'bg-amber-400', 'hex' => '#f59e0b'],
                        ['label' => 'Suspended', 'value' => (int) $stats['locked'], 'color' => 'bg-rose-500', 'hex' => '#f43f5e'],
                    ]);
                    $statusTotal = max($statusDistribution->sum('value'), 1);
                    $donutGradient = function ($rows, int $total): string {
                        $running = 0;
                        $segments = [];

                        foreach ($rows as $row) {
                            $value = (int) $row['value'];

                            if ($value <= 0) {
                                continue;
                            }

                            $start = round(($running / $total) * 100, 2);
                            $running += $value;
                            $end = round(min(100, ($running / $total) * 100), 2);
                            $segments[] = "{$row['hex']} {$start}% {$end}%";
                        }

                        return count($segments) > 0 ? 'conic-gradient('.implode(', ', $segments).')' : '#e2e8f0';
                    };
                @endphp

                <x-stat-card :metric="['label' => 'Campuses', 'value' => number_format($stats['campuses']), 'change' => null, 'period' => 'assignment scopes', 'icon' => 'landmark', 'color' => 'teal', 'route' => 'campuses.index']" />

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-950">Users by Status</h2>
                        <a href="{{ route('users.index') }}" class="text-xs font-bold text-violet-600">View All</a>
                    </div>
                    <div class="space-y-5">
                        <div class="space-y-3">
                            @foreach ($statusDistribution as $statusItem)
                                @php
                                    $percent = round(($statusItem['value'] / $statusTotal) * 100, 1);
                                @endphp
                                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3 text-sm">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="size-2.5 rounded-full {{ $statusItem['color'] }}"></span>
                                        <span class="truncate text-slate-600">{{ $statusItem['label'] }}</span>
                                    </div>
                                    <span class="font-black text-slate-950">{{ number_format($statusItem['value']) }}</span>
                                    <span class="w-12 text-right text-xs text-slate-500">{{ $percent }}%</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="relative mx-auto size-40">
                            <div class="absolute inset-0 rounded-full p-[14px]" style="background: {{ $donutGradient($statusDistribution, $statusTotal) }}">
                                <div class="size-full rounded-full bg-white"></div>
                            </div>
                            <canvas
                                class="relative size-full"
                                data-chart="doughnut"
                                data-labels='@js($statusDistribution->pluck('label'))'
                                data-values='@js($statusDistribution->pluck('value'))'
                                data-colors='@js($statusDistribution->pluck('hex'))'
                            ></canvas>
                            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                                <div>
                                    <div class="text-2xl font-black text-slate-950">{{ number_format($stats['total']) }}</div>
                                    <div class="text-[10px] font-black uppercase text-slate-400">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-950">Users by Role</h2>
                        <a href="{{ route('roles.index') }}" class="text-xs font-bold text-violet-600">View All</a>
                    </div>
                    <div class="space-y-5">
                        <div class="space-y-3">
                            @foreach ($roleChartRows as $role)
                                @php
                                    $count = (int) $role['value'];
                                    $percent = $roleTotal > 0 ? round(($count / $roleTotal) * 100, 1) : 0;
                                @endphp
                                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3 text-sm">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="size-2.5 rounded-full {{ $role['color'] }}"></span>
                                        <span class="truncate text-slate-600">{{ $role['label'] }}</span>
                                    </div>
                                    <span class="font-black text-slate-950">{{ number_format($count) }}</span>
                                    <span class="w-12 text-right text-xs text-slate-500">{{ $percent }}%</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="relative mx-auto size-40">
                            <div class="absolute inset-0 rounded-full p-[14px]" style="background: {{ $donutGradient($roleChartRows, $roleTotal) }}">
                                <div class="size-full rounded-full bg-white"></div>
                            </div>
                            <canvas
                                class="relative size-full"
                                data-chart="doughnut"
                                data-labels='@js($roleChartRows->pluck('label'))'
                                data-values='@js($roleChartRows->pluck('value'))'
                                data-colors='@js($roleChartRows->pluck('hex'))'
                            ></canvas>
                            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                                <div>
                                    <div class="text-2xl font-black text-slate-950">{{ number_format($roleChartRows->sum('value')) }}</div>
                                    <div class="text-[10px] font-black uppercase text-slate-400">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-950">Users by Campus</h2>
                        <a href="{{ route('campuses.index') }}" class="text-xs font-bold text-violet-600">View All</a>
                    </div>
                    <div class="space-y-5">
                        <div class="space-y-3">
                            @foreach ($campusChartRows as $campus)
                                @php
                                    $count = (int) $campus['value'];
                                    $percent = $campusTotal > 0 ? round(($count / $campusTotal) * 100, 1) : 0;
                                @endphp
                                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3 text-sm">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="size-2.5 rounded-full {{ $campus['color'] }}"></span>
                                        <span class="truncate text-slate-600">{{ $campus['label'] }}</span>
                                    </div>
                                    <span class="font-black text-slate-950">{{ number_format($count) }}</span>
                                    <span class="w-12 text-right text-xs text-slate-500">{{ $percent }}%</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="relative mx-auto size-40">
                            <div class="absolute inset-0 rounded-full p-[14px]" style="background: {{ $donutGradient($campusChartRows, $campusTotal) }}">
                                <div class="size-full rounded-full bg-white"></div>
                            </div>
                            <canvas
                                class="relative size-full"
                                data-chart="doughnut"
                                data-labels='@js($campusChartRows->pluck('label'))'
                                data-values='@js($campusChartRows->pluck('value'))'
                                data-colors='@js($campusChartRows->pluck('hex'))'
                            ></canvas>
                            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                                <div>
                                    <div class="text-2xl font-black text-slate-950">{{ number_format($campusChartRows->sum('value')) }}</div>
                                    <div class="text-[10px] font-black uppercase text-slate-400">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-950">Recent Account Activity</h2>
                        <a href="{{ route('audit-logs.index') }}" class="text-xs font-bold text-violet-600">View All</a>
                    </div>
                    @forelse ($recentActivity as $log)
                        <div class="flex gap-3 border-b border-slate-100 py-3 last:border-0">
                            <div class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="shield-check" class="size-4"></i></div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold">{{ $log->description }}</div>
                                <div class="text-xs text-slate-500">{{ $log->causer_name ?? $log->user?->name ?? 'System' }} | {{ $log->created_at->format('M d, Y - h:i A') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm text-slate-500">No account activity has been recorded yet.</div>
                    @endforelse
                </section>
            </aside>
        </div>

        <div x-cloak x-show="viewing" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @keydown.escape.window="viewing = null">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl" @click.outside="viewing = null">
                <div class="flex items-start justify-between border-b border-slate-100 p-5">
                    <div>
                        <h2 class="text-lg font-black text-slate-950" x-text="viewing ? viewing.name : ''"></h2>
                        <p class="mt-1 text-sm text-slate-500" x-text="viewing ? viewing.title : ''"></p>
                    </div>
                    <button type="button" @click="viewing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                        <i data-lucide="x" class="size-4"></i>
                    </button>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">Email</div>
                        <div class="mt-1 break-all text-sm font-bold text-slate-900" x-text="viewing ? viewing.email : ''"></div>
                    </div>
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">Phone</div>
                        <div class="mt-1 text-sm font-bold text-slate-900" x-text="viewing ? viewing.phone : ''"></div>
                    </div>
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">Role</div>
                        <div class="mt-1 text-sm font-bold text-slate-900" x-text="viewing ? viewing.role : ''"></div>
                    </div>
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">Status</div>
                        <div class="mt-1 text-sm font-bold capitalize text-slate-900" x-text="viewing ? viewing.status : ''"></div>
                    </div>
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">Church</div>
                        <div class="mt-1 text-sm font-bold text-slate-900" x-text="viewing ? viewing.church : ''"></div>
                    </div>
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">Campus</div>
                        <div class="mt-1 text-sm font-bold text-slate-900" x-text="viewing ? viewing.campus : ''"></div>
                    </div>
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">MFA</div>
                        <div class="mt-1 text-sm font-bold text-slate-900" x-text="viewing ? viewing.mfa : ''"></div>
                    </div>
                    <div class="rounded-lg border border-slate-100 p-3">
                        <div class="text-xs font-black uppercase text-slate-400">Last Login</div>
                        <div class="mt-1 text-sm font-bold text-slate-900" x-text="viewing ? viewing.lastLogin : ''"></div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 p-5">
                    <a :href="viewing ? viewing.emailHref : '#'" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="mail" class="size-4"></i> Send Email
                    </a>
                    <button type="button" @click="editing = String(viewing.id); viewing = null" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">
                        <i data-lucide="pencil" class="size-4"></i> Edit User
                    </button>
                </div>
            </div>
        </div>

        @foreach ($users as $user)
            <div x-cloak x-show="editing === '{{ $user->opaqueId() }}'" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/40 p-4" @keydown.escape.window="editing = null">
                <div class="my-6 w-full max-w-3xl rounded-xl bg-white shadow-2xl" @click.outside="editing = null">
                    <div class="flex items-start justify-between border-b border-slate-100 p-5">
                        <div>
                            <h2 class="text-lg font-black text-slate-950">Edit User</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $user->name }} | {{ $user->email }}</p>
                        </div>
                        <button type="button" @click="editing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                            <i data-lucide="x" class="size-4"></i>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4 p-5">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Full Name
                                <input name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required>
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Email Address
                                <input name="email" type="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required>
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Phone Number
                                <input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Title
                                <input name="title" value="{{ old('title', $user->title) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Status
                                <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">
                                    <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                                    <option value="suspended" @selected(old('status', $user->status) === 'suspended')>Suspended</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Role
                                <select name="roles[]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected($user->roles->contains($role))>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Church
                                <select name="church_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">
                                    <option value="">Global</option>
                                    @foreach($churches as $church)
                                        <option value="{{ $church->id }}" @selected((string) old('church_id', $user->church_id) === (string) $church->id)>{{ $church->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Campus
                                <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">
                                    <option value="">All Campuses</option>
                                    @foreach($campuses as $campus)
                                        <option value="{{ $campus->id }}" @selected((string) old('campus_id', $user->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">New Password
                                <input name="password" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="Leave blank to keep current">
                            </label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Confirm New Password
                                <input name="password_confirmation" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="Leave blank to keep current">
                            </label>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                            <button type="button" @click="editing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">
                                <i data-lucide="check" class="size-4"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        @foreach ($users as $user)
            @php
                $primaryRole = $user->roles->first();
                $viewPayload = [
                    'id' => $user->opaqueId(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? 'No phone number',
                    'title' => $user->title ?? 'No title',
                    'role' => $primaryRole?->name ?? 'No Role',
                    'church' => $user->church?->name ?? 'Global',
                    'campus' => $user->campus?->name ?? 'All',
                    'status' => $user->status,
                    'mfa' => $user->mfa_enabled ? 'Enabled' : 'Disabled',
                    'lastLogin' => $user->last_login_at?->format('M d, Y h:i A') ?? 'Never',
                    'emailHref' => 'mailto:'.$user->email.'?subject='.rawurlencode('KingdomHub account'),
                ];
            @endphp
            <div x-cloak x-show="actioning === '{{ $user->opaqueId() }}'" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @keydown.escape.window="actioning = null">
                <div class="w-full max-w-sm rounded-xl bg-white shadow-2xl" @click.outside="actioning = null">
                    <div class="flex items-start justify-between border-b border-slate-100 p-5">
                        <div>
                            <h2 class="text-lg font-black text-slate-950">User Actions</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $user->name }}</p>
                        </div>
                        <button type="button" @click="actioning = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                            <i data-lucide="x" class="size-4"></i>
                        </button>
                    </div>
                    <div class="space-y-2 p-5">
                        <a href="{{ $viewPayload['emailHref'] }}" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="mail" class="size-4"></i>Send Email</a>
                        <a href="{{ route('users.show', $user) }}" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="eye" class="size-4"></i>View Full Profile</a>
                        <a href="{{ route('users.show', ['user' => $user, 'edit' => 1]) }}" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="pencil" class="size-4"></i>Edit Full Profile</a>
                        <form method="POST" action="{{ route('users.impersonate', $user) }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded-lg border border-violet-200 px-3 py-2 text-left text-sm font-bold text-violet-700 hover:bg-violet-50"><i data-lucide="user-check" class="size-4"></i>Impersonate User</button>
                        </form>
                        @if ($user->status === 'active')
                            <button type="submit" form="single-user-suspend-{{ $user->opaqueId() }}" class="flex w-full items-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-left text-sm font-bold text-rose-700 hover:bg-rose-50"><i data-lucide="shield-alert" class="size-4"></i>Suspend User</button>
                            <button type="submit" form="single-user-deactivate-{{ $user->opaqueId() }}" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="minus" class="size-4"></i>Deactivate User</button>
                        @else
                            <button type="submit" form="single-user-activate-{{ $user->opaqueId() }}" class="flex w-full items-center gap-2 rounded-lg border border-emerald-200 px-3 py-2 text-left text-sm font-bold text-emerald-700 hover:bg-emerald-50"><i data-lucide="check" class="size-4"></i>Activate User</button>
                        @endif
                        <button type="submit" form="single-user-mfa-{{ $user->opaqueId() }}" class="flex w-full items-center gap-2 rounded-lg border border-violet-200 px-3 py-2 text-left text-sm font-bold text-violet-700 hover:bg-violet-50"><i data-lucide="shield-check" class="size-4"></i>{{ $user->mfa_enabled ? 'Disable' : 'Enable' }} MFA</button>
                    </div>
                </div>
            </div>
        @endforeach

        <div x-cloak x-show="inviteOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/40 p-4" @keydown.escape.window="inviteOpen = false">
            <div class="my-6 w-full max-w-lg rounded-xl bg-white shadow-2xl" @click.outside="inviteOpen = false">
                <div class="flex items-start justify-between border-b border-slate-100 p-5">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Add / Invite New User</h2>
                        <p class="mt-1 text-sm text-slate-500">Create a new account or send an invitation.</p>
                    </div>
                    <button type="button" @click="inviteOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                        <i data-lucide="x" class="size-4"></i>
                    </button>
                </div>
                <form id="create-user-form" method="POST" action="{{ route('users.store') }}" class="space-y-4 p-5">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Full Name
                            <input name="name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="Enter full name" required>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Email Address
                            <input name="email" type="email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="name@church.org" required>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Phone Number
                            <input name="phone" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="+1 (555) 000-0000">
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Title
                            <input name="title" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="Ministry role">
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Role
                            <select name="roles[]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Church
                            <select name="church_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">@foreach($churches as $church)<option value="{{ $church->id }}">{{ $church->name }}</option>@endforeach</select>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Campus
                            <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">@foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach</select>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Temporary Password
                            <input name="password" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="Temporary password" required>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500 sm:col-span-2">Confirm Password
                            <input name="password_confirmation" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="Confirm temporary password" required>
                        </label>
                    </div>
                    <input type="hidden" name="status" value="active">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <input type="checkbox" name="send_invitation" value="1" checked class="rounded border-slate-300 text-violet-600">
                        Send invitation email
                    </label>
                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" @click="inviteOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">
                            <i data-lucide="send" class="size-4"></i> Send Invitation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
