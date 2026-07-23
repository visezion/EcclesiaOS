<x-app-layout title="Churches & Campuses" :breadcrumbs="$breadcrumbs">
    @php
        $typeColors = [
            'Main Campus' => ['bg' => 'bg-violet-50', 'text' => 'text-violet-700', 'ring' => 'ring-violet-200', 'hex' => '#6d4aff'],
            'Regional Campus' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'ring' => 'ring-sky-200', 'hex' => '#2477f2'],
            'City Campus' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'ring' => 'ring-orange-200', 'hex' => '#f97316'],
            'Online Campus' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'ring' => 'ring-purple-200', 'hex' => '#a855f7'],
            'Ministry Campus' => ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-700', 'ring' => 'ring-cyan-200', 'hex' => '#14b8a6'],
        ];
        $chartColors = ['#6d4aff', '#2477f2', '#14b8a6', '#f97316', '#10b981', '#f43f5e'];
        $typeDistribution = $campuses->groupBy('type')->map(fn ($items, $type) => [
            'label' => $type,
            'value' => $items->count(),
            'hex' => $typeColors[$type]['hex'] ?? $chartColors[0],
        ])->values();
        $roleRows = $roles->filter(fn ($role) => (int) $role->users_count > 0)->values()->map(fn ($role, $index) => [
            'label' => $role->name,
            'value' => (int) $role->users_count,
            'hex' => $chartColors[$index % count($chartColors)],
        ]);
        $roleTotal = max($roleRows->sum('value'), 1);
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
        $assignmentUsers = $users->map(fn ($user) => [
            'id' => $user->id,
            'update_url' => route('users.update', $user),
            'name' => $user->name,
            'title' => $user->title,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'church_id' => $user->church_id,
            'campus_id' => $user->campus_id,
            'avatar' => $user->avatar_src,
            'role_ids' => $user->roles->pluck('id')->values(),
            'search' => strtolower($user->name.' '.$user->email.' '.$user->phone),
        ])->values();
    @endphp

    <div
        x-data="campusDirectory(@js($assignmentUsers), '{{ url('settings/users') }}')"
        class="grid gap-4 xl:grid-cols-[1fr_430px]"
    >
        <main class="min-w-0 space-y-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div class="grid size-14 place-items-center rounded-full bg-violet-100 text-violet-600">
                        <i data-lucide="network" class="size-7"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-950">Churches & Campuses</h1>
                        <p class="text-sm text-slate-500">Manage church branches, campuses and user assignments across your organization.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-on:click="importOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:border-violet-200">
                        <i data-lucide="upload" class="size-4"></i>
                        Import Churches
                    </button>
                    <button type="button" x-on:click="addOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                        <i data-lucide="plus" class="size-4"></i>
                        Add Church / Campus
                        <i data-lucide="chevron-up" class="size-4 rotate-180"></i>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <x-stat-card :metric="['label' => 'Total Churches', 'value' => number_format($stats['churches']), 'change' => null, 'period' => 'Active branches', 'icon' => 'network', 'color' => 'purple', 'route' => 'campuses.index']" />
                <x-stat-card :metric="['label' => 'Total Campuses', 'value' => number_format($stats['campuses']), 'change' => null, 'period' => 'Across all churches', 'icon' => 'building-2', 'color' => 'blue', 'route' => 'campuses.index']" />
                <x-stat-card :metric="['label' => 'Total Users Assigned', 'value' => number_format($stats['assigned']), 'change' => '8.2%', 'period' => 'vs last month', 'icon' => 'user-check', 'color' => 'emerald', 'route' => 'users.index']" />
                <x-stat-card :metric="['label' => 'Active Assignments', 'value' => number_format($stats['active']), 'change' => null, 'period' => 'Active users', 'icon' => 'user-plus', 'color' => 'orange', 'route' => 'users.index']" />
                <x-stat-card :metric="['label' => 'Pending Assignments', 'value' => number_format($stats['pending']), 'change' => null, 'period' => 'Awaiting action', 'icon' => 'shield-alert', 'color' => 'rose', 'route' => 'campuses.index']" />
                <x-stat-card :metric="['label' => 'Unassigned Users', 'value' => number_format($stats['unassigned']), 'change' => null, 'period' => 'Not yet assigned', 'icon' => 'user-plus', 'color' => 'amber', 'route' => 'users.index']" />
            </div>

            <section class="dashboard-card p-0">
                <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_160px_180px_160px_auto_auto]">
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                        <input x-model="search" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 pr-9 text-sm" placeholder="Search churches or campuses...">
                    </div>
                    <select x-model="church" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Churches</option>
                        @foreach($churches as $church)
                            <option value="{{ $church->id }}">{{ $church->name }}</option>
                        @endforeach
                    </select>
                    <select x-model="type" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Campus Types</option>
                        @foreach($campuses->pluck('type')->unique()->sort()->values() as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select x-model="status" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button type="button" x-on:click="moreFiltersOpen = ! moreFiltersOpen" x-bind:class="moreFiltersOpen ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-700'" class="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold">
                        <i data-lucide="settings" class="size-4"></i>
                        More Filters
                    </button>
                    <button type="button" x-on:click="clearFilters()" class="px-3 py-2.5 text-sm font-semibold text-violet-600">Clear</button>
                </div>
                <div x-cloak x-show="moreFiltersOpen" class="grid gap-3 border-b border-slate-100 px-4 pb-4 md:grid-cols-[180px_1fr]">
                    <label class="space-y-1 text-sm">
                        <span class="text-xs font-semibold text-slate-500">Minimum Capacity</span>
                        <input x-model="minCapacity" type="number" min="0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Any capacity">
                    </label>
                    <div class="flex items-end text-xs text-slate-500">Filters update the table instantly and keep all campus records loaded.</div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[1080px]">
                        <thead>
                            <tr>
                                <th class="w-9"></th>
                                <th>Church / Campus</th>
                                <th>Campus Type</th>
                                <th>Status</th>
                                <th>Branch Pastor</th>
                                <th>Church Administrator</th>
                                <th class="text-center">Total Users</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($campuses as $campus)
                                @php
                                    $pastor = $campus->users->first(fn ($user) => $user->roles->contains('name', 'Branch Pastor')) ?? $campus->users->first();
                                    $administrator = $campus->users->first(fn ($user) => $user->roles->contains('name', 'Church Administrator')) ?? $campus->users->skip(1)->first();
                                    $tone = $typeColors[$campus->type] ?? ['bg' => 'bg-violet-50', 'text' => 'text-violet-700', 'ring' => 'ring-violet-200'];
                                    $campusSearch = strtolower($campus->church->name.' '.$campus->name.' '.$campus->type.' '.$campus->status.' '.$campus->address);
                                @endphp
                                <tr
                                    x-show="matchesCampus($el)"
                                    data-campus-row
                                    data-search="{{ $campusSearch }}"
                                    data-church="{{ $campus->church_id }}"
                                    data-type="{{ $campus->type }}"
                                    data-status="{{ $campus->status }}"
                                    data-capacity="{{ $campus->capacity ?? 0 }}"
                                >
                                    <td><button type="button" x-on:click="toggleCampus('{{ $campus->id }}')" class="grid size-7 place-items-center rounded-md text-slate-500 hover:bg-slate-50"><i data-lucide="chevron-right" x-bind:class="expandedCampusId === '{{ $campus->id }}' ? 'rotate-90' : ''" class="size-4 transition"></i></button></td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="grid size-9 place-items-center rounded-full bg-violet-50 text-violet-600">
                                                <i data-lucide="network" class="size-5"></i>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $campus->church->name }}</div>
                                                <div class="text-xs text-slate-500">{{ $campus->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $tone['bg'] }} {{ $tone['text'] }} {{ $tone['ring'] }}">{{ $campus->type }}</span></td>
                                    <td><x-status-badge :status="$campus->status" /></td>
                                    <td>
                                        @if ($pastor)
                                            <div class="flex items-center gap-2">
                                                <img src="{{ $pastor->avatar_src }}" alt="{{ $pastor->name }}" class="size-7 rounded-full object-cover">
                                                <span class="text-sm text-slate-700">{{ $pastor->name }}</span>
                                            </div>
                                        @else
                                            <span class="text-sm text-slate-400">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($administrator)
                                            <div class="flex items-center gap-2">
                                                <img src="{{ $administrator->avatar_src }}" alt="{{ $administrator->name }}" class="size-7 rounded-full object-cover">
                                                <span class="text-sm text-slate-700">{{ $administrator->name }}</span>
                                            </div>
                                        @else
                                            <span class="text-sm text-slate-400">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="text-center font-semibold text-slate-900">{{ number_format($campus->users_count) }}</td>
                                    <td>
                                        <div class="flex items-center gap-2 text-sm text-slate-600">
                                            <i data-lucide="map-pin" class="size-4 text-slate-500"></i>
                                            {{ $campus->address }}
                                        </div>
                                    </td>
                                </tr>
                                <tr x-cloak x-show="expandedCampusId === '{{ $campus->id }}' && matchesCampus($el.previousElementSibling)">
                                    <td></td>
                                    <td colspan="7" class="bg-slate-50/70">
                                        <div class="grid gap-3 py-3 md:grid-cols-4">
                                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                <div class="text-xs text-slate-500">Members</div>
                                                <div class="mt-1 text-lg font-semibold text-slate-950">{{ number_format($campus->members_count) }}</div>
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                <div class="text-xs text-slate-500">Capacity</div>
                                                <div class="mt-1 text-lg font-semibold text-slate-950">{{ $campus->capacity ? number_format($campus->capacity) : 'N/A' }}</div>
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                <div class="text-xs text-slate-500">Service Location</div>
                                                <div class="mt-1 truncate text-sm font-semibold text-slate-950">{{ $campus->metadata['service_location'] ?? 'Main Auditorium' }}</div>
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                <div class="text-xs text-slate-500">Sunday Service</div>
                                                <div class="mt-1 truncate text-sm font-semibold text-slate-950">{{ $campus->metadata['sunday_service'] ?? '9:00 AM' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
                    <span>Showing <span x-text="visibleCampusCount()"></span> of {{ number_format($campuses->count()) }} campuses</span>
                    <div class="flex items-center gap-2">
                        <button class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-400"><i data-lucide="arrow-left" class="size-4"></i></button>
                        <span class="grid size-9 place-items-center rounded-lg bg-violet-600 text-sm font-semibold text-white">1</span>
                        <button class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600"><i data-lucide="arrow-right" class="size-4"></i></button>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[1fr_1fr_1fr]">
                <section class="dashboard-card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Church & Campus Distribution</h2>
                        <a href="{{ route('campuses.index') }}" class="text-xs font-semibold text-violet-600">View Map</a>
                    </div>
                    <div class="relative h-56 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                        <svg class="absolute inset-0 size-full" viewBox="0 0 640 310" aria-hidden="true">
                            <path d="M101 123l45-26 86 10 44-16 76 9 58 31 78-9 49 24 13 45-29 42-80 9-71 32-84-14-58 20-79-35-43-5-29-58z" fill="#e6ebf2"/>
                            <path d="M153 107l40 37-19 72-67 11-31-44 21-55zM225 112l50-20 16 73-32 63-61-17 13-65zM312 106l55 9 30 77-38 55-54-18-10-68zM400 132l86-7 54 35-19 63-82 13-34-49z" fill="#f3f6fa" stroke="#d8e1ee" stroke-width="2"/>
                            <path d="M250 244l56 14 72-8 20 34-69 17-69-12z" fill="#edf2f7"/>
                        </svg>
                        @foreach ($campuses as $campus)
                            @php($marker = $typeColors[$campus->type]['hex'] ?? '#6d4aff')
                            <span class="absolute grid size-6 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full text-white shadow-md ring-4 ring-white" style="left: {{ $campus->map_x ?? 50 }}%; top: {{ $campus->map_y ?? 50 }}%; background: {{ $marker }}">
                                <i data-lucide="map-pin" class="size-3"></i>
                            </span>
                        @endforeach
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                        @foreach ($typeDistribution as $item)
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2"><span class="size-2 rounded-full" style="background: {{ $item['hex'] }}"></span><span class="truncate">{{ $item['label'] }}</span></span>
                                <span class="font-semibold text-slate-900">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Role Allocation Overview</h2>
                        <a href="{{ route('roles.index') }}" class="text-xs font-semibold text-violet-600">View Details</a>
                    </div>
                    <div class="grid items-center gap-4 md:grid-cols-[150px_1fr]">
                        <div class="relative mx-auto size-36">
                            <div class="absolute inset-0 rounded-full p-[15px]" style="background: {{ $donutGradient($roleRows, $roleTotal) }}">
                                <div class="size-full rounded-full bg-white"></div>
                            </div>
                            <canvas
                                class="relative size-full"
                                data-chart="doughnut"
                                data-labels='@js($roleRows->pluck('label'))'
                                data-values='@js($roleRows->pluck('value'))'
                                data-colors='@js($roleRows->pluck('hex'))'
                            ></canvas>
                        </div>
                        <div class="space-y-2">
                            @foreach ($roleRows->take(5) as $role)
                                @php($percent = round(((int) $role['value'] / $roleTotal) * 100, 1))
                                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3 text-sm">
                                    <span class="flex min-w-0 items-center gap-2 text-slate-600"><span class="size-2.5 rounded-full" style="background: {{ $role['hex'] }}"></span><span class="truncate">{{ $role['label'] }}</span></span>
                                    <span class="font-semibold text-slate-900">{{ number_format($role['value']) }}</span>
                                    <span class="w-12 text-right text-xs text-slate-500">{{ $percent }}%</span>
                                </div>
                            @endforeach
                            <div class="grid grid-cols-[1fr_auto_auto] border-t border-slate-100 pt-2 text-sm">
                                <span class="font-semibold text-slate-700">Total</span>
                                <span class="font-semibold text-slate-900">{{ number_format($roleRows->sum('value')) }}</span>
                                <span class="w-12 text-right text-xs text-slate-500">100%</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Assignment Overview</h2>
                        <select class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600">
                            <option>This Month</option>
                            <option>This Quarter</option>
                        </select>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="user-check" class="size-4"></i></span><span class="text-slate-600">New Assignments</span><span class="font-semibold text-slate-900">{{ $users->where('created_at', '>=', now()->subMonth())->count() }}</span><span class="text-xs font-semibold text-emerald-600">+12.5%</span></div>
                        <div class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-blue-50 text-blue-600"><i data-lucide="badge-check" class="size-4"></i></span><span class="text-slate-600">Updated Assignments</span><span class="font-semibold text-slate-900">{{ $users->whereNotNull('campus_id')->count() }}</span><span class="text-xs font-semibold text-emerald-600">+8.3%</span></div>
                        <div class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-rose-50 text-rose-600"><i data-lucide="x" class="size-4"></i></span><span class="text-slate-600">Removed Assignments</span><span class="font-semibold text-slate-900">{{ $users->whereNull('campus_id')->count() }}</span><span class="text-xs font-semibold text-rose-600">+5.1%</span></div>
                        <div class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-orange-50 text-orange-600"><i data-lucide="user-plus" class="size-4"></i></span><span class="text-slate-600">Pending Approvals</span><span class="font-semibold text-slate-900">{{ $stats['pending'] }}</span><span class="text-xs font-semibold text-emerald-600">+15.2%</span></div>
                        <div class="grid grid-cols-[1fr_auto_auto] border-t border-slate-100 pt-3"><span class="font-semibold text-slate-700">Total Changes</span><span class="font-semibold text-slate-900">{{ $users->count() + $stats['pending'] }}</span><span class="text-xs font-semibold text-emerald-600">+9.8%</span></div>
                    </div>
                </section>
            </div>
        </main>

        <aside class="dashboard-card h-fit xl:sticky xl:top-20">
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Assign User to Church & Campus</h2>
                    <p class="mt-1 text-sm text-slate-500">Assign a user to a church and set their access scope.</p>
                </div>
                <button type="button" x-on:click="resetAssignment()" class="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-5"></i></button>
            </div>

            <form method="POST" x-bind:action="assignmentAction()" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" x-bind:value="selectedUser().name">
                <input type="hidden" name="email" x-bind:value="selectedUser().email">
                <input type="hidden" name="title" x-bind:value="selectedUser().title">
                <input type="hidden" name="phone" x-bind:value="selectedUser().phone">
                <input type="hidden" name="status" x-bind:value="selectedUser().status">

                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid size-6 place-items-center rounded-md bg-violet-600 text-xs font-semibold text-white">1</span>
                        <span class="text-sm font-semibold text-slate-900">Select User</span>
                    </div>
                    <div class="relative mb-3">
                        <input x-model="userSearch" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 pr-9 text-sm" placeholder="Search by name, email or phone...">
                        <i data-lucide="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                    </div>
                    <div class="max-h-48 space-y-2 overflow-y-auto pr-1">
                        <template x-for="user in filteredUsers()" :key="user.id">
                            <button type="button" x-on:click="selectUser(user)" class="flex w-full items-center gap-3 rounded-lg border p-3 text-left transition" x-bind:class="String(selectedUserId) === String(user.id) ? 'border-violet-200 bg-violet-50/60' : 'border-slate-200 bg-white hover:border-violet-200'">
                                <img x-bind:src="user.avatar" x-bind:alt="user.name" class="size-10 rounded-full object-cover">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-slate-900" x-text="user.name"></span>
                                    <span class="block truncate text-xs text-slate-500" x-text="user.email"></span>
                                    <span class="block text-xs text-slate-500" x-text="user.phone"></span>
                                </span>
                                <i data-lucide="check" class="size-4 text-violet-600" x-show="String(selectedUserId) === String(user.id)"></i>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid size-6 place-items-center rounded-md bg-violet-600 text-xs font-semibold text-white">2</span>
                        <span class="text-sm font-semibold text-slate-900">Select Church</span>
                    </div>
                    <select name="church_id" x-model="selectedChurchId" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        @foreach($churches as $church)
                            <option value="{{ $church->id }}">{{ $church->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid size-6 place-items-center rounded-md bg-violet-600 text-xs font-semibold text-white">3</span>
                        <span class="text-sm font-semibold text-slate-900">Select Campus Access</span>
                    </div>
                    <label class="mb-2 block text-xs font-semibold text-slate-600">Default Campus</label>
                    <select name="campus_id" x-model="selectedCampusId" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}">{{ $campus->name }} - {{ $campus->city }}</option>
                        @endforeach
                    </select>
                    <div class="mt-3 space-y-2">
                        <label class="flex items-start gap-3 rounded-lg p-2 hover:bg-slate-50"><input type="radio" x-model="accessScope" value="single" class="mt-1 text-violet-600"><span><span class="block text-sm font-semibold text-slate-900">Single Campus</span><span class="block text-xs text-slate-500">User will have access to one campus only</span></span></label>
                        <label class="flex items-start gap-3 rounded-lg p-2 hover:bg-slate-50"><input type="radio" x-model="accessScope" value="multiple" class="mt-1 text-violet-600"><span><span class="block text-sm font-semibold text-slate-900">Multiple Campuses</span><span class="block text-xs text-slate-500">User will have access to selected campuses</span></span></label>
                        <label class="flex items-start gap-3 rounded-lg p-2 hover:bg-slate-50"><input type="radio" x-model="accessScope" value="all" class="mt-1 text-violet-600"><span><span class="block text-sm font-semibold text-slate-900">All Campuses</span><span class="block text-xs text-slate-500">User will have access to all campuses in this church</span></span></label>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid size-6 place-items-center rounded-md bg-violet-600 text-xs font-semibold text-white">4</span>
                        <span class="text-sm font-semibold text-slate-900">Assign Role & Permissions</span>
                    </div>
                    <label class="mb-2 block text-xs font-semibold text-slate-600">Role</label>
                    <select name="roles[]" x-model="selectedRoleId" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <label class="mb-2 mt-3 block text-xs font-semibold text-slate-600">Permission Template</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option>Default role permissions</option>
                        <option>Restricted campus access</option>
                        <option>Full campus management</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-3">
                    <button type="button" x-on:click="resetAssignment()" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white">
                        <i data-lucide="check" class="size-4"></i>
                        Assign User
                    </button>
                </div>
            </form>
        </aside>

        <div x-cloak x-show="addOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
            <form method="POST" action="{{ route('campuses.store') }}" class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl">
                @csrf
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-950">Add Church / Campus</h2>
                    <button type="button" x-on:click="addOpen = false" class="text-slate-400"><i data-lucide="x" class="size-5"></i></button>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">Existing Church</span><select name="church_id" class="w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">Create new church</option>@foreach($churches as $church)<option value="{{ $church->id }}">{{ $church->name }}</option>@endforeach</select></label>
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">New Church Name</span><input name="church_name" class="w-full rounded-lg border border-slate-200 px-3 py-2" placeholder="Only needed for new church"></label>
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">Campus Name</span><input name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2" placeholder="Main Campus"></label>
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">Type</span><select name="type" required class="w-full rounded-lg border border-slate-200 px-3 py-2"><option>Main Campus</option><option>Regional Campus</option><option>City Campus</option><option>Online Campus</option><option>Ministry Campus</option></select></label>
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">City</span><input name="city" required class="w-full rounded-lg border border-slate-200 px-3 py-2" placeholder="Dallas"></label>
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">Country</span><input name="country" required value="USA" class="w-full rounded-lg border border-slate-200 px-3 py-2"></label>
                    <label class="space-y-1 text-sm md:col-span-2"><span class="font-semibold text-slate-700">Address</span><input name="address" required class="w-full rounded-lg border border-slate-200 px-3 py-2" placeholder="Dallas, TX"></label>
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">Capacity</span><input name="capacity" type="number" min="1" class="w-full rounded-lg border border-slate-200 px-3 py-2"></label>
                    <label class="space-y-1 text-sm"><span class="font-semibold text-slate-700">Status</span><select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" x-on:click="addOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Save Campus</button>
                </div>
            </form>
        </div>

        <div x-cloak x-show="importOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
            <form method="POST" action="{{ route('campuses.import') }}" enctype="multipart/form-data" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-2xl">
                @csrf
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-950">Import Churches</h2>
                    <button type="button" x-on:click="importOpen = false" class="text-slate-400"><i data-lucide="x" class="size-5"></i></button>
                </div>
                <input name="import_file" type="file" accept=".csv,.txt" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <p class="mt-2 text-xs text-slate-500">CSV columns: church, campus, type, city, country, status.</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" x-on:click="importOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Import</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
