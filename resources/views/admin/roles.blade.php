<x-app-layout title="Roles & Permissions" :breadcrumbs="$breadcrumbs">
    @php
        $orderedRoleNames = array_keys(config('access.roles'));
        $roles = $roles->sortBy(fn ($role) => array_search($role->name, $orderedRoleNames, true) === false ? 999 : array_search($role->name, $orderedRoleNames, true))->values();
        $initialRole = $roles->first();
        $roleIcons = [
            'Super Administrator' => ['icon' => 'shield-check', 'class' => 'bg-violet-50 text-violet-600 border-violet-100'],
            'Church Administrator' => ['icon' => 'users-round', 'class' => 'bg-emerald-50 text-emerald-600 border-emerald-100'],
            'Senior Pastor' => ['icon' => 'users', 'class' => 'bg-orange-50 text-orange-600 border-orange-100'],
            'Branch Pastor' => ['icon' => 'badge-check', 'class' => 'bg-blue-50 text-blue-600 border-blue-100'],
            'Finance Officer' => ['icon' => 'badge-dollar-sign', 'class' => 'bg-emerald-50 text-emerald-600 border-emerald-100'],
            'Membership Officer' => ['icon' => 'users', 'class' => 'bg-rose-50 text-rose-600 border-rose-100'],
            'Asset Manager' => ['icon' => 'package-check', 'class' => 'bg-violet-50 text-violet-600 border-violet-100'],
            'Book Store Manager' => ['icon' => 'book-open', 'class' => 'bg-orange-50 text-orange-600 border-orange-100'],
            'Ministry Leader' => ['icon' => 'user-check', 'class' => 'bg-blue-50 text-blue-600 border-blue-100'],
            'Staff' => ['icon' => 'user-round', 'class' => 'bg-slate-50 text-slate-600 border-slate-100'],
            'Viewer' => ['icon' => 'eye', 'class' => 'bg-violet-50 text-violet-600 border-violet-100'],
        ];
        $permissionDefinitions = collect([
            'Core Access' => [
                ['permission' => 'view dashboard', 'label' => 'Dashboard Access', 'help' => 'Open the main dashboard and landing workspace.', 'icon' => 'layout-dashboard', 'class' => 'bg-violet-50 text-violet-600'],
                ['permission' => 'view reports', 'label' => 'Reports & Analytics', 'help' => 'View organization-level reports and analytics.', 'icon' => 'chart-column', 'class' => 'bg-blue-50 text-blue-600'],
                ['permission' => 'view audit log', 'label' => 'Audit Logs', 'help' => 'Review system activity, authentication events, and security logs.', 'icon' => 'shield-check', 'class' => 'bg-emerald-50 text-emerald-600'],
            ],
            'Administration' => [
                ['permission' => 'manage users', 'label' => 'Users', 'help' => 'Create, update, invite, deactivate, and assign users.', 'icon' => 'users-round', 'class' => 'bg-violet-50 text-violet-600'],
                ['permission' => 'manage roles', 'label' => 'Roles', 'help' => 'Create roles, clone roles, and update role assignments.', 'icon' => 'shield-check', 'class' => 'bg-blue-50 text-blue-600'],
                ['permission' => 'manage permissions', 'label' => 'Permission Policies', 'help' => 'Maintain permission rules and access policies.', 'icon' => 'key-round', 'class' => 'bg-orange-50 text-orange-600'],
                ['permission' => 'manage settings', 'label' => 'System Settings', 'help' => 'Change system configuration, integrations, and module settings.', 'icon' => 'settings', 'class' => 'bg-slate-50 text-slate-600'],
                ['permission' => 'manage campuses', 'label' => 'Churches & Campuses', 'help' => 'Manage church, branch, campus, and user assignment scopes.', 'icon' => 'landmark', 'class' => 'bg-teal-50 text-teal-600'],
            ],
            'People & Ministry' => [
                ['permission' => 'manage members', 'label' => 'Members', 'help' => 'Manage member profiles, families, and care information.', 'icon' => 'users', 'class' => 'bg-violet-50 text-violet-600'],
                ['permission' => 'manage attendance', 'label' => 'Attendance', 'help' => 'Record, edit, and review attendance data.', 'icon' => 'clipboard-check', 'class' => 'bg-emerald-50 text-emerald-600'],
                ['permission' => 'manage prayer', 'label' => 'Prayer Requests', 'help' => 'Manage prayer request intake and follow-up.', 'icon' => 'hand-heart', 'class' => 'bg-blue-50 text-blue-600'],
                ['permission' => 'manage volunteers', 'label' => 'Volunteers', 'help' => 'Manage volunteer assignments, teams, and scheduling.', 'icon' => 'user-check', 'class' => 'bg-orange-50 text-orange-600'],
                ['permission' => 'manage ministries', 'label' => 'Ministries', 'help' => 'Manage ministries, departments, and program involvement.', 'icon' => 'landmark', 'class' => 'bg-teal-50 text-teal-600'],
                ['permission' => 'manage youth', 'label' => 'Child & Youth', 'help' => 'Manage child and youth ministry records.', 'icon' => 'baby', 'class' => 'bg-violet-50 text-violet-600'],
                ['permission' => 'manage counselling', 'label' => 'Counselling', 'help' => 'Manage counselling records and pastoral care workflows.', 'icon' => 'heart-handshake', 'class' => 'bg-rose-50 text-rose-600'],
                ['permission' => 'manage staff', 'label' => 'HR & Staff', 'help' => 'Manage staff records, employment details, and HR data.', 'icon' => 'briefcase-medical', 'class' => 'bg-blue-50 text-blue-600'],
            ],
            'Operations' => [
                ['permission' => 'manage events', 'label' => 'Programs & Events', 'help' => 'Create events, sessions, order of program, and schedules.', 'icon' => 'calendar-days', 'class' => 'bg-violet-50 text-violet-600'],
                ['permission' => 'manage communications', 'label' => 'Communications', 'help' => 'Send messages, campaigns, reminders, and notifications.', 'icon' => 'message-square-text', 'class' => 'bg-blue-50 text-blue-600'],
                ['permission' => 'manage workflows', 'label' => 'Workflow & Approvals', 'help' => 'Create approval flows, approve requests, and manage workflow templates.', 'icon' => 'git-branch', 'class' => 'bg-violet-50 text-violet-600'],
                ['permission' => 'view leadership reports', 'label' => 'Pastor & Leadership Reports', 'help' => 'Submit, view, and review leadership reports.', 'icon' => 'file-chart-column', 'class' => 'bg-blue-50 text-blue-600'],
                ['permission' => 'manage feedback', 'label' => 'Feedback System', 'help' => 'Review survey responses, feedback, and follow-up items.', 'icon' => 'message-square-check', 'class' => 'bg-emerald-50 text-emerald-600'],
            ],
            'Resources & Finance' => [
                ['permission' => 'manage finance', 'label' => 'Giving & Finance', 'help' => 'Manage giving, financial reports, budgets, and expenses.', 'icon' => 'badge-dollar-sign', 'class' => 'bg-emerald-50 text-emerald-600'],
                ['permission' => 'manage assets', 'label' => 'Asset Inventory', 'help' => 'Manage physical assets, inventory, status, and assignments.', 'icon' => 'package-check', 'class' => 'bg-violet-50 text-violet-600'],
                ['permission' => 'manage facilities', 'label' => 'Facilities', 'help' => 'Manage rooms, facilities, reservations, and equipment.', 'icon' => 'building-2', 'class' => 'bg-blue-50 text-blue-600'],
                ['permission' => 'manage bookstore', 'label' => 'Book Store', 'help' => 'Manage bookstore inventory, sales, and reporting.', 'icon' => 'book-open', 'class' => 'bg-orange-50 text-orange-600'],
                ['permission' => 'manage media', 'label' => 'Sermons & Media', 'help' => 'Manage sermons, media files, and streaming content.', 'icon' => 'monitor-play', 'class' => 'bg-slate-50 text-slate-600'],
            ],
        ]);
        $definedPermissionNames = $permissionDefinitions->flatten(1)->pluck('permission');
        $extraPermissions = $permissions
            ->reject(fn ($permission) => $definedPermissionNames->contains($permission->name))
            ->map(fn ($permission) => [
                'permission' => $permission->name,
                'label' => Str::headline($permission->name),
                'help' => 'Additional configured permission.',
                'icon' => 'shield-check',
                'class' => 'bg-slate-50 text-slate-600',
            ])
            ->values();
        if ($extraPermissions->isNotEmpty()) {
            $permissionDefinitions->put('Other Permissions', $extraPermissions->all());
        }
        $permissionGroups = $permissionDefinitions->map(fn ($rows) => collect($rows)
            ->map(fn ($row) => [...$row, 'model' => $permissions->firstWhere('name', $row['permission'])])
            ->filter(fn ($row) => $row['model'] !== null)
            ->values()
        )->filter(fn ($rows) => $rows->isNotEmpty());
    @endphp

    <div class="space-y-4" x-data="roleDirectory('{{ $initialRole?->id }}')">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-full bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="shield-check" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Roles & Permissions</h1>
                    <p class="text-sm text-slate-500">Manage roles, define granular permissions, and control access across the system.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('roles.report') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="file-search" class="size-4"></i> Permission Reports
                </a>
                <button type="button" @click="openSelectedClone()" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-300 bg-white px-4 py-2.5 text-sm font-bold text-violet-700 hover:bg-violet-50">
                    <i data-lucide="package-plus" class="size-4"></i> Clone Role
                </button>
                <button type="button" @click="addOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i> Add Role
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <a href="{{ route('roles.index') }}" class="dashboard-card flex min-h-[104px] items-center gap-4 hover:shadow-md">
                <div class="grid size-12 place-items-center rounded-full bg-violet-50 text-violet-600"><i data-lucide="users" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Total Roles</div><div class="text-2xl font-black text-slate-950">{{ number_format($stats['roles']) }}</div><div class="text-xs text-slate-500">Active roles in system</div></div>
            </a>
            <a href="{{ route('roles.index') }}" class="dashboard-card flex min-h-[104px] items-center gap-4 hover:shadow-md">
                <div class="grid size-12 place-items-center rounded-full bg-emerald-50 text-emerald-600"><i data-lucide="users-round" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Custom Roles</div><div class="text-2xl font-black text-slate-950">{{ number_format($stats['custom']) }}</div><div class="text-xs text-slate-500">Non-system roles</div></div>
            </a>
            <a href="{{ route('users.index') }}" class="dashboard-card flex min-h-[104px] items-center gap-4 hover:shadow-md">
                <div class="grid size-12 place-items-center rounded-full bg-orange-50 text-orange-600"><i data-lucide="user-check" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Assigned Users</div><div class="text-2xl font-black text-slate-950">{{ number_format($stats['assigned']) }}</div><div class="text-xs text-slate-500">Users with role assignments</div></div>
            </a>
            <a href="{{ route('roles.index') }}" class="dashboard-card flex min-h-[104px] items-center gap-4 hover:shadow-md">
                <div class="grid size-12 place-items-center rounded-full bg-blue-50 text-blue-600"><i data-lucide="shield-check" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Permission Policies</div><div class="text-2xl font-black text-slate-950">{{ number_format($stats['permissions']) }}</div><div class="text-xs text-slate-500">Total permission rules</div></div>
            </a>
            <a href="{{ route('users.index') }}" class="dashboard-card flex min-h-[104px] items-center gap-4 hover:shadow-md">
                <div class="grid size-12 place-items-center rounded-full bg-rose-50 text-rose-600"><i data-lucide="shield-alert" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Restricted Access</div><div class="text-2xl font-black text-slate-950">{{ number_format($stats['restricted']) }}</div><div class="text-xs text-slate-500">Limited or custom restrictions</div></div>
            </a>
            <a href="{{ route('audit-logs.index') }}" class="dashboard-card flex min-h-[104px] items-center gap-4 hover:shadow-md">
                <div class="grid size-12 place-items-center rounded-full bg-violet-50 text-violet-600"><i data-lucide="calendar-days" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Last Updated</div><div class="text-lg font-black text-slate-950">{{ optional($stats['updated'])->format('M d, Y') ?? 'N/A' }}</div><div class="text-xs text-slate-500">By Super Administrator</div></div>
            </a>
        </div>

        <section class="dashboard-card p-0">
            <div class="grid gap-3 border-b border-slate-100 p-4 lg:grid-cols-[1fr_180px_180px_auto]">
                <div class="relative">
                    <input x-model.debounce.150ms="search" class="w-full rounded-lg border border-slate-200 py-2 pl-3 pr-10 text-sm" placeholder="Search roles by name or description...">
                    <i data-lucide="search" class="absolute right-3 top-2.5 size-4 text-slate-400"></i>
                </div>
                <label class="space-y-1">
                    <span class="text-[10px] font-black uppercase text-slate-500">Status</span>
                    <select x-model="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-[10px] font-black uppercase text-slate-500">Type</span>
                    <select x-model="type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All Types</option>
                        <option value="system">System</option>
                        <option value="custom">Custom</option>
                    </select>
                </label>
                <button type="button" @click="clearFilters()" class="self-end rounded-lg px-4 py-2 text-sm font-bold text-violet-600 hover:bg-violet-50">Clear Filters</button>
            </div>

            <div class="grid xl:grid-cols-[460px_1fr]">
                <aside class="border-r border-slate-100 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-black text-slate-950">Roles</h2>
                        <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-slate-500">
                            <span x-text="`${visibleCount()} roles found`"></span>
                            <i data-lucide="chevron-up" class="size-3 rotate-180"></i>
                        </button>
                    </div>
                    <div class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-100">
                        @foreach ($roles as $role)
                            @php
                                $roleIcon = $roleIcons[$role->name] ?? ['icon' => 'shield-check', 'class' => 'bg-slate-50 text-slate-600 border-slate-100'];
                                $type = $role->name === 'Super Administrator' ? 'system' : 'custom';
                                $searchText = Str::lower($role->name.' '.$role->description);
                            @endphp
                            <div
                                data-role-row
                                data-role-id="{{ $role->id }}"
                                data-role-name="{{ $role->name }}"
                                data-search="{{ $searchText }}"
                                data-status="active"
                                data-type="{{ $type }}"
                                x-show="matches($el)"
                                class="relative"
                            >
                                <button
                                    type="button"
                                    @click="selectRole('{{ $role->id }}')"
                                    class="flex w-full items-center gap-3 border-l-2 border-transparent p-3 pr-14 text-left hover:bg-violet-50"
                                    :class="selectedRole === '{{ $role->id }}' ? 'border-l-violet-600 bg-violet-50 ring-1 ring-inset ring-violet-200' : ''"
                                >
                                    <span class="grid size-10 shrink-0 place-items-center rounded-lg border {{ $roleIcon['class'] }}"><i data-lucide="{{ $roleIcon['icon'] }}" class="size-5"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-black text-slate-900">{{ $role->name }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $role->description }}</span>
                                    </span>
                                    <span class="w-20 shrink-0 text-right text-xs font-black text-slate-700">{{ number_format($role->users_count) }} {{ Str::plural('User', $role->users_count) }}</span>
                                </button>
                                <button type="button" @click.stop="menuOpen = menuOpen === '{{ $role->id }}' ? null : '{{ $role->id }}'" class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-slate-500 hover:bg-white">
                                    <i data-lucide="more-vertical" class="size-4"></i>
                                </button>
                                <div x-cloak x-show="menuOpen === '{{ $role->id }}'" x-transition @click.outside="menuOpen = null" class="absolute right-3 top-10 z-20 w-44 rounded-lg border border-slate-200 bg-white p-2 shadow-xl">
                                    <button type="button" @click="selectRole('{{ $role->id }}')" class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="eye" class="size-4"></i>View Role</button>
                                    <button type="button" @click="openClone('{{ $role->id }}', @js($role->name))" class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="package-plus" class="size-4"></i>Clone Role</button>
                                </div>
                            </div>
                        @endforeach
                        <div x-show="visibleCount() === 0" class="p-6 text-center text-sm font-semibold text-slate-500">No roles match the current filters.</div>
                    </div>
                    <div class="mt-4 text-sm text-slate-500">Showing 1 to <span x-text="visibleCount()"></span> of {{ $roles->count() }} roles</div>
                </aside>

                <section class="min-w-0 p-4">
                    @foreach ($roles as $role)
                        @php
                            $roleIcon = $roleIcons[$role->name] ?? ['icon' => 'shield-check', 'class' => 'bg-slate-50 text-slate-600 border-slate-100'];
                            $isSystem = $role->name === 'Super Administrator';
                        @endphp
                        <form x-show="selectedRole === '{{ $role->id }}'" method="POST" action="{{ route('roles.update', $role) }}" class="space-y-4">
                            @csrf
                            @method('PUT')
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="grid size-14 place-items-center rounded-full border {{ $roleIcon['class'] }}"><i data-lucide="{{ $roleIcon['icon'] }}" class="size-7"></i></div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-xl font-black text-slate-950">{{ $role->name }}</h2>
                                            <span class="rounded-full {{ $isSystem ? 'bg-emerald-100 text-emerald-700' : 'bg-violet-100 text-violet-700' }} px-3 py-1 text-xs font-black">{{ $isSystem ? 'System Role' : 'Custom Role' }}</span>
                                        </div>
                                        <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ $role->description }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm md:grid-cols-4">
                                    <div><div class="text-xs font-black text-slate-500">Users Assigned</div><div class="mt-1 font-black text-slate-950">{{ number_format($role->users_count) }} {{ Str::plural('User', $role->users_count) }}</div></div>
                                    <div><div class="text-xs font-black text-slate-500">Type</div><div class="mt-1 font-black text-slate-950">{{ $isSystem ? 'System' : 'Custom' }}</div></div>
                                    <div><div class="text-xs font-black text-slate-500">Status</div><div class="mt-1"><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700">Active</span></div></div>
                                    <div><div class="text-xs font-black text-slate-500">Last Updated</div><div class="mt-1 font-black text-slate-950">{{ $role->updated_at?->format('M d, Y') }}</div><div class="text-xs text-slate-500">{{ $role->updated_at?->format('h:i A') }}</div></div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200">
                                <div class="flex flex-col gap-2 border-b border-slate-100 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="text-sm font-black text-slate-950">Permission Policies</div>
                                        <p class="mt-1 text-xs text-slate-500">Each switch maps to one real database permission used by authorization checks.</p>
                                    </div>
                                    @if ($isSystem)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-100">
                                            <i data-lucide="shield-check" class="size-3.5"></i>
                                            Always full access
                                        </span>
                                    @endif
                                </div>
                                <div class="space-y-5 p-4">
                                    @foreach ($permissionGroups as $groupName => $groupRows)
                                        <div>
                                            <h3 class="mb-3 text-xs font-black uppercase tracking-wide text-slate-500">{{ $groupName }}</h3>
                                            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                                                @foreach ($groupRows as $row)
                                                    @php
                                                        $hasPermission = $isSystem || $role->permissions->contains($row['model']);
                                                    @endphp
                                                    <label class="flex min-h-[92px] cursor-pointer items-start gap-3 rounded-lg border border-slate-100 bg-white p-3 transition hover:border-violet-200 hover:bg-violet-50/40">
                                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $row['class'] }}"><i data-lucide="{{ $row['icon'] }}" class="size-5"></i></span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block text-sm font-black text-slate-900">{{ $row['label'] }}</span>
                                                            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $row['help'] }}</span>
                                                            <span class="mt-2 block font-mono text-[11px] text-slate-400">{{ $row['permission'] }}</span>
                                                        </span>
                                                        <input
                                                            type="checkbox"
                                                            name="permissions[]"
                                                            value="{{ $row['model']->id }}"
                                                            @checked($hasPermission)
                                                            @disabled($isSystem)
                                                            class="mt-1 size-4 shrink-0 rounded border-slate-300 text-violet-600"
                                                        >
                                                        @if ($isSystem)
                                                            <input type="hidden" name="permissions[]" value="{{ $row['model']->id }}">
                                                        @endif
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 rounded-lg border border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex flex-wrap gap-6 text-sm font-semibold text-slate-600">
                                    <span class="inline-flex items-center gap-2"><span class="grid size-4 place-items-center rounded bg-violet-600 text-white"><i data-lucide="check" class="size-3"></i></span>Full Access</span>
                                    <span class="inline-flex items-center gap-2"><span class="size-4 rounded border border-slate-300 bg-white"></span>No Access</span>
                                </div>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="submit" form="reset-role-{{ $role->id }}" class="rounded-lg border border-violet-300 px-5 py-2 text-sm font-black text-violet-700 hover:bg-violet-50">Reset to Default</button>
                                    <button type="submit" class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-black text-white hover:bg-violet-700">Save Changes</button>
                                </div>
                            </div>
                        </form>
                        <form id="reset-role-{{ $role->id }}" method="POST" action="{{ route('roles.reset', $role) }}" class="hidden">
                            @csrf
                            @method('PUT')
                        </form>
                    @endforeach
                </section>
            </div>
        </section>

        <div x-cloak x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/40 p-4" @keydown.escape.window="addOpen = false">
            <div class="my-6 w-full max-w-2xl rounded-xl bg-white shadow-2xl" @click.outside="addOpen = false">
                <div class="flex items-start justify-between border-b border-slate-100 p-5">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Add Role</h2>
                        <p class="mt-1 text-sm text-slate-500">Create a custom role and assign module permissions.</p>
                    </div>
                    <button type="button" @click="addOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
                </div>
                <form method="POST" action="{{ route('roles.store') }}" class="space-y-4 p-5">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Role Name
                            <input name="name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500 sm:col-span-2">Description
                            <input name="description" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" placeholder="Describe the role scope">
                        </label>
                    </div>
                    <div class="max-h-[360px] space-y-4 overflow-y-auto rounded-lg border border-slate-100 p-3">
                        @foreach ($permissionGroups as $groupName => $groupRows)
                            <div>
                                <h3 class="mb-2 text-[10px] font-black uppercase tracking-wide text-slate-500">{{ $groupName }}</h3>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($groupRows as $row)
                                        <label class="flex items-start gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-violet-50/50">
                                            <input type="checkbox" name="permissions[]" value="{{ $row['model']->id }}" class="mt-0.5 rounded border-slate-300 text-violet-600">
                                            <span>
                                                <span class="block">{{ $row['label'] }}</span>
                                                <span class="block text-xs font-normal text-slate-500">{{ $row['help'] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" @click="addOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">Create Role</button>
                    </div>
                </form>
            </div>
        </div>

        @foreach ($roles as $role)
            <div x-cloak x-show="cloneOpen && cloneRoleId === '{{ $role->id }}'" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @keydown.escape.window="cloneOpen = false">
                <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl" @click.outside="cloneOpen = false">
                    <div class="flex items-start justify-between border-b border-slate-100 p-5">
                        <div>
                            <h2 class="text-lg font-black text-slate-950">Clone Role</h2>
                            <p class="mt-1 text-sm text-slate-500">Copy permissions from {{ $role->name }}.</p>
                        </div>
                        <button type="button" @click="cloneOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
                    </div>
                    <form method="POST" action="{{ route('roles.clone', $role) }}" class="space-y-4 p-5">
                        @csrf
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">New Role Name
                            <input name="name" x-model="cloneRoleName" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required>
                        </label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Description
                            <input name="description" value="Cloned from {{ $role->name }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">
                        </label>
                        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                            <button type="button" @click="cloneOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">Clone Role</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
