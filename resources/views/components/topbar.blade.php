@php
    $user = auth()->user();
    $firstName = str($user?->name ?? 'there')->explode(' ')->first();
    $greetingName = trim(($user?->title ? str($user->title)->before(' ')->toString().' ' : '').$firstName);
@endphp

<header class="app-topbar sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex min-h-16 items-center gap-3 px-4 sm:px-6 lg:px-7">
        <button type="button" class="grid size-10 place-items-center rounded-lg text-slate-600 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500" x-on:click="sidebarOpen = true" aria-label="Open sidebar">
            <i data-lucide="menu" class="size-5"></i>
        </button>

        <div class="hidden min-w-0 flex-1 md:block">
            <p class="truncate text-sm text-slate-600"><span class="font-medium text-slate-900">Welcome back, {{ $greetingName }}!</span> Here is what is happening today.</p>
        </div>

        <form action="{{ route('search') }}" method="GET" class="hidden w-full max-w-md lg:block">
            <label class="sr-only" for="global-search">Search</label>
            <div class="relative">
                <input id="global-search" name="q" value="{{ request('q') }}" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 pl-4 pr-11 text-sm outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100" placeholder="Search members, events, media, reports...">
                <button class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-md text-slate-500 hover:bg-slate-100" aria-label="Search">
                    <i data-lucide="search" class="size-4"></i>
                </button>
            </div>
        </form>

        <div class="ml-auto flex items-center gap-1">
            <button class="relative grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500" aria-label="Notifications">
                <i data-lucide="bell" class="size-5"></i>
                <span class="absolute right-1.5 top-1 rounded-full bg-violet-600 px-1.5 text-[10px] font-medium text-white">12</span>
            </button>
            <button class="grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500" aria-label="Messages"><i data-lucide="message-square" class="size-5"></i></button>
            <button class="grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500" aria-label="Calendar"><i data-lucide="calendar-days" class="size-5"></i></button>
            <button class="grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500" aria-label="Help"><i data-lucide="circle-help" class="size-5"></i></button>
            <select class="hidden h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100 xl:block" aria-label="Date range">
                <option>May 19 - May 25, 2024</option>
                <option>This Month</option>
                <option>Last 6 Months</option>
            </select>
            <x-dropdown>
                <x-slot:trigger>
                    <button class="grid size-10 place-items-center overflow-hidden rounded-full bg-slate-900 text-sm font-medium text-white" aria-label="Open user menu">
                        @if ($user?->avatar_src)
                            <img src="{{ $user->avatar_src }}" alt="{{ $user->name }}" class="size-full object-cover">
                        @else
                            {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                        @endif
                    </button>
                </x-slot:trigger>
                <a href="{{ route('profile.edit') }}" class="block rounded-md px-3 py-2 text-sm hover:bg-slate-100">Profile</a>
                <a href="{{ route('account.settings') }}" class="block rounded-md px-3 py-2 text-sm hover:bg-slate-100">Account settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="block w-full rounded-md px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Logout</button>
                </form>
            </x-dropdown>
        </div>
    </div>
</header>
