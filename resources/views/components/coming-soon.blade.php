@props(['module'])

<div class="mx-auto max-w-4xl">
    <div class="dashboard-card overflow-hidden p-0">
        <div class="border-b border-slate-200 bg-gradient-to-r from-slate-950 via-violet-950 to-slate-900 p-8 text-white">
            <div class="grid size-16 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                <i data-lucide="{{ $module['icon'] }}" class="size-8"></i>
            </div>
            <h1 class="mt-5 text-3xl font-bold tracking-normal">{{ $module['label'] }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-300">This module is currently under development. The route, navigation state, permissions, and layout are already wired so the implementation can be added incrementally.</p>
        </div>
        <div class="grid gap-6 p-8 md:grid-cols-[1fr_280px]">
            <div>
                <h2 class="text-base font-bold text-slate-900">Planned capabilities</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach (($module['planned'] ?? []) as $capability)
                        <li class="flex items-center gap-2 text-sm text-slate-700">
                            <i data-lucide="check-circle-2" class="size-4 text-emerald-600"></i>
                            <span>{{ $capability }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Development status</div>
                <div class="mt-2 text-lg font-bold text-slate-900">Foundation ready</div>
                <p class="mt-2 text-sm text-slate-600">Controllers, routes, shared shell, and permissions are prepared for this module.</p>
                <div class="mt-5 flex flex-col gap-3">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">
                        <i data-lucide="layout-dashboard" class="size-4"></i> Return to Dashboard
                    </a>
                    <button disabled class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-400">
                        <i data-lucide="bell" class="size-4"></i> Notify Me
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
