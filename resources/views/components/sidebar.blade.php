@php
    $branding = \App\Support\Branding::current();
    $user = auth()->user();
    $canAccessNavigationItem = fn (array $item): bool => (! ($item['super_admin'] ?? false) || $user?->isSuperAdministrator())
        && ($user?->isSuperAdministrator()
            || (! empty($item['permissions_any']) && $user?->hasAnyPermission($item['permissions_any']))
            || (empty($item['permissions_any']) && (empty($item['permission']) || $user?->hasPermission($item['permission']))));
    $items = collect(\App\Support\ModuleRegistry::visibleNavigation($user?->church))
        ->filter(fn (array $item): bool => $canAccessNavigationItem($item) || collect($item['children'] ?? [])->contains($canAccessNavigationItem))
        ->all();
    $sections = collect($items)->groupBy(fn (array $item): string => $item['section'] ?? 'Other');
    $configuredSidebarColor = (string) data_get($branding->settings, 'sidebar_middle_color', '#082851');
    $sidebarBackgroundColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $configuredSidebarColor) ? $configuredSidebarColor : '#082851';
    $colorfulSidebarIcons = (bool) data_get($branding->settings, 'sidebar_colorful_icons', false);
    $sidebarIconTones = [
        'dashboard' => 'text-violet-500',
        'members.index' => 'text-sky-500',
        'families.index' => 'text-rose-500',
        'programs.index' => 'text-indigo-500',
        'events.index' => 'text-amber-500',
        'calendar.index' => 'text-blue-500',
        'meetings.index' => 'text-cyan-500',
        'attendance.index' => 'text-emerald-500',
        'finance.index' => 'text-emerald-500',
        'sermons.index' => 'text-orange-500',
        'prayer-requests.index' => 'text-pink-500',
        'bible.index' => 'text-amber-500',
        'messages.index' => 'text-sky-500',
        'communications.index' => 'text-fuchsia-500',
        'volunteers.index' => 'text-teal-500',
        'ministries.index' => 'text-indigo-500',
        'campuses.index' => 'text-red-500',
        'assets.index' => 'text-cyan-500',
        'facilities.index' => 'text-slate-500',
        'bookstore.index' => 'text-yellow-500',
        'children-youth.index' => 'text-rose-500',
        'counselling.index' => 'text-purple-500',
        'leadership-reports.index' => 'text-blue-500',
        'feedback.index' => 'text-lime-500',
        'staff.index' => 'text-teal-500',
        'reports.index' => 'text-orange-500',
        'workflows.index' => 'text-violet-500',
        'users.index' => 'text-sky-500',
        'settings.index' => 'text-slate-500',
    ];
    $sidebarIconPalette = [
        'text-blue-500', 'text-emerald-500', 'text-amber-500',
        'text-rose-500', 'text-cyan-500', 'text-purple-500',
        'text-orange-500', 'text-teal-500', 'text-pink-500',
    ];
    $sidebarIconTone = function (?string $route) use ($sidebarIconTones, $sidebarIconPalette): string {
        if (isset($sidebarIconTones[$route])) {
            return $sidebarIconTones[$route];
        }

        return $sidebarIconPalette[((int) sprintf('%u', crc32((string) $route))) % count($sidebarIconPalette)];
    };
    $currentRoute = request()->route()?->getName();
    $sidebarNotificationCount = ($user?->unreadNotifications()->count() ?? 0) + ($user ? \App\Models\CommunicationDelivery::query()
        ->where('church_id', $user->church_id)
        ->where('channel', 'in_app')
        ->whereNull('read_at')
        ->when(! $user->isSuperAdministrator() && $user->campus_id, function ($query) use ($user): void {
            $query->where(function ($scope) use ($user): void {
                $scope->whereHas('member', fn ($memberQuery) => $memberQuery->where('campus_id', $user->campus_id))
                    ->orWhereHas('whatsappGroup', function ($groupQuery) use ($user): void {
                        $groupQuery->where('campus_id', $user->campus_id)
                            ->orWhereHas('ministry', fn ($ministryQuery) => $ministryQuery->where('campus_id', $user->campus_id));
                    });
            });
        })->count() : 0);
    $sidebarMessageCount = $user ? app(\App\Support\UnreadCounts::class)->messages($user) : 0;
    $routeMatches = function (?string $route, array $activeRoutes = []) use ($currentRoute): bool {
        if ($currentRoute === null) {
            return false;
        }

        if ($route !== null && $currentRoute === $route) {
            return true;
        }

        return collect($activeRoutes)->contains(function (string $candidate) use ($currentRoute): bool {
            return $currentRoute === $candidate || str_starts_with($currentRoute, $candidate.'.');
        });
    };
    $sidebarBackgroundUrl = $branding->sidebarBackground();
@endphp

<aside
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col overflow-hidden bg-sidebar text-white shadow-lg transition-transform duration-200 lg:translate-x-0"
    style="background-color: {{ $sidebarBackgroundColor }} !important; background-image: none !important;"
