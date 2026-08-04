@props(['module'])

@php
    $capabilityIcons = [
        'sparkles',
        'layers-3',
        'file-down',
        'calendar-clock',
        'chart-no-axes-combined',
        'sliders-horizontal',
    ];
    $plannedCapabilities = collect($module['planned'] ?? [])->values();
@endphp

<div class="mx-auto max-w-6xl space-y-5">
    <section class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="absolute inset-y-0 left-0 w-1.5 bg-violet-600"></div>
        <div class="relative grid gap-6 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-center">
            <div class="min-w-0">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                    <span class="grid size-16 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                        <i data-lucide="{{ $module['icon'] }}" class="size-8"></i>
                    </span>
                    <div class="min-w-0">
                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-100">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-60"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
                            </span>
                            Under development
                        </span>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">{{ $term($module['label']) }}</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500 sm:text-base">We’re building a thoughtful, reliable experience for this part of EcclesiaOS. The foundation is ready and the complete module is coming soon.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-violet-100 bg-violet-50 p-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-violet-700">Release status</span>
                    <i data-lucide="construction" class="size-5 text-violet-600"></i>
                </div>
                <p class="mt-3 text-xl font-semibold text-slate-950">Coming soon</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">Core routing, access controls, navigation and the shared interface are already prepared.</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-white ring-1 ring-violet-100">
                    <div class="h-full w-1/3 rounded-full bg-violet-600"></div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] font-medium text-slate-500">
                    <span>Foundation ready</span>
                    <span>In progress</span>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 border-b border-slate-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">What’s being built</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-950">Planned capabilities</h2>
                </div>
                <span class="text-xs text-slate-500">{{ $plannedCapabilities->count() }} features planned</span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @forelse($plannedCapabilities as $capability)
                    <div class="group flex min-w-0 items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-violet-200 hover:bg-violet-50/50">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-violet-600 ring-1 ring-slate-200 transition group-hover:ring-violet-200">
                            <i data-lucide="{{ $capabilityIcons[$loop->index % count($capabilityIcons)] }}" class="size-5"></i>
                        </span>
                        <span class="min-w-0">
                            <strong class="block text-sm font-semibold text-slate-900">{{ $term($capability) }}</strong>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">Planned for the upcoming module release.</span>
                        </span>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 sm:col-span-2">
                        Feature planning is currently in progress.
                    </div>
                @endforelse
            </div>

            <div class="mt-6 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="shield-check" class="size-5"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Built into your existing workspace</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Your current role permissions and organization access will apply when this module launches.</p>
                    </div>
                </div>
                <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                    <i data-lucide="badge-check" class="size-4"></i>
                    Foundation ready
                </span>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-lg bg-blue-50 text-blue-600"><i data-lucide="list-checks" class="size-5"></i></span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Development</p>
                        <h2 class="mt-0.5 font-semibold text-slate-950">Module progress</h2>
                    </div>
                </div>

                <ol class="mt-5 space-y-0">
                    @foreach([
                        ['Foundation prepared', 'Routes, permissions and navigation are connected.', 'circle-check', 'text-emerald-600 bg-emerald-50', true],
                        ['Experience in development', 'Workflows, screens and module data are being built.', 'loader-circle', 'text-violet-600 bg-violet-50', false],
                        ['Release and rollout', 'Final validation and availability will follow.', 'rocket', 'text-slate-400 bg-slate-100', false],
                    ] as [$title, $copy, $icon, $iconClass, $complete])
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @unless($loop->last)<span class="absolute left-4 top-8 h-[calc(100%-2rem)] w-px {{ $complete ? 'bg-emerald-200' : 'bg-slate-200' }}"></span>@endunless
                            <span class="relative z-10 grid size-8 shrink-0 place-items-center rounded-full {{ $iconClass }}"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                            <span class="pt-1">
                                <strong class="block text-sm font-semibold text-slate-900">{{ $title }}</strong>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $copy }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600"><i data-lucide="bell-ring" class="size-5"></i></span>
                    <div>
                        <h2 class="font-semibold text-slate-950">Stay informed</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Release updates will be announced inside EcclesiaOS when this module becomes available.</p>
                    </div>
                </div>
                <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2.5 text-center text-xs font-medium text-slate-500 ring-1 ring-slate-200">
                    <i data-lucide="info" class="mr-1 inline size-3.5 align-[-2px]"></i>
                    No action is needed right now
                </div>
            </section>

            <a href="{{ route('dashboard') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                <i data-lucide="layout-dashboard" class="size-4"></i>
                Return to Dashboard
            </a>
        </aside>
    </div>
</div>
