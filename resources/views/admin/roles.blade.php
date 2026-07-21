<x-app-layout title="Roles & Permissions" :breadcrumbs="$breadcrumbs">
    @php
        $orderedRoleNames = array_keys(config('access.roles'));
        $roles = $roles->sortBy(fn ($role) => array_search($role->name, $orderedRoleNames, true) === false ? 999 : array_search($role->name, $orderedRoleNames, true))->values();
        $initialRole = $roles->first();
        $columns = ['View', 'Create', 'Edit', 'Delete', 'Export', 'Approve', 'Manage'];
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
        $moduleRows = collect([
            ['label' => 'Members', 'permission' => 'manage members', 'icon' => 'users', 'class' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Attendance', 'permission' => 'manage attendance', 'icon' => 'clipboard-check', 'class' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Giving & Finance', 'permission' => 'manage finance', 'icon' => 'badge-dollar-sign', 'class' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Asset Inventory', 'permission' => 'manage assets', 'icon' => 'package-check', 'class' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Book Store', 'permission' => 'manage bookstore', 'icon' => 'book-open', 'class' => 'bg-teal-50 text-teal-600'],
            ['label' => 'Ministries', 'permission' => 'manage ministries', 'icon' => 'landmark', 'class' => 'bg-blue-50 text-blue-600'],
            ['label' => 'Communications', 'permission' => 'manage communications', 'icon' => 'message-square-text', 'class' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Feedback System', 'permission' => 'manage feedback', 'icon' => 'message-square-check', 'class' => 'bg-slate-50 text-slate-600'],
            ['label' => 'Pastor & Leadership Reports', 'permission' => 'view leadership reports', 'icon' => 'file-chart-column', 'class' => 'bg-blue-50 text-blue-600'],
            ['label' => 'HR & Staff', 'permission' => 'manage staff', 'icon' => 'users-round', 'class' => 'bg-blue-50 text-blue-600'],
            ['label' => 'Reports & Analytics', 'permission' => 'view reports', 'icon' => 'chart-column', 'class' => 'bg-blue-50 text-blue-600'],
            ['label' => 'Workflow / Approvals', 'permission' => 'manage workflows', 'icon' => 'git-branch', 'class' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Settings', 'permission' => 'manage settings', 'icon' => 'settings', 'class' => 'bg-slate-50 text-slate-600'],
        ])->map(fn ($row) => [...$row, 'model' => $permissions->firstWhere('name', $row['permission'])])->filter(fn ($row) => $row['model'] !== null)->values();
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
                                    class="flex w-full items-center gap-3 border-l-2 border-transparent p-3 text-left hover:bg-violet-50"
                                    :class="selectedRole === '{{ $role->id }}' ? 'border-l-violet-600 bg-violet-50 ring-1 ring-inset ring-violet-200' : ''"
                                >
                                    <span class="grid size-10 shrink-0 place-items-center rounded-lg border {{ $roleIcon['class'] }}"><i data-lucide="{{ $roleIcon['icon'] }}" class="size-5"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-black text-slate-900">{{ $role->name }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $role->description }}</span>
                                    </span>
                                    <span class="w-14 text-right text-xs font-black text-slate-700">{{ number_format($role->users_count) }} {{ Str::plural('User', $role->users_count) }}</span>
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

                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <div class="border-b border-slate-100 bg-white px-4 py-3 text-sm font-black text-slate-950">Module Permissions</div>
                                <div class="overflow-x-auto">
                                    <table class="table-compact min-w-[980px]">
                                        <thead>
                                            <tr>
                                                <th class="w-[280px]">Modules</th>
                                                @foreach ($columns as $column)
                                                    <th class="text-center">{{ $column }} <span class="ml-1 inline-grid size-3 place-items-center rounded-full border border-slate-300 text-[9px] text-slate-400">?</span></th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($moduleRows as $row)
                                                @php
                                                    $hasPermission = $role->permissions->contains($row['model']);
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <div class="flex items-center gap-3 font-bold text-slate-800">
                                                            <span class="grid size-7 place-items-center rounded-md {{ $row['class'] }}"><i data-lucide="{{ $row['icon'] }}" class="size-4"></i></span>
                                                            {{ $row['label'] }}
                                                        </div>
                                                    </td>
                                                    @foreach ($columns as $column)
                                                        <td class="text-center">
                                                            <input type="checkbox" name="permissions[]" value="{{ $row['model']->id }}" @checked($hasPermission) class="rounded border-slate-300 text-violet-600">
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 rounded-lg border border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex flex-wrap gap-6 text-sm font-semibold text-slate-600">
                                    <span class="inline-flex items-center gap-2"><span class="grid size-4 place-items-center rounded bg-violet-600 text-white"><i data-lucide="check" class="size-3"></i></span>Full Access</span>
                                    <span class="inline-flex items-center gap-2"><span class="grid size-4 place-items-center rounded bg-blue-600 text-white"><i data-lucide="minus" class="size-3"></i></span>Partial Access</span>
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
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($moduleRows as $row)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="checkbox" name="permissions[]" value="{{ $row['model']->id }}" class="rounded border-slate-300 text-violet-600">
                                {{ $row['label'] }}
                            </label>
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
