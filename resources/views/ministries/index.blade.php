<x-app-layout :title="$terminology['ministry_plural']" :breadcrumbs="$breadcrumbs">
    @php
        $statusRows = collect([
            ['label' => 'Active', 'value' => $stats['active'], 'hex' => '#10b981'],
            ['label' => 'Inactive', 'value' => max($stats['total'] - $stats['active'], 0), 'hex' => '#f97316'],
        ])->filter(fn ($row) => $row['value'] > 0)->values();
        $statusTotal = max($statusRows->sum('value'), 1);
        $donutGradient = function ($rows, int $total): string {
            $running = 0;
            $segments = [];

            foreach ($rows as $row) {
                $start = round(($running / $total) * 100, 2);
                $running += (int) $row['value'];
                $end = round(min(100, ($running / $total) * 100), 2);
                $segments[] = "{$row['hex']} {$start}% {$end}%";
            }

            return count($segments) > 0 ? 'conic-gradient('.implode(', ', $segments).')' : '#e2e8f0';
        };
    @endphp

    <div
        x-data="{
            search: '',
            campus: '',
            status: '',
            expanded: null,
            addOpen: false,
            matches(row) {
                const text = row.dataset.search || '';
                return (!this.search || text.includes(this.search.toLowerCase()))
                    && (!this.campus || row.dataset.campus === this.campus)
                    && (!this.status || row.dataset.status === this.status);
            },
            visibleCount() {
                return Array.from(document.querySelectorAll('[data-ministry-row]')).filter(row => this.matches(row)).length;
            },
            clearFilters() {
                this.search = '';
                this.campus = '';
                this.status = '';
            }
        }"
        class="grid gap-4 xl:grid-cols-[1fr_390px]"
    >
        <main class="min-w-0 space-y-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div class="grid size-14 place-items-center rounded-full bg-violet-100 text-violet-600">
                        <i data-lucide="landmark" class="size-7"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-950">{{ $terminology['ministry_plural'] }}</h1>
                        <p class="text-sm text-slate-500">Manage {{ Str::lower($terminology['ministry_plural']) }}, leaders, volunteers, and {{ Str::lower($terminology['campus_singular']) }} assignments.</p>
                    </div>
                </div>
                <button type="button" x-on:click="addOpen = true; $nextTick(() => $refs.createPanel.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    Add {{ $terminology['ministry_singular'] }}
                    <i data-lucide="chevron-up" class="size-4 rotate-180"></i>
                </button>
            </div>

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card :metric="['label' => 'Total '.$terminology['ministry_plural'], 'value' => number_format($stats['total']), 'change' => null, 'period' => 'Across assigned scope', 'icon' => 'landmark', 'color' => 'purple', 'route' => 'ministries.index']" />
                <x-stat-card :metric="['label' => 'Active', 'value' => number_format($stats['active']), 'change' => null, 'period' => 'Ready for service', 'icon' => 'check-circle-2', 'color' => 'emerald', 'route' => 'ministries.index']" />
                <x-stat-card :metric="['label' => $terminology['campus_plural'].' Covered', 'value' => number_format($stats['campuses']), 'change' => null, 'period' => 'With ministries', 'icon' => 'map-pin', 'color' => 'blue', 'route' => 'ministries.index']" />
                <x-stat-card :metric="['label' => 'Volunteers', 'value' => number_format($stats['volunteers']), 'change' => null, 'period' => 'Assigned teams', 'icon' => 'users-round', 'color' => 'orange', 'route' => 'ministries.index']" />
            </div>

            <section class="dashboard-card p-0">
                <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_190px_150px_auto]">
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                        <input x-model="search" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 pr-9 text-sm" placeholder="Search {{ Str::lower($terminology['ministry_plural']) }}, leaders, or descriptions...">
                    </div>
                    <select x-model="campus" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All {{ $terminology['campus_plural'] }}</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                        @endforeach
                    </select>
                    <select x-model="status" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button type="button" x-on:click="clearFilters()" class="px-3 py-2.5 text-sm font-semibold text-violet-600">Clear</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[1040px]">
                        <thead>
                            <tr>
                                <th class="w-9"></th>
                                <th>{{ $terminology['ministry_singular'] }}</th>
                                <th>{{ $terminology['campus_singular'] }}</th>
                                <th>Leader</th>
                                <th class="text-center">Volunteers</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ministries as $ministry)
                                @php
                                    $leaderName = $ministry->leader ? $ministry->leader->first_name.' '.$ministry->leader->last_name : 'Unassigned';
                                    $searchText = strtolower($ministry->name.' '.$leaderName.' '.$ministry->campus?->name.' '.$ministry->description.' '.$ministry->status);
                                @endphp
                                <tr data-ministry-row data-search="{{ $searchText }}" data-campus="{{ $ministry->campus_id }}" data-status="{{ $ministry->status }}" x-show="matches($el)">
                                    <td><button type="button" x-on:click="expanded = expanded === '{{ $ministry->id }}' ? null : '{{ $ministry->id }}'" class="grid size-7 place-items-center rounded-md text-slate-500 hover:bg-slate-50"><i data-lucide="chevron-right" x-bind:class="expanded === '{{ $ministry->id }}' ? 'rotate-90' : ''" class="size-4 transition"></i></button></td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="grid size-9 place-items-center rounded-full bg-violet-50 text-violet-600">
                                                <i data-lucide="landmark" class="size-5"></i>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $ministry->name }}</div>
                                                <div class="max-w-sm truncate text-xs text-slate-500">{{ $ministry->description ?: 'No description recorded.' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2 text-sm text-slate-600">
                                            <i data-lucide="map-pin" class="size-4 text-slate-500"></i>
                                            {{ $ministry->campus?->name ?? 'Unassigned' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="grid size-7 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ Str::upper(Str::substr($leaderName, 0, 1)) }}</span>
                                            <span class="text-sm text-slate-700">{{ $leaderName }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center font-semibold text-slate-900">{{ number_format($ministry->volunteers_count) }}</td>
                                    <td><x-status-badge :status="$ministry->status" /></td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <button form="update-ministry-{{ $ministry->id }}" class="inline-grid size-8 place-items-center rounded-lg border border-violet-200 text-violet-700 hover:bg-violet-50" title="Save changes"><i data-lucide="save" class="size-4"></i></button>
                                            <button form="delete-ministry-{{ $ministry->id }}" class="inline-grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Archive"><i data-lucide="archive" class="size-4"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-cloak x-show="expanded === '{{ $ministry->id }}' && matches($el.previousElementSibling)">
                                    <td></td>
                                    <td colspan="6" class="bg-slate-50/70">
                                        <form id="update-ministry-{{ $ministry->id }}" method="POST" action="{{ route('ministries.update', $ministry) }}" class="grid gap-3 py-3 md:grid-cols-[1.1fr_1fr_1fr_140px]">
                                            @csrf
                                            @method('PUT')
                                            <input name="name" value="{{ old('name', $ministry->name) }}" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <select name="leader_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <option value="">No leader assigned</option>
                                                @foreach ($leaders as $leader)
                                                    <option value="{{ $leader->id }}" @selected($ministry->leader_id === $leader->id)>{{ $leader->first_name }} {{ $leader->last_name }}</option>
                                                @endforeach
                                            </select>
                                            <select name="campus_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                @foreach ($campuses as $campus)
                                                    <option value="{{ $campus->id }}" @selected($ministry->campus_id === $campus->id)>{{ $campus->name }}</option>
                                                @endforeach
                                            </select>
                                            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <option value="active" @selected($ministry->status === 'active')>Active</option>
                                                <option value="inactive" @selected($ministry->status === 'inactive')>Inactive</option>
                                            </select>
                                            <textarea name="description" rows="2" class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-4">{{ $ministry->description }}</textarea>
                                        </form>
                                        <form id="delete-ministry-{{ $ministry->id }}" method="POST" action="{{ route('ministries.destroy', $ministry) }}" class="hidden">@csrf @method('DELETE')</form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center">
                                        <x-empty-state icon="landmark" title="No ministries found" message="Create the first ministry for this campus." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
                    <span>Showing <span x-text="visibleCount()"></span> of {{ number_format($ministries->count()) }} {{ Str::lower($terminology['ministry_plural']) }}</span>
                    <div class="flex items-center gap-2">
                        <button class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-400"><i data-lucide="arrow-left" class="size-4"></i></button>
                        <span class="grid size-9 place-items-center rounded-lg bg-violet-600 text-sm font-semibold text-white">1</span>
                        <button class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600"><i data-lucide="arrow-right" class="size-4"></i></button>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-3">
                <section class="dashboard-card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Status Overview</h2>
                        <span class="text-xs font-semibold text-violet-600">{{ number_format($stats['total']) }} total</span>
                    </div>
                    <div class="grid items-center gap-4 md:grid-cols-[150px_1fr]">
                        <div class="relative mx-auto size-36">
                            <div class="absolute inset-0 rounded-full p-[15px]" style="background: {{ $donutGradient($statusRows, $statusTotal) }}">
                                <div class="size-full rounded-full bg-white"></div>
                            </div>
                            <canvas class="relative size-full" data-chart="doughnut" data-labels='@js($statusRows->pluck('label'))' data-values='@js($statusRows->pluck('value'))' data-colors='@js($statusRows->pluck('hex'))'></canvas>
                        </div>
                        <div class="space-y-2">
                            @foreach($statusRows as $row)
                                <div class="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
                                    <span class="flex min-w-0 items-center gap-2 text-slate-600"><span class="size-2.5 rounded-full" style="background: {{ $row['hex'] }}"></span><span class="truncate">{{ $row['label'] }}</span></span>
                                    <span class="font-semibold text-slate-900">{{ number_format($row['value']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">{{ $terminology['campus_singular'] }} Coverage</h2>
                        <span class="text-xs font-semibold text-violet-600">{{ number_format($stats['campuses']) }}</span>
                    </div>
                    <div class="space-y-3 text-sm">
                        @foreach($campuses->take(6) as $campus)
                            @php($count = $ministries->where('campus_id', $campus->id)->count())
                            <div class="grid grid-cols-[1fr_auto] items-center gap-3">
                                <span class="truncate text-slate-600">{{ $campus->name }}</span>
                                <span class="font-semibold text-slate-900">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Team Activity</h2>
                        <span class="text-xs font-semibold text-violet-600">{{ number_format($stats['volunteers']) }} volunteers</span>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="user-check" class="size-4"></i></span><span class="text-slate-600">Active {{ Str::lower($terminology['ministry_plural']) }}</span><span class="font-semibold text-slate-900">{{ number_format($stats['active']) }}</span></div>
                        <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-blue-50 text-blue-600"><i data-lucide="map-pin" class="size-4"></i></span><span class="text-slate-600">{{ $terminology['campus_plural'] }} covered</span><span class="font-semibold text-slate-900">{{ number_format($stats['campuses']) }}</span></div>
                        <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-orange-50 text-orange-600"><i data-lucide="users-round" class="size-4"></i></span><span class="text-slate-600">Volunteer assignments</span><span class="font-semibold text-slate-900">{{ number_format($stats['volunteers']) }}</span></div>
                    </div>
                </section>
            </div>
        </main>

        <aside x-ref="createPanel" class="dashboard-card h-fit xl:sticky xl:top-20" x-bind:class="addOpen ? 'ring-2 ring-violet-100' : ''">
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Create {{ $terminology['ministry_singular'] }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Add a ministry to the selected {{ Str::lower($terminology['campus_singular']) }}.</p>
                </div>
                <button type="button" x-on:click="addOpen = false" class="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-50" title="Close panel"><i data-lucide="x" class="size-5"></i></button>
            </div>

            <form id="create-ministry-form" method="POST" action="{{ route('ministries.store') }}" class="space-y-5">
                @csrf
                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid size-6 place-items-center rounded-md bg-violet-600 text-xs font-semibold text-white">1</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $terminology['ministry_singular'] }} Details</span>
                    </div>
                    <div class="space-y-3">
                        <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['ministry_singular'] }} Name
                            <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900" placeholder="Worship {{ $terminology['ministry_singular'] }}">
                        </label>
                        <label class="space-y-1 text-xs font-medium text-slate-500">Description
                            <textarea name="description" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900" placeholder="Purpose, responsibilities, and serving focus.">{{ old('description') }}</textarea>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid size-6 place-items-center rounded-md bg-violet-600 text-xs font-semibold text-white">2</span>
                        <span class="text-sm font-semibold text-slate-900">Assignment</span>
                    </div>
                    <div class="space-y-3">
                        <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['campus_singular'] }}
                            <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900">
                                @foreach ($campuses as $campus)
                                    <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1 text-xs font-medium text-slate-500">Leader
                            <select name="leader_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900">
                                <option value="">No leader assigned</option>
                                @foreach ($leaders as $leader)
                                    <option value="{{ $leader->id }}" @selected((string) old('leader_id') === (string) $leader->id)>{{ $leader->first_name }} {{ $leader->last_name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid size-6 place-items-center rounded-md bg-violet-600 text-xs font-semibold text-white">3</span>
                        <span class="text-sm font-semibold text-slate-900">Status</span>
                    </div>
                    <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-3">
                    <button type="button" x-on:click="addOpen = false" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white">
                        <i data-lucide="check" class="size-4"></i>
                        Save
                    </button>
                </div>
            </form>
        </aside>
    </div>
</x-app-layout>
