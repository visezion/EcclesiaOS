<x-app-layout title="Access Control" :breadcrumbs="$breadcrumbs">
    @php
        $sidebarBackgroundPath = data_get($brandingChurch?->settings, 'sidebar_background') ?: config('church.sidebar_background');
        $sidebarBackgroundUrl = \Illuminate\Support\Str::startsWith((string) $sidebarBackgroundPath, ['http://', 'https://', '/'])
            ? $sidebarBackgroundPath
            : (\Illuminate\Support\Str::startsWith((string) $sidebarBackgroundPath, 'branding/')
                ? asset('storage/'.$sidebarBackgroundPath)
                : asset($sidebarBackgroundPath));
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Authentication & Access Control</h1>
                <p class="mt-1 text-sm text-slate-500">Manage users, church and campus assignments, roles, permissions, and audit activity.</p>
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="dashboard-card px-5 py-3"><div class="text-2xl font-black">{{ $users->count() }}</div><div class="text-xs font-bold text-slate-500">Users</div></div>
                <div class="dashboard-card px-5 py-3"><div class="text-2xl font-black">{{ $roles->count() }}</div><div class="text-xs font-bold text-slate-500">Roles</div></div>
                <div class="dashboard-card px-5 py-3"><div class="text-2xl font-black">{{ $permissions->count() }}</div><div class="text-xs font-bold text-slate-500">Permissions</div></div>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="dashboard-card">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Sidebar Background</h2>
                    <p class="mt-1 text-sm text-slate-500">Upload a PNG image for the lower sidebar background behind the profile card.</p>
                </div>
                <form method="POST" action="{{ route('settings.branding.sidebar-background.reset') }}">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset Background</button>
                </form>
            </div>
            <div class="grid gap-4 lg:grid-cols-[240px_1fr] lg:items-center">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-sidebar">
                    <div class="h-32 bg-church-silhouette" style="--sidebar-background-image: url('{{ $sidebarBackgroundUrl }}');"></div>
                </div>
                <form method="POST" action="{{ route('settings.branding.sidebar-background') }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                    @csrf
                    <label class="space-y-1 text-sm">
                        <span class="font-medium text-slate-700">PNG file</span>
                        <input name="sidebar_background" type="file" accept="image/png" required class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-violet-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-violet-700">
                        <span class="block text-xs text-slate-500">PNG only, up to 2 MB. Current source: {{ $sidebarBackgroundPath }}</span>
                    </label>
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                        <i data-lucide="upload" class="size-4"></i>
                        Upload PNG
                    </button>
                </form>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-950">Create User</h2>
                <span class="text-xs font-semibold text-slate-500">Church/campus assignment is enforced at the account level.</span>
            </div>
            <form method="POST" action="{{ route('users.store') }}" class="grid gap-3 lg:grid-cols-6">
                @csrf
                <input name="name" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Full name" required>
                <input name="title" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Title">
                <input name="email" type="email" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Email" required>
                <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
                <select name="church_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Global church access</option>
                    @foreach ($churches as $church)
                        <option value="{{ $church->id }}">{{ $church->name }}</option>
                    @endforeach
                </select>
                <select name="campus_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">All campuses</option>
                    @foreach ($campuses as $campus)
                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                    @endforeach
                </select>
                <input name="password" type="password" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-2" placeholder="Temporary password" required>
                <input name="password_confirmation" type="password" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-2" placeholder="Confirm password" required>
                <select name="roles[]" multiple class="min-h-24 rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-2">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <div class="lg:col-span-6">
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">
                        <i data-lucide="user-plus" class="size-4"></i> Create User
                    </button>
                </div>
            </form>
        </section>

        <section class="dashboard-card">
            <h2 class="mb-4 text-base font-bold text-slate-950">Users</h2>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1100px]">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Church</th>
                            <th>Campus</th>
                            <th>Roles</th>
                            <th>Last Login</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <form id="user-{{ $user->id }}" method="POST" action="{{ route('users.update', $user) }}" class="grid gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input name="name" value="{{ $user->name }}" class="rounded border border-slate-200 px-2 py-1 text-xs">
                                        <input name="title" value="{{ $user->title }}" class="rounded border border-slate-200 px-2 py-1 text-xs" placeholder="Title">
                                        <input name="email" type="email" value="{{ $user->email }}" class="rounded border border-slate-200 px-2 py-1 text-xs">
                                        <input name="password" type="password" class="rounded border border-slate-200 px-2 py-1 text-xs" placeholder="New password">
                                        <input name="password_confirmation" type="password" class="rounded border border-slate-200 px-2 py-1 text-xs" placeholder="Confirm new password">
                                    </form>
                                </td>
                                <td>
                                    <select form="user-{{ $user->id }}" name="status" class="rounded border border-slate-200 px-2 py-1 text-xs">
                                        @foreach (['active', 'inactive', 'suspended'] as $status)
                                            <option value="{{ $status }}" @selected($user->status === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select form="user-{{ $user->id }}" name="church_id" class="w-40 rounded border border-slate-200 px-2 py-1 text-xs">
                                        <option value="">Global</option>
                                        @foreach ($churches as $church)
                                            <option value="{{ $church->id }}" @selected($user->church_id === $church->id)>{{ $church->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select form="user-{{ $user->id }}" name="campus_id" class="w-36 rounded border border-slate-200 px-2 py-1 text-xs">
                                        <option value="">All</option>
                                        @foreach ($campuses as $campus)
                                            <option value="{{ $campus->id }}" @selected($user->campus_id === $campus->id)>{{ $campus->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select form="user-{{ $user->id }}" name="roles[]" multiple class="min-h-24 w-44 rounded border border-slate-200 px-2 py-1 text-xs">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected($user->roles->contains($role))>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td>
                                    <button form="user-{{ $user->id }}" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">Save</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_420px]">
            <section class="dashboard-card">
                <h2 class="mb-4 text-base font-bold text-slate-950">Role Permission Matrix</h2>
                <div class="space-y-4">
                    @foreach ($roles as $role)
                        <form method="POST" action="{{ route('roles.update', $role) }}" class="rounded-lg border border-slate-200 p-4">
                            @csrf
                            @method('PUT')
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-bold text-slate-900">{{ $role->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $role->description }}</div>
                                </div>
                                <button @disabled($role->name === 'Super Administrator') class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white hover:bg-violet-700 disabled:bg-slate-300">Save Permissions</button>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 rounded border border-slate-100 bg-slate-50 px-2 py-1.5 text-xs">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission)) @disabled($role->name === 'Super Administrator') class="rounded border-slate-300 text-violet-600">
                                        <span>{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </form>
                    @endforeach
                </div>
            </section>

            <section class="dashboard-card">
                <h2 class="mb-4 text-base font-bold text-slate-950">Recent Activity Log</h2>
                <div class="divide-y divide-slate-100">
                    @forelse ($activityLogs as $log)
                        <div class="py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-bold text-slate-900">{{ $log->description }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->module }} / {{ $log->action }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</div>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600">{{ $log->ip_address ?? 'local' }}</span>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="inbox" title="No activity yet" message="Authentication and access-control actions will appear here." />
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
