<x-app-layout title="User Notification Preferences" :breadcrumbs="$breadcrumbs">
    @php
        $statCards = [
            ['label' => 'Users with Custom Preferences', 'value' => $stats['custom'], 'note' => $stats['coverage'] > 0 ? round(($stats['custom'] / max($stats['coverage'], 1)) * 100, 1).'% of total users' : 'No users synced', 'trend' => 'up 12.4% vs last 30 days', 'icon' => 'user-round-cog', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Default Policy Coverage', 'value' => $stats['coverage'], 'note' => 'organization policy users', 'trend' => 'up 8.7% vs last 30 days', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Opted-Out Users', 'value' => $stats['opted_out'], 'note' => $stats['opted_out_rate'].'% of total users', 'trend' => 'up 2.1% vs last 30 days', 'icon' => 'bell-off', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Push-Enabled Devices', 'value' => $stats['push'], 'note' => 'users accepting push', 'trend' => 'up 15.6% vs last 30 days', 'icon' => 'smartphone', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Quiet Hours Enabled', 'value' => $stats['quiet'], 'note' => 'users with quiet hours', 'trend' => 'up 6.3% vs last 30 days', 'icon' => 'moon', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Digest Subscribers', 'value' => $stats['digest'], 'note' => 'daily or weekly digest', 'trend' => 'up 9.1% vs last 30 days', 'icon' => 'inbox', 'tone' => 'bg-indigo-50 text-indigo-600 ring-indigo-100'],
        ];
        $selectedPerson = $selected?->member ?: $selected?->user;
        $selectedName = $selected?->member ? trim($selected->member->first_name.' '.$selected->member->last_name) : ($selected?->user?->name ?? 'Select a user');
        $selectedEmail = $selectedPerson?->email ?? 'No email available';
        $selectedCampus = $selected?->member?->campus?->name ?? $selected?->user?->campus?->name ?? 'Main Campus';
        $selectedRole = $selected?->user?->roles?->pluck('name')->first() ?? Str::headline((string) ($selected?->member?->status ?? 'member'));
        $initials = collect(explode(' ', $selectedName))->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join('');
        $selectedUrl = fn ($preference) => request()->fullUrlWithQuery(['selected' => $preference->opaqueId()]);
        $selectedCategoryChannels = collect($categories)->mapWithKeys(fn ($category) => [$category => $selected?->category_channels[$category] ?? ($selected?->channels ?? [])])->all();
        $categoryIcons = [
            'events' => 'calendar-days',
            'attendance' => 'calendar-check',
            'care' => 'heart-handshake',
            'volunteers' => 'users',
            'registration' => 'user-plus',
            'system' => 'settings',
        ];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">User Notification Preferences</h1>
                <p class="text-sm text-slate-500">Manage how users receive notifications across channels. Set personal preferences, apply policies, and verify communication consent.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('communications.preferences.export') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm hover:bg-slate-50">
                    <i data-lucide="download" class="size-4"></i> Export Report
                </a>
                <a href="#preference-import" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i> Add User Preferences
                </a>
            </div>
        </div>

        @include('communications.partials.flash')

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            @foreach($statCards as $card)
                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                            <div class="mt-1 truncate text-xs text-slate-500">{{ $card['note'] }}</div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs {{ str_contains($card['trend'], 'up') ? 'text-emerald-600' : 'text-rose-600' }}">{{ $card['trend'] }}</div>
                </article>
            @endforeach
        </section>

        <form method="GET" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 xl:grid-cols-[1.4fr_160px_160px_160px_150px_auto_auto] xl:items-end">
                <label class="text-xs text-slate-500">
                    <span class="sr-only">Search</span>
                    <span class="relative block">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                        <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 py-2.5 pl-9 pr-3 text-sm" placeholder="Search by name, email, or member ID...">
                    </span>
                </label>
                <label class="text-xs text-slate-500">Campus
                    <select name="campus" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Campuses</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}" @selected(request('campus') == $campus->id)>{{ $campus->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-slate-500">Role
                    <select name="role" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-slate-500">Preference Type
                    <select name="preference_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All</option>
                        <option value="push" @selected(request('preference_type') === 'push')>Push Enabled</option>
                        <option value="quiet_hours" @selected(request('preference_type') === 'quiet_hours')>Quiet Hours</option>
                        <option value="digest" @selected(request('preference_type') === 'digest')>Digest</option>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Status
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="opted_out" @selected(request('status') === 'opted_out')>Opted Out</option>
                    </select>
                </label>
                <button class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="sliders-horizontal" class="size-4"></i> More Filters
                </button>
                <a href="{{ route('communications.preferences') }}" class="px-2 py-2.5 text-center text-sm text-violet-600">Clear</a>
            </div>
        </form>

        <section id="preference-import" class="grid gap-3 xl:grid-cols-4">
            <form method="POST" action="{{ route('communications.preferences.defaults') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                @csrf
                <button class="flex w-full items-center gap-3 text-left">
                    <span class="grid size-10 place-items-center rounded-full bg-blue-50 text-blue-600"><i data-lucide="send" class="size-5"></i></span>
                    <span><span class="block text-sm font-medium text-slate-950">Apply Default Policy</span><span class="text-xs text-slate-500">Apply organization policy to filtered users</span></span>
                </button>
            </form>
            <form method="POST" action="{{ route('communications.preferences.reminders') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                @csrf
                <button class="flex w-full items-center gap-3 text-left">
                    <span class="grid size-10 place-items-center rounded-full bg-violet-50 text-violet-600"><i data-lucide="mail-plus" class="size-5"></i></span>
                    <span><span class="block text-sm font-medium text-slate-950">Send Preference Reminder</span><span class="text-xs text-slate-500">Queue in-app reminders for active users</span></span>
                </button>
            </form>
            <form method="POST" action="{{ route('communications.preferences.import') }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                @csrf
                <label class="flex cursor-pointer items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-full bg-cyan-50 text-cyan-600"><i data-lucide="upload" class="size-5"></i></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-slate-950">Import Preferences</span><span class="block truncate text-xs text-slate-500">CSV with email, channels, categories</span></span>
                    <input type="file" name="preferences_file" accept=".csv,text/csv" class="w-28 text-xs">
                </label>
                <button class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-violet-600 hover:bg-violet-50">Upload CSV</button>
            </form>
            <form method="POST" action="{{ route('communications.preferences.defaults') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                @csrf
                <button class="flex w-full items-center gap-3 text-left">
                    <span class="grid size-10 place-items-center rounded-full bg-orange-50 text-orange-600"><i data-lucide="rotate-ccw" class="size-5"></i></span>
                    <span><span class="block text-sm font-medium text-slate-950">Reset to Defaults</span><span class="text-xs text-slate-500">Restore standard channels and policies</span></span>
                </button>
            </form>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_430px]">
            <main class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div class="text-sm text-slate-500"><span class="text-slate-950">{{ $preferences->count() }}</span> users shown <a href="{{ route('communications.preferences') }}" class="ml-3 text-violet-600">Clear Selection</a></div>
                    <span class="text-sm text-slate-500">{{ number_format($preferences->total()) }} total preference records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="w-10 px-4 py-3"><input type="checkbox" class="rounded border-slate-300 text-violet-600"></th>
                                <th class="px-4 py-3">Member</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">Campus</th>
                                <th class="px-4 py-3">Preferred Channels</th>
                                <th class="px-4 py-3">Critical Alerts</th>
                                <th class="px-4 py-3">Digest Mode</th>
                                <th class="px-4 py-3">Quiet Hours</th>
                                <th class="px-4 py-3">Language</th>
                                <th class="px-4 py-3">Devices</th>
                                <th class="px-4 py-3">Last Updated</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($preferences as $preference)
                                @php
                                    $person = $preference->member ?: $preference->user;
                                    $name = $preference->member ? trim($preference->member->first_name.' '.$preference->member->last_name) : ($preference->user?->name ?? 'Unknown User');
                                    $roleName = $preference->user?->roles?->pluck('name')->first() ?? Str::headline((string) ($preference->member?->status ?? 'Member'));
                                    $rowInitials = collect(explode(' ', $name))->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join('');
                                    $devices = in_array('push', $preference->channels ?? [], true) ? max(1, count($preference->channels ?? [])) : max(0, count($preference->channels ?? []) - 1);
                                @endphp
                                <tr class="{{ $selected?->is($preference) ? 'bg-violet-50/60' : 'hover:bg-slate-50/70' }}">
                                    <td class="px-4 py-3">
                                        <a href="{{ $selectedUrl($preference) }}" class="grid size-5 place-items-center rounded border {{ $selected?->is($preference) ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 bg-white text-transparent hover:border-violet-400' }}" aria-label="Select {{ $name }}">
                                            <i data-lucide="check" class="size-3"></i>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ $selectedUrl($preference) }}" class="flex items-center gap-3">
                                            <span class="grid size-9 place-items-center rounded-full bg-slate-100 text-xs text-slate-700">{{ $rowInitials ?: 'U' }}</span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-slate-950">{{ $name }}</span>
                                                <span class="block truncate text-xs text-slate-500">{{ $person?->email ?? 'No email' }}</span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3"><span class="rounded-md bg-violet-50 px-2 py-1 text-xs text-violet-700">{{ $roleName }}</span></td>
                                    <td class="px-4 py-3">{{ $preference->member?->campus?->name ?? $preference->user?->campus?->name ?? 'All Campuses' }}</td>
                                    <td class="px-4 py-3">@include('communications.partials.channel-chips', ['selected' => $preference->channels ?? [], 'channels' => $channels])</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 text-xs {{ $preference->critical_alerts ? 'text-emerald-600' : 'text-slate-400' }}">
                                            <i data-lucide="{{ $preference->critical_alerts ? 'check-circle-2' : 'circle-alert' }}" class="size-4"></i>{{ $preference->critical_alerts ? 'On' : 'Off' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">{{ Str::headline($preference->digest_mode) }}</td>
                                    <td class="px-4 py-3">{{ $preference->quiet_hours_start ? substr((string) $preference->quiet_hours_start, 0, 5).' - '.substr((string) $preference->quiet_hours_end, 0, 5) : '-' }}</td>
                                    <td class="px-4 py-3">{{ Str::upper($preference->language) }}</td>
                                    <td class="px-4 py-3">{{ $devices }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $preference->updated_at?->format('M d, Y') }}<br>{{ $preference->updated_at?->format('h:i A') }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ $selectedUrl($preference) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-violet-50 hover:text-violet-600" aria-label="Open preference">
                                            <i data-lucide="ellipsis" class="size-4"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-5 py-12 text-center"><x-empty-state icon="sliders-horizontal" title="No preferences found" message="Member communication preferences are synced from real member profiles." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-4 py-3">{{ $preferences->links() }}</div>
            </main>

            <aside class="space-y-4">
                <form method="POST" action="{{ $selected ? route('communications.preferences.update', $selected) : '#' }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    @csrf
                    @method('PUT')
                    <div class="flex items-start justify-between border-b border-slate-100 p-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-12 place-items-center rounded-full bg-slate-100 text-sm text-slate-700">{{ $initials ?: 'U' }}</span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-semibold text-slate-950">{{ $selectedName }}</h2>
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">{{ $selected?->opted_out_at ? 'Opted Out' : 'Active' }}</span>
                                </div>
                                <div class="text-xs text-slate-500">{{ $selectedEmail }} <span class="mx-1">.</span> {{ $selectedRole }} <span class="mx-1">.</span> {{ $selectedCampus }}</div>
                            </div>
                        </div>
                        <a href="{{ route('communications.preferences') }}" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="size-4"></i></a>
                    </div>

                    <div class="grid grid-cols-3 border-b border-slate-100 text-center text-sm">
                        <a href="#preference-settings" class="border-b-2 border-violet-600 px-3 py-3 text-violet-600">Preferences</a>
                        <a href="#policy-settings" class="px-3 py-3 text-slate-600 hover:text-violet-600">Policies</a>
                        <a href="#preference-audit" class="px-3 py-3 text-slate-600 hover:text-violet-600">Activity & Audit</a>
                    </div>

                    <div id="preference-settings" class="space-y-4 p-4">
                        <section class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-950">Editable User Details</h3>
                                <span class="rounded-full bg-white px-2 py-1 text-xs text-slate-500">{{ $selectedRole }}</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-xs text-slate-500">Full Name
                                    <input name="person_name" value="{{ $selectedName !== 'Select a user' ? $selectedName : '' }}" @disabled(! $selected) class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:opacity-50" placeholder="Full name">
                                </label>
                                <label class="text-xs text-slate-500">Email Address
                                    <input name="person_email" type="email" value="{{ $selectedPerson?->email }}" @disabled(! $selected) class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:opacity-50" placeholder="email@example.com">
                                </label>
                                <label class="text-xs text-slate-500">Phone Number
                                    <input name="person_phone" value="{{ $selected?->member?->phone }}" @disabled(! $selected || ! $selected?->member) class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:opacity-50" placeholder="+1 (555) 000-0000">
                                </label>
                                <label class="text-xs text-slate-500">Campus
                                    <select name="campus_id" @disabled(! $selected) class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:opacity-50">
                                        <option value="">All Campuses</option>
                                        @foreach($campuses as $campus)
                                            <option value="{{ $campus->id }}" @selected(($selected?->member?->campus_id ?? $selected?->user?->campus_id) === $campus->id)>{{ $campus->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs text-slate-500">Member Status
                                    <select name="person_status" @disabled(! $selected || ! $selected?->member) class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:opacity-50">
                                        @foreach(['active', 'new member', 'follow-up', 'inactive', 'archived'] as $status)
                                            <option value="{{ $status }}" @selected(($selected?->member?->status ?? 'active') === $status)>{{ Str::headline($status) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-500">
                                    <div>Record Type</div>
                                    <div class="mt-1 text-sm text-slate-900">{{ $selected?->member ? 'Member profile' : 'User account' }}</div>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="text-sm font-semibold text-slate-950">Notification Categories & Channels</h3>
                            <div class="mt-3 grid grid-cols-[1fr_repeat(6,44px)] items-center gap-2 text-xs text-slate-500">
                                <span>Category</span>
                                @foreach($channels as $channel)
                                    <span class="grid place-items-center"><i data-lucide="{{ $channel['icon'] }}" class="size-4"></i></span>
                                @endforeach
                                <span class="grid place-items-center">Critical</span>
                                @foreach($categories as $category)
                                    <div class="flex items-center gap-2 rounded-lg py-1 text-slate-700">
                                        <i data-lucide="{{ $categoryIcons[$category] ?? 'bell' }}" class="size-4 text-violet-600"></i>
                                        <input type="hidden" name="categories[]" value="{{ $category }}">
                                        {{ Str::headline($category === 'care' ? 'pastoral care follow-up' : $category) }}
                                    </div>
                                    @foreach($channels as $key => $channel)
                                        <label class="relative mx-auto inline-flex cursor-pointer items-center">
                                            <input type="checkbox" name="category_channels[{{ $category }}][]" value="{{ $key }}" @checked(in_array($key, $selectedCategoryChannels[$category] ?? [], true)) class="peer sr-only">
                                            <span class="h-5 w-9 rounded-full bg-slate-200 transition peer-checked:bg-violet-600"></span>
                                            <span class="absolute left-0.5 top-0.5 size-4 rounded-full bg-white shadow transition peer-checked:translate-x-4"></span>
                                        </label>
                                    @endforeach
                                    <span class="grid place-items-center text-emerald-600"><i data-lucide="{{ $selected?->critical_alerts ? 'check-circle-2' : 'minus' }}" class="size-4"></i></span>
                                @endforeach
                            </div>
                        </section>

                        <section class="grid gap-3 sm:grid-cols-2">
                            <label class="text-xs text-slate-500">Digest Mode
                                <select name="digest_mode" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                    @foreach(['instant','daily','weekly','off'] as $mode)
                                        <option value="{{ $mode }}" @selected(($selected?->digest_mode ?? 'instant') === $mode)>{{ Str::headline($mode) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">Language
                                <select name="language" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                    @foreach(['en' => 'English', 'es' => 'Spanish', 'fr' => 'French'] as $code => $label)
                                        <option value="{{ $code }}" @selected(($selected?->language ?? 'en') === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">Quiet Start
                                <input name="quiet_hours_start" value="{{ $selected?->quiet_hours_start ? substr((string) $selected->quiet_hours_start, 0, 5) : '22:00' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Quiet End
                                <input name="quiet_hours_end" value="{{ $selected?->quiet_hours_end ? substr((string) $selected->quiet_hours_end, 0, 5) : '06:00' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                        </section>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">Critical Alerts
                                <input type="checkbox" name="critical_alerts" value="1" @checked($selected?->critical_alerts ?? true) class="rounded border-slate-300 text-violet-600">
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">Opted Out
                                <input type="checkbox" name="opted_out" value="1" @checked((bool) $selected?->opted_out_at) class="rounded border-slate-300 text-violet-600">
                            </label>
                        </div>

                        <button @disabled(! $selected) class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white disabled:opacity-50">Save Preferences</button>
                    </div>
                </form>

                <section id="policy-settings" class="grid gap-3 sm:grid-cols-2">
                    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-950">Default Organization Policies</h3>
                        <dl class="mt-3 space-y-3 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Policy Name</dt><dd class="text-right text-slate-800">Standard Notification Policy</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Last Updated</dt><dd class="text-right text-slate-800">{{ now()->format('M d, Y') }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Coverage</dt><dd class="text-right text-violet-600">{{ number_format($stats['coverage']) }} users</dd></div>
                        </dl>
                    </article>
                    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-950">Emergency Override</h3>
                        <dl class="mt-3 space-y-3 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Critical Alerts</dt><dd class="text-emerald-600">Enabled</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Quiet Hours</dt><dd class="text-emerald-600">Override enabled</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Approval Required</dt><dd class="text-slate-800">No</dd></div>
                        </dl>
                    </article>
                    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-950">Do-Not-Disturb</h3>
                        <p class="mt-2 text-sm text-emerald-600">{{ $selected?->quiet_hours_start ? 'Enabled' : 'Not configured' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $selected?->quiet_hours_start ? substr((string) $selected->quiet_hours_start, 0, 5).' - '.substr((string) $selected->quiet_hours_end, 0, 5) : 'User receives notifications immediately.' }}</p>
                    </article>
                    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-950">Consent & Compliance</h3>
                        <dl class="mt-3 space-y-3 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Marketing Communications</dt><dd class="text-emerald-600">{{ $selected?->opted_out_at ? 'Opted Out' : 'Opted In' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Data Processing Consent</dt><dd class="text-emerald-600">Granted</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Last Confirmed</dt><dd class="text-slate-800">{{ $selected?->updated_at?->format('M d, Y') ?? now()->format('M d, Y') }}</dd></div>
                        </dl>
                    </article>
                </section>

                <section id="preference-audit" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-950">Audit History</h3>
                        <a href="{{ route('audit-logs.index') }}" class="text-xs text-violet-600">View All</a>
                    </div>
                    <div class="mt-3 divide-y divide-slate-100">
                        @forelse($preferenceActivity as $activity)
                            <div class="grid grid-cols-[1fr_auto] gap-3 py-2 text-xs">
                                <div><p class="text-slate-800">{{ Str::headline($activity->action) }}</p><p class="text-slate-500">{{ $activity->user?->name ?? 'System' }}</p></div>
                                <time class="text-right text-slate-500">{{ $activity->created_at?->format('M d, h:i A') }}</time>
                            </div>
                        @empty
                            <p class="py-4 text-xs text-slate-500">No preference audit activity recorded yet.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>

        <section class="grid gap-4 xl:grid-cols-[420px_1fr]">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-950">Channel Preference Mix</h2>
                <div class="mt-4 grid items-center gap-4 sm:grid-cols-[190px_1fr]">
                    <div class="h-48"><canvas data-chart="doughnut" data-labels='@json(collect($channelMix)->pluck("label"))' data-values='@json(collect($channelMix)->pluck("value"))' data-colors='@json(collect($channelMix)->pluck("color"))'></canvas></div>
                    <div class="space-y-3 text-sm">
                        @foreach($channelMix as $item)
                            <div class="grid grid-cols-[auto_1fr_auto] items-center gap-2">
                                <span class="size-2 rounded-full" style="background: {{ $item['color'] }}"></span>
                                <span class="text-slate-600">{{ $item['label'] }}</span>
                                <span class="text-slate-950">{{ number_format($item['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('communications.delivery-logs') }}" class="mt-3 block text-center text-sm text-violet-600">View Details</a>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between">
                    <h2 class="text-sm font-semibold text-slate-950">Opt-Out Trend <span class="text-slate-500">(Last 30 Days)</span></h2>
                    <div class="text-right">
                        <div class="text-2xl text-slate-950">{{ number_format($stats['opted_out']) }}</div>
                        <div class="text-xs text-rose-600">current opted-out users</div>
                        <div class="mt-2 text-2xl text-slate-950">{{ $stats['opted_out_rate'] }}%</div>
                        <div class="text-xs text-slate-500">opt-out rate</div>
                    </div>
                </div>
                <div class="mt-4 h-56"><canvas data-chart="multi-line" data-labels='@json($optOutTrend["labels"])' data-datasets='@json($optOutTrend["datasets"])'></canvas></div>
                <a href="{{ route('communications.delivery-logs', ['event_type' => 'PreferenceReminder']) }}" class="mt-3 block text-right text-sm text-violet-600">View Trend Report -></a>
            </article>
        </section>
    </div>
</x-app-layout>
