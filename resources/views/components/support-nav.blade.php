@php
    $items = [
        ['label' => 'Overview', 'route' => 'support.index', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('support.index')],
        ['label' => 'My Tickets', 'route' => 'support.tickets.index', 'icon' => 'inbox', 'active' => request()->routeIs('support.tickets.*')],
        ['label' => 'Community', 'route' => 'support.community', 'icon' => 'messages-square', 'active' => request()->routeIs('support.community*')],
        ['label' => 'Knowledge Base', 'route' => 'support.knowledge', 'icon' => 'book-open', 'active' => request()->routeIs('support.knowledge*')],
        ['label' => 'Live Support', 'route' => 'support.live', 'icon' => 'headphones', 'active' => request()->routeIs('support.live*')],
    ];
    if (auth()->user()?->isSuperAdministrator() || auth()->user()?->hasPermission('manage settings')) {
        $items[] = ['label' => 'Central Connection', 'route' => 'central-support.index', 'icon' => 'radio-tower', 'active' => request()->routeIs('central-support.*')];
    }
@endphp

<nav aria-label="Support workspace" class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm">
    <div class="flex min-w-max items-center gap-1">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-xs font-bold transition {{ $item['active'] ? 'bg-violet-50 text-violet-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}" @if($item['active']) aria-current="page" @endif>
                <i data-lucide="{{ $item['icon'] }}" class="size-3.5"></i>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