>
    <nav class="sidebar-nav-scroll relative z-10 min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-5">
        <div class="-mx-3 mb-4 flex items-center gap-3 border-b border-white/10 px-5 py-3">
            <div class="grid size-12 place-items-center overflow-hidden rounded-xl bg-transparent">
                @if ($branding->logo())
                    <img src="{{ $branding->logo() }}" alt="{{ $branding->churchName() }} logo" class="size-full object-contain">
                @else
                    <i data-lucide="cross" class="size-8"></i>
                @endif
            </div>
            <div class="min-w-0">
                <div class="sidebar-brand-text text-lg font-semibold leading-tight" style="color: var(--sidebar-text) !important;">{{ $branding->systemName() }}</div>
                <div class="text-xs leading-tight text-slate-300">{{ $branding->churchName() }}</div>
            </div>
        </div>
        @foreach ($sections as $sectionLabel => $sectionItems)
            <div class="px-3 pb-1 pt-5 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400 first:pt-1">{{ $sectionLabel }}</div>
            @foreach ($sectionItems as $item)
            @php
                $children = collect($item['children'] ?? [])->filter($canAccessNavigationItem);
                $isActive = $routeMatches($item['route'] ?? null, $item['active_routes'] ?? []) || $children->contains(fn (array $child): bool => $routeMatches($child['route'] ?? null, $child['active_routes'] ?? []));
                $itemIconTone = $sidebarIconTone($item['route'] ?? null);
            @endphp
            <div x-data="{ open: @js($isActive) }">
                @if ($children->isNotEmpty())
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        class="{{ $isActive ? 'sidebar-nav-active text-white' : 'sidebar-nav-item text-slate-200 hover:bg-white/10 hover:text-white' }} group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium outline-none transition focus-visible:ring-2 focus-visible:ring-white/70"
                        x-bind:aria-expanded="open.toString()"
                    >
                        @if ($colorfulSidebarIcons)
                            <span class="sidebar-color-icon grid size-4 shrink-0 place-items-center {{ $itemIconTone }}"><i data-lucide="{{ $item['icon'] }}" class="size-4"></i></span>
                        @else
                            <i data-lucide="{{ $item['icon'] }}" class="size-4 shrink-0"></i>
                        @endif
                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                        @isset($item['badge'])
                            <span class="rounded-full bg-violet-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $item['badge'] }}</span>
                        @endisset
                        <i data-lucide="chevron-up" class="size-3 transition-transform" x-bind:class="open ? '' : 'rotate-180'"></i>
                    </button>
                    <div x-show="open" class="mt-1 space-y-1 pl-8">
                        @foreach ($children as $child)
                            @php($childActive = $routeMatches($child['route'] ?? null, $child['active_routes'] ?? []))
                            @php($childIconTone = $sidebarIconTone($child['route'] ?? null))
                            @php($childBadge = match ($child['route'] ?? null) {
                                'communications.notifications' => $sidebarNotificationCount,
                                'messages.index' => $sidebarMessageCount,
                                default => null,
                            })
                            <a href="{{ route($child['route'], $child['route_parameters'] ?? []) }}" class="{{ $childActive ? 'sidebar-nav-active text-white' : 'sidebar-nav-item text-slate-300 hover:bg-white/10 hover:text-white' }} flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-medium" aria-current="{{ $childActive ? 'page' : 'false' }}">
                                @if ($colorfulSidebarIcons)
                                    <span class="sidebar-color-icon grid size-3.5 shrink-0 place-items-center {{ $childIconTone }}"><i data-lucide="{{ $child['icon'] }}" class="size-3.5"></i></span>
                                @else
                                    <i data-lucide="{{ $child['icon'] }}" class="size-3.5"></i>
                                @endif
                                <span class="min-w-0 flex-1 truncate">{{ $child['label'] }}</span>
                                @if ($childBadge !== null && $childBadge > 0)
                                    <span class="rounded-full bg-violet-500 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white" title="{{ number_format($childBadge) }} unread">{{ $childBadge > 99 ? '99+' : $childBadge }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <a
                        href="{{ route($item['route']) }}"
                        class="{{ $isActive ? 'sidebar-nav-active text-white' : 'sidebar-nav-item text-slate-200 hover:bg-white/10 hover:text-white' }} group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                        aria-current="{{ $routeMatches($item['route'] ?? null, $item['active_routes'] ?? []) ? 'page' : 'false' }}"
                    >
                        @if ($colorfulSidebarIcons)
                            <span class="sidebar-color-icon grid size-4 shrink-0 place-items-center {{ $itemIconTone }}"><i data-lucide="{{ $item['icon'] }}" class="size-4"></i></span>
                        @else
                            <i data-lucide="{{ $item['icon'] }}" class="size-4 shrink-0"></i>
                        @endif
                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                        @isset($item['badge'])
                            <span class="rounded-full bg-violet-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $item['badge'] }}</span>
                        @endisset
                    </a>
                @endif
            </div>
            @endforeach
        @endforeach
    </nav>

    <div class="relative z-0 mt-auto shrink-0 overflow-hidden px-4 pb-5 pt-4">
        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-0 h-52 bg-church-silhouette opacity-90" style="--sidebar-background-image: url('{{ $sidebarBackgroundUrl }}');" aria-hidden="true"></div>
        <div class="sidebar-profile-card relative z-10 flex items-center gap-3 rounded-xl p-3 backdrop-blur">
            @if (auth()->user()?->avatar_src)
                <img src="{{ auth()->user()->avatar_src }}" alt="{{ auth()->user()->name }}" class="size-11 rounded-full object-cover ring-2 ring-white/30">
            @else
                <div class="grid size-11 place-items-center rounded-full bg-gradient-to-br from-amber-200 to-amber-600 text-sm font-semibold text-slate-950">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium">{{ auth()->user()?->name }}</div>
                <div class="truncate text-xs text-slate-300">{{ auth()->user()?->title ?? 'Team Member' }}</div>
                <div class="mt-1 flex items-center gap-1 text-xs text-emerald-300"><span class="size-2 rounded-full bg-emerald-400"></span> Online</div>
            </div>
            <i data-lucide="chevron-up" class="size-4 text-slate-300"></i>
        </div>
    </div>
</aside>
