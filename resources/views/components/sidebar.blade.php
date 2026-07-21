@php
    $items = config('navigation');
    $currentRoute = request()->route()?->getName();
@endphp

<aside
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col overflow-y-auto bg-sidebar text-white shadow-2xl transition-transform duration-200 lg:translate-x-0"
>
    <div class="flex items-center gap-3 px-5 py-5">
        <div class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-violet-400 to-purple-700 shadow-lg">
            <i data-lucide="cross" class="size-8"></i>
        </div>
        <div class="min-w-0">
            <div class="text-lg font-bold leading-tight">{{ config('app.name') }}</div>
            <div class="text-xs leading-tight text-slate-300">{{ config('church.subtitle') }}</div>
        </div>
    </div>

    <nav class="flex-1 space-y-1 px-3 pb-5">
        @foreach ($items as $item)
            @php
                $isActive = $currentRoute === $item['route'];
            @endphp
            <a
                href="{{ route($item['route']) }}"
                class="{{ $isActive ? 'bg-gradient-to-r from-violet-600 to-purple-500 text-white shadow-lg shadow-purple-950/30' : 'text-slate-200 hover:bg-white/10 hover:text-white' }} group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium outline-none transition focus-visible:ring-2 focus-visible:ring-white/70"
                aria-current="{{ $isActive ? 'page' : 'false' }}"
            >
                <i data-lucide="{{ $item['icon'] }}" class="size-4 shrink-0"></i>
                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                @isset($item['badge'])
                    <span class="rounded-full bg-violet-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $item['badge'] }}</span>
                @endisset
            </a>
        @endforeach
    </nav>

    <div class="relative mt-auto px-4 pb-5 pt-10">
        <div class="absolute inset-x-0 bottom-0 h-52 bg-church-silhouette opacity-90"></div>
        <div class="relative flex items-center gap-3 rounded-xl bg-slate-950/35 p-3 backdrop-blur">
            <div class="grid size-11 place-items-center rounded-full bg-gradient-to-br from-amber-200 to-amber-600 text-sm font-bold text-slate-950">
                {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold">{{ auth()->user()?->name }}</div>
                <div class="truncate text-xs text-slate-300">{{ auth()->user()?->title ?? 'Team Member' }}</div>
                <div class="mt-1 flex items-center gap-1 text-xs text-emerald-300"><span class="size-2 rounded-full bg-emerald-400"></span> Online</div>
            </div>
            <i data-lucide="chevron-up" class="size-4 text-slate-300"></i>
        </div>
    </div>
</aside>
