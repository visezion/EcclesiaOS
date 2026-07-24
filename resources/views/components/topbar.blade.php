@php
    $user = auth()->user();
    $firstName = str($user?->name ?? 'there')->explode(' ')->first();
    $greetingName = trim(($user?->title ? str($user->title)->before(' ')->toString().' ' : '').$firstName);
    $canAccess = fn (?string $route, ?string $permission = null): bool => $route !== null
        && \Illuminate\Support\Facades\Route::has($route)
        && ! \App\Support\ModuleRegistry::isDisabledRoute($route)
        && ($user?->isSuperAdministrator() || $permission === null || $user?->hasPermission($permission));
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
    $notificationUrl = $canAccess('communications.notifications', 'manage communications')
        ? route('communications.notifications')
        : route('account.settings').'#notifications';
    $messagesUrl = $canAccess('communications.index', 'manage communications')
        ? route('communications.index')
        : route('account.settings').'#notifications';
    $calendarUrl = $canAccess('calendar.index', 'manage events')
        ? route('calendar.index')
        : route('dashboard');
    $helpUrl = $canAccess('developer-hub.index', 'manage settings')
        ? route('developer-hub.index')
        : route('account.settings');
@endphp

<header class="app-topbar sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex min-h-16 items-center gap-3 px-4 sm:px-6 lg:px-7">
        <button type="button" class="grid size-10 place-items-center rounded-lg text-slate-600 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500 lg:hidden" x-on:click="sidebarOpen = true" aria-label="Open sidebar">
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
            <button type="button" x-on:click="mobileSearchOpen = ! mobileSearchOpen" class="grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500 lg:hidden" aria-label="Open search">
                <i data-lucide="search" class="size-5"></i>
            </button>
            <a href="{{ $notificationUrl }}" class="relative grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500" aria-label="Notifications">
                <i data-lucide="bell" class="size-5"></i>
                @if($unreadCount > 0)
                    <span class="absolute right-1.5 top-1 rounded-full bg-violet-600 px-1.5 text-[10px] font-medium text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
            </a>
            <a href="{{ $messagesUrl }}" class="hidden size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500 sm:grid" aria-label="Messages"><i data-lucide="message-square" class="size-5"></i></a>
            <a href="{{ $calendarUrl }}" class="hidden size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500 sm:grid" aria-label="Calendar"><i data-lucide="calendar-days" class="size-5"></i></a>
            <a href="{{ $helpUrl }}" class="hidden size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-violet-500 md:grid" aria-label="Help"><i data-lucide="circle-help" class="size-5"></i></a>
            <div class="hidden h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 xl:flex">
                <i data-lucide="calendar-clock" class="size-4 text-slate-400"></i>
                <span>{{ now()->format('M d, Y') }}</span>
            </div>
            <x-dropdown>
                <x-slot:trigger>
                    <button type="button" class="grid size-10 place-items-center overflow-hidden rounded-full bg-slate-900 text-sm font-medium text-white ring-2 ring-white" aria-label="Open user menu">
                        @if ($user?->avatar_src)
                            <img src="{{ $user->avatar_src }}" alt="{{ $user->name }}" class="size-full object-cover">
                        @else
                            {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                        @endif
                    </button>
                </x-slot:trigger>
                <div class="border-b border-slate-100 px-3 py-2">
                    <div class="truncate text-sm font-semibold text-slate-950">{{ $user?->name }}</div>
                    <div class="truncate text-xs text-slate-500">{{ $user?->email }}</div>
                </div>
                <a href="{{ route('profile.edit') }}" class="mt-1 flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-slate-100"><i data-lucide="user-round" class="size-4 text-slate-400"></i>Profile</a>
                <a href="{{ route('account.settings') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-slate-100"><i data-lucide="settings" class="size-4 text-slate-400"></i>Account settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50"><i data-lucide="log-out" class="size-4"></i>Logout</button>
                </form>
            </x-dropdown>
        </div>
    </div>
    <form x-cloak x-show="mobileSearchOpen" x-transition action="{{ route('search') }}" method="GET" class="border-t border-slate-100 px-4 py-3 lg:hidden">
        <label class="sr-only" for="mobile-global-search">Search</label>
        <div class="relative">
            <input id="mobile-global-search" name="q" value="{{ request('q') }}" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 pl-4 pr-11 text-sm outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100" placeholder="Search members, events, reports...">
            <button class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-md text-slate-500 hover:bg-slate-100" aria-label="Search">
                <i data-lucide="search" class="size-4"></i>
            </button>
        </div>
    </form>
</header>
