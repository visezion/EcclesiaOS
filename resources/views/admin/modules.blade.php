<x-app-layout title="Module Management" :breadcrumbs="$breadcrumbs">
    @php
        $toneClasses = [
            'violet' => 'bg-violet-50 text-violet-600 ring-violet-100',
            'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
            'blue' => 'bg-blue-50 text-blue-600 ring-blue-100',
            'rose' => 'bg-rose-50 text-rose-600 ring-rose-100',
            'orange' => 'bg-orange-50 text-orange-600 ring-orange-100',
            'sky' => 'bg-sky-50 text-sky-600 ring-sky-100',
            'amber' => 'bg-amber-50 text-amber-600 ring-amber-100',
            'cyan' => 'bg-cyan-50 text-cyan-600 ring-cyan-100',
            'slate' => 'bg-slate-50 text-slate-600 ring-slate-100',
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Module Management</h1>
                <p class="mt-1 text-sm text-slate-500">Enable or disable modules and features across the KingdomHub platform.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('modules.reset') }}">
                    @csrf
                    @method('PUT')
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="rotate-ccw" class="size-4"></i>
                        Restore Default Modules
                    </button>
                </form>
                <button form="module-settings-form" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="save" class="size-4"></i>
                    Save Changes
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif

        @if (session('error') || $errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
                {{ session('error') ?? $errors->first() }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach ($stats as $stat)
                <section class="dashboard-card flex min-h-[112px] items-center gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-xl ring-1 {{ $toneClasses[$stat['tone']] ?? $toneClasses['violet'] }}">
                        <i data-lucide="{{ $stat['icon'] }}" class="size-6"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-500">{{ $stat['label'] }}</div>
                        <div class="mt-1 truncate text-2xl font-bold text-slate-950">{{ $stat['value'] }}</div>
                        <div class="mt-1 truncate text-xs font-medium {{ $stat['tone'] === 'rose' ? 'text-rose-600' : ($stat['tone'] === 'emerald' ? 'text-emerald-600' : 'text-slate-500') }}">{{ $stat['sub'] }}</div>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_330px]">
            <main class="space-y-4">
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 p-4">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-950">All Modules</h2>
                                <p class="mt-1 text-sm text-slate-500">Customize the system for your church. Disabled modules are hidden from users and direct URLs are blocked.</p>
                            </div>
                            <form method="GET" action="{{ route('modules.index') }}" class="grid gap-2 md:grid-cols-[minmax(220px,1fr)_170px_150px_auto_auto]">
                                <label class="relative">
                                    <span class="sr-only">Search modules</span>
                                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                                    <input name="q" value="{{ $filters['q'] }}" placeholder="Search modules..." class="h-10 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm text-slate-900">
                                </label>
                                <label>
                                    <span class="sr-only">Category</span>
                                    <select name="category" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-900">
                                        <option value="all" @selected($filters['category'] === 'all')>All Categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category['key'] }}" @selected($filters['category'] === $category['key'])>{{ $category['label'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span class="sr-only">Status</span>
                                    <select name="status" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-900">
                                        @foreach (['all' => 'All', 'enabled' => 'Enabled', 'disabled' => 'Disabled', 'required' => 'Required'] as $value => $label)
                                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    <i data-lucide="filter" class="size-4"></i>
                                    Filters
                                </button>
                                @if ($filters['q'] !== '' || $filters['category'] !== 'all' || $filters['status'] !== 'all')
                                    <a href="{{ route('modules.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-violet-600 hover:bg-violet-50">Clear</a>
                                @endif
                            </form>
                        </div>
                    </div>

                    <form id="module-settings-form" method="POST" action="{{ route('modules.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Module</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Usage</th>
                                        <th class="px-4 py-3">Description</th>
                                        <th class="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($moduleSettings['modules'] as $module)
                                        <tr class="{{ $module['disabled'] ? 'bg-slate-50/70' : 'bg-white' }}">
                                            <td class="px-4 py-4">
                                                <div class="flex min-w-[230px] items-center gap-3">
                                                    <span class="grid size-10 shrink-0 place-items-center rounded-lg ring-1 {{ $module['disabled'] ? 'bg-slate-100 text-slate-500 ring-slate-200' : 'bg-violet-50 text-violet-600 ring-violet-100' }}">
                                                        <i data-lucide="{{ $module['icon'] ?? 'layout-grid' }}" class="size-5"></i>
                                                    </span>
                                                    <div class="min-w-0">
                                                        <div class="font-semibold text-slate-950">{{ $module['label'] }}</div>
                                                        <div class="mt-0.5 truncate text-xs text-slate-500">{{ $module['route'] }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $module['category_label'] }}</span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $module['disabled'] ? 'bg-rose-50 text-rose-700 ring-rose-100' : 'bg-emerald-50 text-emerald-700 ring-emerald-100' }}">
                                                    {{ $module['disabled'] ? 'Disabled' : 'Enabled' }}
                                                </span>
                                                @if ($module['required'])
                                                    <span class="ml-1 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-100">Required</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-slate-600">{{ $module['usage'] }}</td>
                                            <td class="max-w-md px-4 py-4 text-slate-600">{{ $module['description'] }}</td>
                                            <td class="px-4 py-4">
                                                <div class="flex items-center justify-end gap-3">
                                                    @if ($module['required'])
                                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
                                                            <i data-lucide="lock" class="size-4"></i>
                                                            Locked
                                                        </span>
                                                    @else
                                                        <label class="relative inline-flex cursor-pointer items-center">
                                                            <input type="checkbox" name="enabled_modules[]" value="{{ $module['route'] }}" @checked(! $module['disabled']) class="peer sr-only">
                                                            <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-violet-600"></span>
                                                            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                                                        </label>
                                                    @endif

                                                    @if (! $module['disabled'] && \Illuminate\Support\Facades\Route::has($module['route']))
                                                        <a href="{{ route($module['route']) }}" class="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-50 hover:text-violet-600" title="Open {{ $module['label'] }}">
                                                            <i data-lucide="external-link" class="size-4"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">No modules match the selected filters.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <span>Showing {{ number_format($moduleSettings['modules']->count()) }} of {{ number_format($moduleSettings['total']) }} modules</span>
                        <span class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">All results</span>
                    </div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-950">
                        <i data-lucide="settings-2" class="size-4 text-violet-600"></i>
                        Configuration
                    </h2>
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-semibold text-slate-900">Main Navigation</div>
                                <div class="mt-1 text-xs text-slate-500">Enabled modules appear in the sidebar.</div>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">On</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-semibold text-slate-900">Direct URL Blocking</div>
                                <div class="mt-1 text-xs text-slate-500">Disabled module routes return 404.</div>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">On</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-semibold text-slate-900">Search Visibility</div>
                                <div class="mt-1 text-xs text-slate-500">Disabled modules are removed from search.</div>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">On</span>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-950">
                        <i data-lucide="tags" class="size-4 text-violet-600"></i>
                        Module Categories
                    </h2>
                    <div class="space-y-3">
                        @foreach ($categories as $category)
                            <a href="{{ route('modules.index', ['category' => $category['key']]) }}" class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="grid size-8 place-items-center rounded-lg ring-1 {{ $toneClasses[$category['tone']] ?? $toneClasses['slate'] }}">
                                        <i data-lucide="{{ $category['icon'] }}" class="size-4"></i>
                                    </span>
                                    <span class="truncate text-sm font-medium text-slate-700">{{ $category['label'] }}</span>
                                </span>
                                <span class="text-xs font-semibold text-slate-500">{{ $category['count'] }} modules</span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-950">
                        <i data-lucide="activity" class="size-4 text-violet-600"></i>
                        Recent Module Activity
                    </h2>
                    <div class="space-y-4">
                        @forelse ($recentActivity as $activity)
                            <div class="flex gap-3 text-sm">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600">
                                    <i data-lucide="layout-grid" class="size-4"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="font-semibold text-slate-900">{{ $activity->description }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $activity->created_at?->format('M d, Y h:i A') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">No module changes have been recorded yet.</div>
                        @endforelse
                    </div>
                </section>

                <section class="dashboard-card border-violet-100 bg-violet-50/40">
                    <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950">
                        <i data-lucide="circle-help" class="size-4 text-violet-600"></i>
                        About Module Management
                    </h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Disabling a module hides it from navigation for all users and blocks access to its routes. Existing database records are preserved and become available again when the module is re-enabled.</p>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
