<x-app-layout title="Members Management" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = [
            'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'new' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'inactive' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'follow-up' => 'bg-orange-50 text-orange-700 ring-orange-200',
            'archived' => 'bg-slate-100 text-slate-600 ring-slate-200',
        ];
        $statusDot = [
            'active' => 'bg-emerald-500',
            'new' => 'bg-violet-500',
            'inactive' => 'bg-rose-500',
            'follow-up' => 'bg-orange-500',
            'archived' => 'bg-slate-400',
        ];
        $givingTone = [
            'Tither' => 'bg-emerald-50 text-emerald-700',
            'Regular' => 'bg-blue-50 text-blue-700',
            'None' => 'bg-slate-100 text-slate-500',
        ];
        $currentQuery = request()->query();
        $selectedCampusId = \App\Support\OpaqueId::decode(request('campus_id'), \App\Models\Campus::class);
        $selectedFamilyId = \App\Support\OpaqueId::decode(request('family_id'), \App\Models\Family::class);
        $selectedMinistryId = \App\Support\OpaqueId::decode(request('ministry_id'), \App\Models\Ministry::class);
    @endphp

    <div
        x-data="{
            selected: [],
            more: {{ request()->hasAny(['family_id', 'ministry_id', 'engagement', 'per_page']) ? 'true' : 'false' }},
            addOpen: false,
            importOpen: false,
            detailOpen: {{ $selectedMember ? 'true' : 'false' }},
            detailMode: @js($selectedMode),
            toggleAll(event) {
                const ids = Array.from(document.querySelectorAll('[data-member-checkbox]')).map(input => input.value);
                this.selected = event.target.checked ? ids : [];
            }
        }"
        class="space-y-5"
    >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-2xl bg-violet-100 text-violet-600">
                    <i data-lucide="users-round" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Members Management</h1>
                    <p class="text-sm text-slate-500">Manage members, households, engagement, lifecycle, and pastoral care.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="importOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i data-lucide="upload" class="size-4"></i>
                    Import Members
                </button>
                <a href="{{ route('members.export', $currentQuery) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i data-lucide="download" class="size-4"></i>
                    Export
                </a>
                <a href="{{ route('members.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700">
                    <i data-lucide="user-plus" class="size-4"></i>
                    Add Member
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error') || $errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ session('error') ?? $errors->first() }}</div>
        @endif

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <x-stat-card :metric="['label' => 'Total Members', 'value' => number_format($stats['total']), 'change' => null, 'period' => 'View all members', 'icon' => 'users-round', 'color' => 'purple', 'route' => 'members.index']" />
            <x-stat-card :metric="['label' => 'Active Members', 'value' => number_format($stats['active']), 'change' => null, 'period' => $stats['total'] ? round(($stats['active'] / max($stats['total'], 1)) * 100, 1).'% of total' : 'No members', 'icon' => 'user-check', 'color' => 'emerald', 'route' => 'members.index']" />
            <x-stat-card :metric="['label' => 'New This Month', 'value' => number_format($stats['new']), 'change' => $stats['new'] ? '+'.$stats['new'] : null, 'period' => 'new records', 'icon' => 'sparkles', 'color' => 'indigo', 'route' => 'members.index']" />
            <x-stat-card :metric="['label' => 'First-Time Guests', 'value' => number_format($stats['guests']), 'change' => null, 'period' => 'This month', 'icon' => 'user-round', 'color' => 'teal', 'route' => 'members.index']" />
            <x-stat-card :metric="['label' => 'Retention Rate', 'value' => $stats['retention'].'%', 'change' => $stats['retention'] > 80 ? '+ Good' : null, 'period' => 'active ratio', 'icon' => 'chart-column', 'color' => 'orange', 'route' => 'members.index']" />
            <x-stat-card :metric="['label' => 'Follow-up Needed', 'value' => number_format($stats['follow_up']), 'change' => null, 'period' => 'inactive or follow-up', 'icon' => 'clipboard-check', 'color' => 'rose', 'route' => 'members.index']" />
        </div>

        <form method="GET" action="{{ route('members.index') }}" class="dashboard-card space-y-3">
            <div class="grid gap-3 xl:grid-cols-[1fr_140px_160px_160px_150px_150px_auto_auto]">
                <label class="relative">
                    <input name="q" value="{{ request('q') }}" class="h-10 w-full rounded-lg border border-slate-200 px-3 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-violet-400 focus:ring-4 focus:ring-violet-100" placeholder="Search members by name, email, phone...">
                    <i data-lucide="search" class="absolute right-3 top-3 size-4 text-slate-400"></i>
                </label>
                <select name="status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-700">
                    <option value="">Status: All</option>
                    @foreach (['active' => 'Active', 'new' => 'New Member', 'inactive' => 'Inactive', 'follow-up' => 'Follow-up', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-700">
                    <option value="">Campus: All</option>
                    @foreach ($campuses as $campus)
                        <option value="{{ $campus->opaqueId() }}" @selected($selectedCampusId === $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
                <select name="engagement" class="h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-700">
                    <option value="">Engagement: All</option>
                    <option value="regular" @selected(request('engagement') === 'regular')>Regular Attendance</option>
                    <option value="giving" @selected(request('engagement') === 'giving')>Giving Activity</option>
                    <option value="follow-up" @selected(request('engagement') === 'follow-up')>Needs Follow-up</option>
                </select>
                <select name="ministry_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-700">
                    <option value="">Ministry: All</option>
                    @foreach ($ministries as $ministry)
                        <option value="{{ $ministry->opaqueId() }}" @selected($selectedMinistryId === $ministry->id)>{{ $ministry->name }}</option>
                    @endforeach
                </select>
                <button type="button" @click="more = ! more" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-50 px-3 text-sm font-medium text-violet-600 hover:bg-violet-100">
                    <i data-lucide="sliders-horizontal" class="size-4"></i>
                    More Filters
                </button>
                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-medium text-white hover:bg-violet-700">
                    <i data-lucide="search" class="size-4"></i>
                    Apply
                </button>
                <a href="{{ route('members.index') }}" class="inline-flex h-10 items-center justify-center px-3 text-sm font-medium text-slate-500 hover:text-violet-600">Clear</a>
            </div>
            <div x-show="more" x-cloak class="grid gap-3 border-t border-slate-100 pt-3 md:grid-cols-3">
                <select name="family_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-700">
                    <option value="">Family: All</option>
                    @foreach ($families as $family)
                        <option value="{{ $family->opaqueId() }}" @selected($selectedFamilyId === $family->id)>{{ $family->name }}</option>
                    @endforeach
                </select>
                <select name="per_page" class="h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-700">
                    @foreach ([10, 25, 50] as $value)
                        <option value="{{ $value }}" @selected((int) request('per_page', 10) === $value)>{{ $value }} per page</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <i data-lucide="list-checks" class="size-4 text-violet-600"></i>
                    Filters apply to real member records.
                </div>
            </div>
        </form>

        <div class="grid gap-4 xl:grid-cols-[1fr_330px]">
            <section class="dashboard-card p-0">
                <form method="POST" action="{{ route('members.bulk') }}">
                    @csrf
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-950">Members Directory</h2>
                            <span class="rounded-md bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700">{{ number_format($members->total()) }} members</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <select name="action" class="h-10 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-600" required>
                                <option value="">Bulk Actions</option>
                                <option value="activate">Activate selected</option>
                                <option value="follow-up">Mark follow-up</option>
                                <option value="inactive">Mark inactive</option>
                                <option value="archive">Archive selected</option>
                                <option value="delete">Delete selected</option>
                            </select>
                            <button class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 disabled:opacity-50" :disabled="selected.length === 0">
                                <i data-lucide="check" class="size-4"></i>
                                Apply
                            </button>
                            <a href="{{ route('members.index', array_merge($currentQuery, ['layout' => 'table'])) }}" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Table view"><i data-lucide="list-checks" class="size-4"></i></a>
                            <a href="{{ route('members.index', array_merge($currentQuery, ['layout' => 'cards'])) }}" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Card view"><i data-lucide="layout-grid" class="size-4"></i></a>
                        </div>
                    </div>

                    @if (request('layout') === 'cards')
                        <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($members as $member)
                                <article class="rounded-lg border border-slate-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="grid size-11 place-items-center rounded-full bg-violet-100 text-sm font-semibold text-violet-700">{{ Str::substr($member['firstName'], 0, 1) }}{{ Str::substr($member['lastName'], 0, 1) }}</div>
                                            <div>
                                                <div class="font-semibold text-slate-950">{{ $member['name'] }}</div>
                                                <div class="text-xs text-slate-500">{{ $member['code'] }} · {{ $member['campus'] }}</div>
                                            </div>
                                        </div>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-medium ring-1 {{ $statusTone[$member['status']] ?? $statusTone['active'] }}">{{ Str::headline($member['status']) }}</span>
                                    </div>
                                    <div class="mt-4 grid gap-2 text-sm text-slate-600">
                                        <span class="flex items-center gap-2"><i data-lucide="mail" class="size-4 text-slate-400"></i>{{ $member['email'] }}</span>
                                        <span class="flex items-center gap-2"><i data-lucide="phone" class="size-4 text-slate-400"></i>{{ $member['phone'] }}</span>
                                        <span class="flex items-center gap-2"><i data-lucide="heart-handshake" class="size-4 text-slate-400"></i>{{ $member['ministry'] }}</span>
                                    </div>
                                    <div class="mt-4 flex gap-2">
                                        <a href="{{ route('members.show', ['member' => $member['key']]) }}" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">View</a>
                                        <a href="{{ route('members.show', ['member' => $member['key'], 'edit' => 1]) }}" class="flex-1 rounded-lg bg-violet-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-violet-700">Edit</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table-compact min-w-[1220px]">
                                <thead>
                                    <tr>
                                        <th class="w-10"><input type="checkbox" class="rounded border-slate-300" @change="toggleAll($event)"></th>
                                        <th>Member ID</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Age</th>
                                        <th>Marital Status</th>
                                        <th>Status</th>
                                        <th>Campus</th>
                                        <th>Ministry</th>
                                        <th>Join Date</th>
                                        <th>Attendance</th>
                                        <th>Giving</th>
                                        <th>Last Activity</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($members as $member)
                                        <tr>
                                            <td><input data-member-checkbox type="checkbox" name="members[]" value="{{ $member['key'] }}" x-model="selected" class="rounded border-slate-300"></td>
                                            <td class="font-medium text-slate-700">{{ $member['code'] }}</td>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <div class="grid size-9 place-items-center rounded-full bg-violet-100 text-xs font-semibold text-violet-700">{{ Str::substr($member['firstName'], 0, 1) }}{{ Str::substr($member['lastName'], 0, 1) }}</div>
                                                    <span class="font-semibold text-slate-950">{{ $member['name'] }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $member['gender'] }}</td>
                                            <td>{{ $member['phone'] }}</td>
                                            <td>{{ $member['email'] }}</td>
                                            <td>{{ $member['age'] }}</td>
                                            <td>{{ $member['marital'] }}</td>
                                            <td><span class="rounded-full px-2.5 py-1 text-[11px] font-medium ring-1 {{ $statusTone[$member['status']] ?? $statusTone['active'] }}">{{ Str::headline($member['status']) }}</span></td>
                                            <td>{{ $member['campus'] }}</td>
                                            <td>{{ $member['ministry'] }}</td>
                                            <td>{{ $member['joined'] }}</td>
                                            <td>
                                                <div class="flex h-6 items-end gap-0.5">
                                                    @foreach ($member['attendanceBars'] as $bar)
                                                        <span class="w-1 rounded-sm {{ $member['status'] === 'follow-up' ? 'bg-orange-500' : ($member['status'] === 'inactive' ? 'bg-rose-500' : 'bg-emerald-500') }}" style="height: {{ $bar }}px"></span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td><span class="rounded-md px-2 py-1 text-[11px] font-medium {{ $givingTone[$member['givingStatus']] ?? $givingTone['None'] }}">{{ $member['givingStatus'] }}</span></td>
                                            <td>{{ $member['lastActivity'] }}</td>
                                            <td>
                                                <div class="flex justify-end gap-1">
                                                    <a href="mailto:{{ $member['email'] }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Email member"><i data-lucide="mail" class="size-4"></i></a>
                                                    <a href="{{ route('members.show', ['member' => $member['key']]) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="View member"><i data-lucide="eye" class="size-4"></i></a>
                                                    <a href="{{ route('members.show', ['member' => $member['key'], 'edit' => 1]) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Edit member"><i data-lucide="pencil" class="size-4"></i></a>
                                                    <form method="POST" action="{{ route('members.destroy', ['member' => $member['key']]) }}" onsubmit="return confirm('Remove this member from the directory?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="grid size-8 place-items-center rounded-lg border border-slate-200 text-rose-500 hover:bg-rose-50" title="Delete member"><i data-lucide="x" class="size-4"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="16" class="py-10 text-center text-sm text-slate-500">No members match the current filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </form>
                <div class="flex flex-col gap-3 border-t border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">Showing {{ number_format($members->firstItem() ?? 0) }} to {{ number_format($members->lastItem() ?? 0) }} of {{ number_format($members->total()) }} members</p>
                    {{ $members->links() }}
                </div>
            </section>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-base font-semibold text-slate-950">Members by Status</h2><a href="{{ route('members.index') }}" class="text-xs font-medium text-violet-600">View Full Report</a></div>
                    <div class="space-y-3">
                        @foreach ($statusDistribution as $item)
                            <a href="{{ route('members.index', ['status' => $item['status']]) }}" class="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
                                <span class="flex items-center gap-2 text-slate-600"><span class="size-2 rounded-full {{ $statusDot[$item['status']] ?? 'bg-violet-500' }}"></span>{{ $item['label'] }}</span>
                                <span class="font-semibold text-slate-950">{{ number_format($item['count']) }} <span class="ml-1 font-normal text-slate-400">({{ $item['percent'] }}%)</span></span>
                                <span class="col-span-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><span class="block h-full {{ $statusDot[$item['status']] ?? 'bg-violet-500' }}" style="width: {{ $item['percent'] }}%"></span></span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-base font-semibold text-slate-950">Members by Campus</h2><a href="{{ route('campuses.index') }}" class="text-xs font-medium text-violet-600">View Report</a></div>
                    <div class="space-y-3">
                        @foreach ($campusDistribution as $item)
                            <div class="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
                                <span class="text-slate-600">{{ $item['name'] }}</span>
                                <span class="font-semibold text-slate-950">{{ number_format($item['count']) }} <span class="ml-1 font-normal text-slate-400">({{ $item['percent'] }}%)</span></span>
                                <span class="col-span-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><span class="block h-full bg-blue-500" style="width: {{ $item['percent'] }}%"></span></span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-base font-semibold text-slate-950">Recent Member Activity</h2><a href="{{ route('audit-logs.index', ['keyword' => 'member']) }}" class="text-xs font-medium text-violet-600">View All</a></div>
                    <div class="space-y-4">
                        @forelse ($recentActivity as $activity)
                            <div class="flex gap-3 text-sm">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="badge-check" class="size-4"></i></span>
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-slate-900">{{ $activity->description }}</div>
                                    <div class="text-xs text-slate-500">{{ $activity->created_at->format('M d, Y') }} · {{ $activity->user?->name ?? 'System' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm text-slate-500">No member activity yet.</div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>

        <div x-show="addOpen || importOpen || detailOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="addOpen = false; importOpen = false; detailOpen = false"></div>

        <aside x-show="addOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div><h2 class="text-lg font-semibold text-slate-950">Add Member</h2><p class="mt-1 text-sm text-slate-500">Create a real member record in the directory.</p></div>
                <button type="button" @click="addOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500"><i data-lucide="x" class="size-4"></i></button>
            </div>
            @include('members.partials.form', ['action' => route('members.store'), 'method' => 'POST', 'member' => null])
        </aside>

        <aside x-show="importOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div><h2 class="text-lg font-semibold text-slate-950">Import Members</h2><p class="mt-1 text-sm text-slate-500">Upload a CSV with first_name, last_name, email, phone, campus, status, joined_at.</p></div>
                <button type="button" @click="importOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500"><i data-lucide="x" class="size-4"></i></button>
            </div>
            <form method="POST" action="{{ route('members.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <label class="block space-y-2 text-sm font-medium text-slate-600">CSV File<input name="members_file" type="file" accept=".csv,text/csv,text/plain" required class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-violet-50 file:px-3 file:py-1 file:text-xs file:font-medium file:text-violet-700"></label>
                <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700"><i data-lucide="upload" class="size-4"></i> Import Members</button>
            </form>
        </aside>

        @if ($selectedMember)
            <aside x-show="detailOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950" x-text="detailMode === 'edit' ? 'Edit Member' : 'Member Profile'"></h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $selectedMember['code'] }} · {{ $selectedMember['campus'] }}</p>
                    </div>
                    <a href="{{ route('members.index', \Illuminate\Support\Arr::except($currentQuery, ['view', 'edit'])) }}" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500"><i data-lucide="x" class="size-4"></i></a>
                </div>
                <template x-if="detailMode !== 'edit'">
                    <div class="space-y-4">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="flex items-center gap-4">
                                <div class="grid size-16 place-items-center rounded-full bg-violet-100 text-lg font-semibold text-violet-700">{{ Str::substr($selectedMember['firstName'], 0, 1) }}{{ Str::substr($selectedMember['lastName'], 0, 1) }}</div>
                                <div>
                                    <h3 class="text-xl font-semibold text-slate-950">{{ $selectedMember['name'] }}</h3>
                                    <p class="text-sm text-slate-500">{{ $selectedMember['ministry'] }}</p>
                                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium ring-1 {{ $statusTone[$selectedMember['status']] ?? $statusTone['active'] }}">{{ Str::headline($selectedMember['status']) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([['Mail', $selectedMember['email'], 'mail'], ['Phone', $selectedMember['phone'], 'phone'], ['Family', $selectedMember['family'], 'users-round'], ['Joined', $selectedMember['joined'], 'calendar-days'], ['Attendance', $selectedMember['attendance'].' check-ins', 'chart-column'], ['Giving', $selectedMember['givingStatus'], 'wallet']] as [$label, $value, $icon])
                                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                                    <div class="mb-1 flex items-center gap-2 text-xs font-medium text-slate-500"><i data-lucide="{{ $icon }}" class="size-4 text-violet-600"></i>{{ $label }}</div>
                                    <div class="font-medium text-slate-900">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('members.index', array_merge($currentQuery, ['edit' => $selectedMember['key']])) }}" class="flex-1 rounded-lg bg-violet-600 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-violet-700">Edit Profile</a>
                            <a href="mailto:{{ $selectedMember['email'] }}" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">Send Email</a>
                        </div>
                    </div>
                </template>
                <template x-if="detailMode === 'edit'">
                    <div>
                        @include('members.partials.form', ['action' => route('members.update', ['member' => $selectedMember['key']]), 'method' => 'PUT', 'member' => $selectedMember])
                    </div>
                </template>
            </aside>
        @endif
    </div>
</x-app-layout>
