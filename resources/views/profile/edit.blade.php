<x-app-layout title="User Profile" :breadcrumbs="[]">
    @php
        $role = $user->roles->first();
        $permissions = $user->roles->flatMap->permissions->unique('id')->values();
        $permissionLabels = $permissions->take(6)->map(fn ($permission) => str($permission->name)->headline());
        $profileFields = [
            $user->name,
            $user->email,
            $user->phone,
            $user->title,
            $user->employee_id,
            $user->date_joined,
            $user->date_of_birth,
            $user->gender,
            $user->address,
            $user->timezone,
            $user->emergency_contact_name,
            $user->emergency_contact_relationship,
            $user->emergency_contact_phone,
            $user->recovery_email,
            $user->avatar_src,
        ];
        $completedFields = collect($profileFields)->filter(fn ($value) => filled($value))->count();
        $completion = (int) round(($completedFields / count($profileFields)) * 100);
        $passwordAge = $user->password_changed_at ? $user->password_changed_at->diffInDays(now()) : null;
        $passwordStrength = $passwordAge !== null && $passwordAge <= 90 ? 'Strong' : 'Review';
        $sessionCount = max($activeSessions->count(), 1);
        $avatarFallback = Str::of($user->name)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join('');
        $tabs = [
            'overview' => 'Overview',
            'permissions' => 'Permissions',
            'activity' => 'Activity Timeline',
            'security' => 'Security',
            'sessions' => 'Sessions',
            'documents' => 'Documents',
        ];
        $isAdminProfile = $isAdminProfile ?? false;
        $profileUpdateRoute = $profileUpdateRoute ?? route('profile.update');
        $profileUpdateMethod = $profileUpdateMethod ?? 'PATCH';
        $passwordUpdateRoute = $passwordUpdateRoute ?? route('profile.password');
        $passwordRequiresCurrent = $passwordRequiresCurrent ?? true;
        $impersonateRoute = $impersonateRoute ?? route('profile.impersonate');
        $profileRefreshRoute = $isAdminProfile ? route('users.show', $user) : route('profile.edit');
        $adminRoles = collect($roles ?? []);
        $adminChurches = collect($churches ?? []);
        $adminCampuses = collect($campuses ?? []);
    @endphp

    <div x-data="profilePage(@js(request()->boolean('edit')))" class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-black text-slate-950">User Profile</h1>
                <span class="text-sm font-semibold text-slate-400">Users &gt; {{ $user->name }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($isAdminProfile)
                    <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="arrow-left" class="size-4"></i> Back to Users
                    </a>
                @endif
                <form method="POST" action="{{ $impersonateRoute }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="user-check" class="size-4"></i> Impersonate User
                    </button>
                </form>
                <div class="relative flex">
                    <button type="button" @click="editOpen = true" class="inline-flex items-center justify-center gap-2 rounded-l-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">
                        <i data-lucide="pencil" class="size-4"></i> Edit Profile
                    </button>
                    <button type="button" @click="actionOpen = ! actionOpen" class="grid size-10 place-items-center rounded-r-lg border-l border-violet-500 bg-violet-600 text-white hover:bg-violet-700">
                        <i data-lucide="chevron-up" class="size-4 rotate-180"></i>
                    </button>
                    <div x-cloak x-show="actionOpen" x-transition @click.outside="actionOpen = false" class="absolute right-0 top-12 z-30 w-48 rounded-lg border border-slate-200 bg-white p-2 shadow-xl">
                        <button type="button" @click="passwordOpen = true; actionOpen = false" class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="shield-check" class="size-4"></i>Update Password</button>
                        <a href="{{ route('roles.index') }}" class="flex items-center gap-2 rounded-md px-2 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="shield-check" class="size-4"></i>View Permissions</a>
                    </div>
                </div>
            </div>
        </div>

        @if (session('status') || session('password_status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') ?? session('password_status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 xl:grid-cols-[1fr_170px_170px_170px_170px]">
            <section class="dashboard-card xl:col-span-1">
                <div class="grid gap-6 lg:grid-cols-[170px_1fr_330px]">
                    <div class="flex justify-center lg:justify-start">
                        <div class="relative">
                            @if ($user->avatar_src)
                                <img src="{{ $user->avatar_src }}" alt="{{ $user->name }}" class="size-36 rounded-full object-cover ring-4 ring-white">
                            @else
                                <div class="grid size-36 place-items-center rounded-full bg-gradient-to-br from-violet-100 to-slate-200 text-5xl font-black text-violet-700">{{ $avatarFallback }}</div>
                            @endif
                            <span class="absolute bottom-3 right-2 size-6 rounded-full border-4 border-white bg-emerald-500"></span>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <span class="rounded-md bg-violet-100 px-2 py-1 text-xs font-black text-violet-700">{{ $role?->name ?? 'Staff' }}</span>
                        <h2 class="mt-2 text-3xl font-black leading-tight text-slate-950">{{ $user->name }} <i data-lucide="badge-check" class="inline size-5 text-violet-600"></i></h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ $user->title ?? 'Team Member' }}</p>
                        <div class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                            <div class="flex items-center gap-3"><i data-lucide="mail" class="size-4 text-slate-400"></i><span class="truncate">{{ $user->email }}</span></div>
                            <div class="flex items-center gap-3"><i data-lucide="badge" class="size-4 text-slate-400"></i><span>Employee ID<br><strong class="text-slate-900">{{ $user->employee_id ?? 'N/A' }}</strong></span></div>
                            <div class="flex items-center gap-3"><i data-lucide="phone" class="size-4 text-slate-400"></i><span>{{ $user->phone ?? 'N/A' }}</span></div>
                            <div class="flex items-center gap-3"><i data-lucide="calendar-days" class="size-4 text-slate-400"></i><span>Date Joined<br><strong class="text-slate-900">{{ $user->date_joined?->format('M d, Y') ?? 'N/A' }}</strong></span></div>
                        </div>
                    </div>
                    <div class="grid gap-3 border-t border-slate-100 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                        @foreach ([
                            ['label' => 'Role', 'value' => $role?->name ?? 'No Role', 'icon' => 'users'],
                            ['label' => 'Status', 'value' => str($user->status)->headline(), 'icon' => 'users-round'],
                            ['label' => 'Department / Ministry', 'value' => $user->title ?? 'General Staff', 'icon' => 'landmark'],
                        ] as $item)
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $item['icon'] }}" class="size-4"></i></span>
                                    <div class="min-w-0">
                                        <div class="text-xs text-slate-500">{{ $item['label'] }}</div>
                                        <div class="truncate text-sm font-black text-slate-950">{{ $item['value'] }}</div>
                                    </div>
                                </div>
                                <span class="grid size-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="check" class="size-4"></i></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="dashboard-card grid min-h-[180px] place-items-center text-center">
                <div class="grid size-12 place-items-center rounded-full bg-orange-50 text-orange-500"><i data-lucide="star" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Access Level</div><div class="mt-2 text-xl font-black text-slate-950">{{ $user->isSuperAdministrator() ? 'Super Admin' : 'Standard' }}</div><div class="mt-1 text-xs font-bold text-emerald-600">{{ $user->isSuperAdministrator() ? 'Full System Access' : 'Role Scoped Access' }}</div></div>
            </section>
            <section class="dashboard-card grid min-h-[180px] place-items-center text-center">
                <div class="grid size-12 place-items-center rounded-full bg-rose-50 text-rose-500"><i data-lucide="calendar-days" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Last Login</div><div class="mt-2 text-lg font-black text-slate-950">{{ $user->last_login_at?->format('M d, Y') ?? 'Never' }}</div><div class="text-sm font-black text-slate-950">{{ $user->last_login_at?->format('h:i A') }}</div><div class="mt-1 text-xs text-slate-500">Chrome on macOS</div></div>
            </section>
            <section class="dashboard-card grid min-h-[180px] place-items-center text-center">
                <div class="grid size-12 place-items-center rounded-full bg-blue-50 text-blue-600"><i data-lucide="monitor-play" class="size-6"></i></div>
                <div><div class="text-xs font-bold text-slate-500">Active Sessions</div><div class="mt-2 text-2xl font-black text-slate-950">{{ $sessionCount }}</div><button type="button" @click="tab = 'sessions'" class="mt-1 text-xs font-bold text-emerald-600">See all sessions</button></div>
            </section>
            <section class="dashboard-card grid min-h-[180px] place-items-center text-center">
                <div class="relative grid size-16 place-items-center rounded-full border-[6px] border-violet-600 text-sm font-black text-slate-950">{{ $completion }}%</div>
                <div><div class="text-xs font-bold text-slate-500">Profile Completion</div><div class="mt-2 text-xl font-black text-slate-950">{{ $completion >= 90 ? 'Excellent' : 'Good' }}</div><div class="mt-1 text-xs text-slate-500">Complete your profile</div></div>
            </section>
        </div>

        <div class="border-b border-slate-200">
            <div class="flex gap-6 overflow-x-auto text-sm font-bold text-slate-500">
                @foreach ($tabs as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'" class="whitespace-nowrap border-b-2 px-4 py-3" :class="tab === '{{ $key }}' ? 'border-violet-600 text-violet-600' : 'border-transparent hover:text-slate-900'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div x-show="tab === 'overview'" class="grid gap-4 xl:grid-cols-3">
            <section class="dashboard-card">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="user-round" class="size-5 text-violet-600"></i>Personal Information</h2>
                    <button type="button" @click="editOpen = true" class="text-xs font-bold text-violet-600">Edit</button>
                </div>
                <dl class="grid gap-4 text-sm">
                    <div class="grid grid-cols-[140px_1fr] gap-4"><dt class="text-slate-500">Full Name</dt><dd class="font-bold text-slate-950">{{ $user->name }}</dd></div>
                    <div class="grid grid-cols-[140px_1fr] gap-4"><dt class="text-slate-500">Preferred Name</dt><dd class="font-bold text-slate-950">{{ Str::before($user->name, ' ') }}</dd></div>
                    <div class="grid grid-cols-[140px_1fr] gap-4"><dt class="text-slate-500">Email Address</dt><dd class="font-bold text-slate-950">{{ $user->email }} <i data-lucide="check-circle-2" class="ml-1 inline size-3.5 text-emerald-600"></i></dd></div>
                    <div class="grid grid-cols-[140px_1fr] gap-4"><dt class="text-slate-500">Phone Number</dt><dd class="font-bold text-slate-950">{{ $user->phone ?? 'N/A' }}</dd></div>
                    <div class="grid grid-cols-[140px_1fr] gap-4"><dt class="text-slate-500">Date of Birth</dt><dd class="font-bold text-slate-950">{{ $user->date_of_birth?->format('F d, Y') ?? 'N/A' }}</dd></div>
                    <div class="grid grid-cols-[140px_1fr] gap-4"><dt class="text-slate-500">Gender</dt><dd class="font-bold text-slate-950">{{ $user->gender ?? 'N/A' }}</dd></div>
                    <div class="grid grid-cols-[140px_1fr] gap-4"><dt class="text-slate-500">Address</dt><dd class="font-bold text-slate-950 whitespace-pre-line">{{ $user->address ?? 'N/A' }}</dd></div>
                    <div class="grid grid-cols-[140px_1fr] gap-4 pt-8"><dt class="text-slate-500">Time Zone</dt><dd class="font-bold text-slate-950">{{ $user->timezone ?? config('app.timezone') }}</dd></div>
                </dl>
            </section>

            <section class="space-y-4">
                <section class="dashboard-card">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="shield-check" class="size-5 text-violet-600"></i>Role & Permission Summary</h2>
                        <a href="{{ route('roles.index') }}" class="text-xs font-bold text-violet-600">View All Permissions</a>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="users" class="size-5"></i></span>
                            <div><div class="text-xs text-slate-500">Role</div><div class="text-base font-black text-slate-950">{{ $role?->name ?? 'No Role' }}</div></div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @forelse ($permissionLabels as $permission)
                                <div class="text-sm font-semibold text-slate-700"><i data-lucide="check-circle-2" class="mr-2 inline size-4 text-emerald-600"></i>{{ $permission }}</div>
                            @empty
                                <div class="text-sm text-slate-500">No permissions assigned.</div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="landmark" class="size-5 text-violet-600"></i>Church & Campus Assignment</h2>
                        <button type="button" @click="editOpen = true" class="text-xs font-bold text-violet-600">Edit</button>
                    </div>
                    <dl class="space-y-4 text-sm">
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Church</dt><dd class="font-bold text-slate-950">{{ $user->church?->name ?? 'Global' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Campus</dt><dd class="font-bold text-slate-950">{{ $user->campus?->name ?? 'All' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Service Location</dt><dd class="font-bold text-slate-950">{{ $user->campus?->metadata['service_location'] ?? 'N/A' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Ministry Involvement</dt><dd class="font-bold text-slate-950">{{ $user->title ? $user->title.', Discipleship, Church Council' : 'N/A' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Sunday Service</dt><dd class="font-bold text-slate-950">{{ $user->campus?->metadata['sunday_service'] ?? 'N/A' }}</dd></div>
                    </dl>
                </section>
            </section>

            <section class="space-y-4">
                <section class="dashboard-card">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="badge" class="size-5 text-violet-600"></i>Emergency Contact</h2>
                        <button type="button" @click="editOpen = true" class="text-xs font-bold text-violet-600">Edit</button>
                    </div>
                    <dl class="space-y-4 text-sm">
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Contact Name</dt><dd class="font-bold text-slate-950">{{ $user->emergency_contact_name ?? 'N/A' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Relationship</dt><dd class="font-bold text-slate-950">{{ $user->emergency_contact_relationship ?? 'N/A' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Phone Number</dt><dd class="font-bold text-slate-950">{{ $user->emergency_contact_phone ?? 'N/A' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Alternate Phone</dt><dd class="font-bold text-slate-950">{{ $user->phone ?? 'N/A' }}</dd></div>
                        <div class="grid grid-cols-[130px_1fr] gap-4"><dt class="text-slate-500">Address</dt><dd class="font-bold text-slate-950 whitespace-pre-line">{{ $user->address ?? 'N/A' }}</dd></div>
                    </dl>
                </section>

                <section class="dashboard-card">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="shield-check" class="size-5 text-violet-600"></i>Account Security</h2>
                        <button type="button" @click="passwordOpen = true" class="text-xs font-bold text-violet-600">Manage</button>
                    </div>
                    <dl class="space-y-4 text-sm">
                        <div class="grid grid-cols-[150px_1fr_auto] gap-3"><dt class="text-slate-500">Password</dt><dd class="font-bold text-slate-950">Last changed {{ $passwordAge !== null ? $passwordAge.' days ago' : 'N/A' }}</dd><dd><span class="rounded-md bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700">{{ $passwordStrength }}</span></dd></div>
                        <div class="grid grid-cols-[150px_1fr_auto] gap-3"><dt class="text-slate-500">Two-Factor Authentication</dt><dd class="font-bold text-slate-950">{{ $user->mfa_enabled ? 'Enabled' : 'Disabled' }}</dd><dd><span class="rounded-md {{ $user->mfa_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }} px-3 py-1 text-xs font-black">{{ $user->mfa_enabled ? 'Active' : 'Inactive' }}</span></dd></div>
                        <div class="grid grid-cols-[150px_1fr] gap-3"><dt class="text-slate-500">MFA Method</dt><dd class="font-bold text-slate-950">{{ $user->mfa_enabled ? 'Authenticator App (Google Authenticator)' : 'Not configured' }}</dd></div>
                        <div class="grid grid-cols-[150px_1fr_auto] gap-3"><dt class="text-slate-500">Recovery Email</dt><dd class="break-all font-bold text-slate-950">{{ $user->recovery_email ?? 'N/A' }}</dd><dd><span class="rounded-md bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700">Verified</span></dd></div>
                    </dl>
                </section>
            </section>
        </div>

        <div x-show="tab === 'permissions'" class="dashboard-card">
            <h2 class="mb-4 text-base font-black text-slate-950">Permissions</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($permissions as $permission)
                    <div class="rounded-lg border border-slate-100 p-3 text-sm font-semibold text-slate-700"><i data-lucide="check-circle-2" class="mr-2 inline size-4 text-emerald-600"></i>{{ str($permission->name)->headline() }}</div>
                @empty
                    <div class="text-sm text-slate-500">No permissions assigned.</div>
                @endforelse
            </div>
        </div>

        <div x-show="tab === 'security'" class="grid gap-4 xl:grid-cols-2">
            <section class="dashboard-card">
                <div class="mb-4 flex items-center justify-between"><h2 class="text-base font-black text-slate-950">Password Update</h2><button type="button" @click="passwordOpen = true" class="text-xs font-bold text-violet-600">Update Password</button></div>
                <p class="text-sm text-slate-500">Password last changed {{ $passwordAge !== null ? $passwordAge.' days ago' : 'N/A' }}.</p>
            </section>
            <section class="dashboard-card">
                <div class="mb-4 flex items-center justify-between"><h2 class="text-base font-black text-slate-950">Recovery Settings</h2><button type="button" @click="editOpen = true" class="text-xs font-bold text-violet-600">Edit</button></div>
                <p class="break-all text-sm font-bold text-slate-900">{{ $user->recovery_email ?? 'No recovery email configured.' }}</p>
            </section>
        </div>

        <div x-show="tab === 'sessions'" class="dashboard-card">
            <div class="mb-5 flex items-center justify-between"><h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="monitor-play" class="size-5 text-violet-600"></i>Active Sessions ({{ $sessionCount }})</h2><a href="{{ $profileRefreshRoute }}" class="text-xs font-bold text-violet-600">Refresh</a></div>
            @forelse ($activeSessions as $session)
                <div class="flex items-center justify-between border-b border-slate-100 py-4 last:border-0">
                    <div class="flex items-center gap-3">
                        <i data-lucide="monitor-play" class="size-5 text-slate-500"></i>
                        <div><div class="font-bold text-slate-950">{{ str($session->user_agent)->limit(80) }}</div><div class="text-xs text-slate-500">{{ $session->ip_address ?? 'Unknown IP' }}</div></div>
                    </div>
                    <div class="text-right text-sm text-slate-500">{{ now()->createFromTimestamp($session->last_activity)->format('M d, Y') }}<br>{{ now()->createFromTimestamp($session->last_activity)->format('h:i A') }}</div>
                </div>
            @empty
                <x-empty-state icon="monitor-play" title="No sessions" message="No session records found." />
            @endforelse
        </div>

        <div x-show="tab === 'activity'" class="dashboard-card">
            <div class="mb-5 flex items-center justify-between"><h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="clipboard-list" class="size-5 text-violet-600"></i>Activity Timeline</h2><a href="{{ route('audit-logs.index') }}" class="text-xs font-bold text-violet-600">View Full Timeline</a></div>
            @forelse ($activityLogs as $log)
                <div class="grid grid-cols-[18px_1fr_auto] gap-3 border-l border-slate-200 pb-4 last:pb-0">
                    <span class="-ml-[7px] mt-1 size-3 rounded-full bg-violet-600 ring-4 ring-violet-50"></span>
                    <div><div class="text-sm font-black text-slate-900">{{ $log->description }}</div><div class="text-xs text-slate-500">{{ $log->action }}</div></div>
                    <div class="text-right text-xs text-slate-500">{{ $log->created_at->format('M d, Y') }}<br>{{ $log->created_at->format('h:i A') }}</div>
                </div>
            @empty
                <x-empty-state icon="clipboard-list" title="No activity" message="No account activity has been recorded yet." />
            @endforelse
        </div>

        <div x-show="tab === 'documents'" class="dashboard-card">
            <x-empty-state icon="file-search" title="No documents" message="No profile documents have been uploaded for this account." />
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <section class="dashboard-card">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-black text-slate-950">Password Update</h2>
                    <button type="button" @click="passwordOpen = true" class="text-xs font-bold text-violet-600">Update Password</button>
                </div>
                <div class="text-sm text-slate-500">Last changed</div>
                <div class="mt-1 font-black text-slate-950">{{ $user->password_changed_at?->format('F d, Y') ?? 'N/A' }} {{ $passwordAge !== null ? '('.$passwordAge.' days ago)' : '' }}</div>
                <div class="mt-6 flex gap-2">
                    <span class="h-2 flex-1 rounded-full bg-emerald-600"></span>
                    <span class="h-2 flex-1 rounded-full bg-emerald-600"></span>
                    <span class="h-2 flex-1 rounded-full bg-emerald-600"></span>
                </div>
                <p class="mt-4 text-sm font-semibold text-emerald-600">Your password is strong. Great job!</p>
            </section>

            <section class="dashboard-card">
                <div class="mb-5 flex items-center justify-between"><h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="user-check" class="size-5 text-violet-600"></i>Active Sessions ({{ $sessionCount }})</h2><button type="button" @click="tab = 'sessions'" class="text-xs font-bold text-violet-600">View All Sessions</button></div>
                @forelse ($activeSessions as $session)
                    <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-0">
                        <div class="flex items-center gap-3"><i data-lucide="monitor-play" class="size-5 text-slate-500"></i><div><div class="text-sm font-bold text-slate-950">{{ str($session->user_agent)->limit(34) }}</div><div class="text-xs text-slate-500">{{ $session->ip_address ?? 'Unknown IP' }}</div></div></div>
                        <div class="text-right text-xs text-slate-500">{{ now()->createFromTimestamp($session->last_activity)->format('M d, Y') }}<br>{{ now()->createFromTimestamp($session->last_activity)->format('h:i A') }}</div>
                    </div>
                @empty
                    <x-empty-state icon="monitor-play" title="No sessions" message="No session records found." />
                @endforelse
            </section>

            <section class="dashboard-card">
                <div class="mb-5 flex items-center justify-between"><h2 class="flex items-center gap-3 text-base font-black text-slate-950"><i data-lucide="clipboard-list" class="size-5 text-violet-600"></i>Activity Timeline</h2><button type="button" @click="tab = 'activity'" class="text-xs font-bold text-violet-600">View Full Timeline</button></div>
                @forelse ($activityLogs->take(4) as $log)
                    <div class="grid grid-cols-[18px_1fr_auto] gap-3 pb-3 last:pb-0">
                        <span class="mt-1 size-2.5 rounded-full bg-violet-600"></span>
                        <div><div class="text-sm font-black text-slate-900">{{ $log->description }}</div><div class="text-xs text-slate-500">{{ $log->action }}</div></div>
                        <div class="text-right text-xs text-slate-500">{{ $log->created_at->format('M d, Y') }}<br>{{ $log->created_at->format('h:i A') }}</div>
                    </div>
                @empty
                    <x-empty-state icon="clipboard-list" title="No activity" message="No account activity has been recorded yet." />
                @endforelse
            </section>
        </div>

        <footer class="flex flex-col gap-2 py-2 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</span>
            <span class="flex gap-8"><span>Version 2.4.0</span><span>Privacy Policy</span><span>Terms of Service</span></span>
        </footer>

        <div x-cloak x-show="editOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/40 p-4" @keydown.escape.window="editOpen = false">
            <div class="my-6 w-full max-w-4xl rounded-xl bg-white shadow-2xl" @click.outside="editOpen = false">
                <div class="flex items-start justify-between border-b border-slate-100 p-5">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Edit Profile</h2>
                        <p class="mt-1 text-sm text-slate-500">Update your real account details and profile image.</p>
                    </div>
                    <button type="button" @click="editOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
                </div>
                <form method="POST" action="{{ $profileUpdateRoute }}" enctype="multipart/form-data" class="space-y-5 p-5">
                    @csrf
                    @method($profileUpdateMethod)
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="relative size-24 overflow-hidden rounded-full bg-violet-100">
                            @if ($user->avatar_src)
                                <img :src="avatarPreview || @js($user->avatar_src)" alt="{{ $user->name }}" class="size-full object-cover">
                            @else
                                <img x-show="avatarPreview" :src="avatarPreview" alt="{{ $user->name }}" class="size-full object-cover">
                                <div x-show="! avatarPreview" class="grid size-full place-items-center text-2xl font-black text-violet-700">{{ $avatarFallback }}</div>
                            @endif
                        </div>
                        <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                            <i data-lucide="plus" class="size-4"></i> Upload Photo
                            <input type="file" name="avatar" accept="image/*" class="hidden" @change="previewAvatar($event)">
                        </label>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Full Name<input name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Title<input name="title" value="{{ old('title', $user->title) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Email<input name="email" type="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Phone<input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Date of Birth<input name="date_of_birth" type="date" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Gender<select name="gender" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"><option value="">Not set</option><option value="Male" @selected(old('gender', $user->gender) === 'Male')>Male</option><option value="Female" @selected(old('gender', $user->gender) === 'Female')>Female</option></select></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500 sm:col-span-2">Address<textarea name="address" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">{{ old('address', $user->address) }}</textarea></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Time Zone<input name="timezone" value="{{ old('timezone', $user->timezone) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Recovery Email<input name="recovery_email" type="email" value="{{ old('recovery_email', $user->recovery_email) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Emergency Contact<input name="emergency_contact_name" value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Relationship<input name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $user->emergency_contact_relationship) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Emergency Phone<input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $user->emergency_contact_phone) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"></label>
                        @if ($isAdminProfile)
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Status<select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"><option value="active" @selected(old('status', $user->status) === 'active')>Active</option><option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option><option value="suspended" @selected(old('status', $user->status) === 'suspended')>Suspended</option></select></label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Role<select name="roles[]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900">@foreach($adminRoles as $adminRole)<option value="{{ $adminRole->id }}" @selected($user->roles->contains($adminRole))>{{ $adminRole->name }}</option>@endforeach</select></label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Church<select name="church_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"><option value="">Global</option>@foreach($adminChurches as $church)<option value="{{ $church->id }}" @selected((string) old('church_id', $user->church_id) === (string) $church->id)>{{ $church->name }}</option>@endforeach</select></label>
                            <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Campus<select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900"><option value="">All Campuses</option>@foreach($adminCampuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('campus_id', $user->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                        @endif
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" @click="editOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-cloak x-show="passwordOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @keydown.escape.window="passwordOpen = false">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl" @click.outside="passwordOpen = false">
                <div class="flex items-start justify-between border-b border-slate-100 p-5">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Update Password</h2>
                        <p class="mt-1 text-sm text-slate-500">Change the password used to access this account.</p>
                    </div>
                    <button type="button" @click="passwordOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
                </div>
                <form method="POST" action="{{ $passwordUpdateRoute }}" class="space-y-4 p-5">
                    @csrf
                    @method('PUT')
                    @if ($passwordRequiresCurrent)
                        <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Current Password<input name="current_password" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required></label>
                    @endif
                    <label class="space-y-1 text-xs font-bold uppercase text-slate-500">New Password<input name="password" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required></label>
                    <label class="space-y-1 text-xs font-bold uppercase text-slate-500">Confirm New Password<input name="password_confirmation" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm normal-case text-slate-900" required></label>
                    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" @click="passwordOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
