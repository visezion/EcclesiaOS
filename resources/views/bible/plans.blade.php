<x-app-layout title="Reading Plans" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    @php
        $readerParameters = function ($reading): array {
            if (! $reading) {
                return [];
            }

            $firstPassage = trim(explode(';', $reading->passages)[0]);
            preg_match('/^(.+?)\s+(\d+)/', $firstPassage, $parts);

            return array_filter([
                'book' => $parts[1] ?? null,
                'chapter' => isset($parts[2]) ? (int) $parts[2] : null,
            ]);
        };
        $todayReaderParameters = $readerParameters($todayReading);
        $readingDaysCompleted = $enrolledPlans->sum(fn ($plan): int => filled($plan->users->first()?->pivot?->completed_at)
            ? (int) $plan->duration_days
            : max(0, (int) ($plan->users->first()?->pivot?->current_day ?? 1) - 1));
        $currentStreak = (int) ($activePlans->max(fn ($plan): int => (int) ($plan->users->first()?->pivot?->current_streak ?? 0)) ?? 0);
        $categoryStyles = [
            ['icon' => 'git-branch', 'class' => 'bg-violet-50 text-violet-600'],
            ['icon' => 'refresh-cw', 'class' => 'bg-emerald-50 text-emerald-600'],
            ['icon' => 'book-open', 'class' => 'bg-sky-50 text-sky-600'],
            ['icon' => 'flame', 'class' => 'bg-orange-50 text-orange-600'],
            ['icon' => 'music', 'class' => 'bg-fuchsia-50 text-fuchsia-600'],
        ];
        $planArt = [
            'from-amber-100 via-emerald-100 to-sky-300',
            'from-orange-100 via-rose-100 to-violet-300',
            'from-sky-100 via-cyan-100 to-emerald-300',
            'from-violet-100 via-fuchsia-100 to-rose-300',
        ];
    @endphp

    <div class="w-full space-y-5">
        @include('bible._tabs')

        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700"><i data-lucide="book-open" class="size-6"></i></span>
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Reading Plans</h1>
                    <p class="mt-1 text-sm text-slate-500">Follow scheduled Bible readings and build a consistent daily habit.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('bible.index') }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-violet-200 bg-white px-5 text-sm font-bold text-violet-700 shadow-sm hover:bg-violet-50"><i data-lucide="book-open" class="size-4"></i>Read Bible</a>
                @if($canManagePlans)
                    <a href="{{ route('bible.admin.plans.index') }}" class="inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white shadow-sm shadow-violet-200 hover:bg-violet-700"><i data-lucide="settings-2" class="size-4"></i>Manage Plans</a>
                @endif
            </div>
        </header>

        @if(session('status'))
            <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700"><i data-lucide="circle-check" class="size-4"></i>{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('bible.plans') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="mb-2 text-xs font-black text-slate-700">Search plans</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.5fr_.85fr_.85fr_.85fr_auto_auto]">
                <label class="relative"><span class="sr-only">Search reading plans</span><i data-lucide="search" class="absolute left-3 top-3 size-4 text-slate-400"></i><input name="q" value="{{ $filters['q'] }}" class="h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm focus:border-violet-500 focus:ring-violet-200" placeholder="Search reading plans..."></label>
                <label><span class="sr-only">Category</span><select name="category" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-violet-500 focus:ring-violet-200"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category->category }}" @selected($filters['category'] === $category->category)>{{ $category->category }}</option>@endforeach</select></label>
                <label><span class="sr-only">Status</span><select name="status" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-violet-500 focus:ring-violet-200"><option value="">All Statuses</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="completed" @selected($filters['status'] === 'completed')>Completed</option></select></label>
                <label><span class="sr-only">Duration</span><select name="duration" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-violet-500 focus:ring-violet-200"><option value="">Any Duration</option><option value="30" @selected($filters['duration'] === '30')>30 days or less</option><option value="90" @selected($filters['duration'] === '90')>90 days or less</option><option value="365" @selected($filters['duration'] === '365')>Up to one year</option></select></label>
                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="filter" class="size-4"></i>Apply Filters</button>
                <a href="{{ route('bible.plans') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 text-sm font-bold text-slate-600 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i>Clear</a>
            </div>
        </form>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <main class="min-w-0 space-y-5">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div><h2 class="font-black text-slate-950">My Active Plans</h2><p class="mt-1 text-xs text-slate-500">Complete each scheduled day to advance your progress.</p></div>
                        <span class="shrink-0 text-xs font-bold text-violet-700">{{ $activePlans->count() }} active</span>
                    </div>
                    <div class="space-y-3">
                        @forelse($activePlans as $plan)
                            @php
                                $membership = $plan->users->first();
                                $day = (int) ($membership?->pivot?->current_day ?? 1);
                                $reading = $plan->days->firstWhere('day_number', $day);
                                $percent = min(100, (int) round((max(0, $day - 1) / max(1, $plan->duration_days)) * 100));
                                $parameters = $readerParameters($reading);
                                $art = $planArt[$loop->index % count($planArt)];
                            @endphp
                            <article class="grid gap-4 rounded-2xl border border-slate-200 p-4 transition hover:border-violet-200 hover:shadow-md md:grid-cols-[112px_minmax(0,1fr)] lg:grid-cols-[112px_minmax(0,1fr)_150px_155px] lg:items-center">
                                <div class="relative h-28 overflow-hidden rounded-xl bg-gradient-to-br {{ $art }} md:h-full md:min-h-28">
                                    <span class="absolute -right-4 -top-4 size-20 rounded-full bg-white/50"></span><span class="absolute -bottom-8 left-5 size-24 rotate-45 rounded-2xl bg-slate-700/15"></span><span class="absolute bottom-3 left-3 grid size-9 place-items-center rounded-lg bg-white/80 text-violet-700 shadow"><i data-lucide="book-open" class="size-5"></i></span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-black text-violet-700">Day {{ $day }}/{{ $plan->duration_days }}</p>
                                    <h3 class="mt-1 truncate text-lg font-black text-slate-950">{{ $plan->name }}</h3>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $plan->description }}</p>
                                    <div class="mt-3 flex items-start gap-2 text-xs"><i data-lucide="bookmark" class="mt-0.5 size-3.5 shrink-0 text-violet-600"></i><div><span class="font-bold text-violet-700">Today&rsquo;s reading</span><p class="mt-1 font-semibold text-slate-800">{{ $reading?->passages ?: 'Schedule unavailable' }}</p></div></div>
                                </div>
                                <div class="border-slate-100 lg:border-l lg:pl-5">
                                    <div class="flex items-center justify-between text-xs font-black text-slate-800"><span>Progress</span><span>{{ $percent }}%</span></div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-violet-600" style="width: {{ $percent }}%"></div></div>
                                    <div class="mt-3 space-y-2 text-xs text-slate-500"><p><i data-lucide="flame" class="mr-1.5 inline size-3.5 text-orange-500"></i>{{ (int) ($membership?->pivot?->current_streak ?? 0) }} day streak</p><p><i data-lucide="calendar-days" class="mr-1.5 inline size-3.5"></i>{{ max(0, $plan->duration_days - $day + 1) }} days left</p></div>
                                </div>
                                <div class="grid gap-2 border-slate-100 lg:border-l lg:pl-5">
                                    <a href="{{ route('bible.index', $parameters) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-violet-300 text-xs font-bold text-violet-700 hover:bg-violet-50"><i data-lucide="book-open" class="size-3.5"></i>Read</a>
                                    @if($reading)
                                        <form method="POST" action="{{ route('bible.plans.complete-day', $plan) }}">@csrf<input type="hidden" name="day" value="{{ $day }}"><button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="circle-check" class="size-3.5"></i>Complete Day</button></form>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 p-10 text-center text-sm text-slate-500"><span class="mx-auto mb-3 grid size-11 place-items-center rounded-xl bg-violet-100 text-violet-600"><i data-lucide="calendar-plus" class="size-5"></i></span>You have no active plan. Start one below to begin a daily reading journey.</div>
                        @endforelse
                    </div>
                </section>

                <section id="discover-plans" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-start justify-between gap-3"><div><h2 class="font-black text-slate-950">Discover Reading Plans</h2><p class="mt-1 text-xs text-slate-500">Every plan contains a complete day-by-day Bible schedule.</p></div><span class="shrink-0 text-xs font-bold text-violet-700">{{ $availablePlans->count() }} available</span></div>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @forelse($availablePlans as $plan)
                            @php($art = $planArt[$loop->index % count($planArt)])
                            <article class="relative grid min-h-44 grid-cols-[105px_minmax(0,1fr)] gap-4 overflow-hidden rounded-2xl border border-slate-200 p-4 transition hover:border-violet-200 hover:shadow-md">
                                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br {{ $art }}"><span class="absolute -right-5 -top-5 size-20 rounded-full bg-white/50"></span><span class="absolute bottom-3 left-3 grid size-9 place-items-center rounded-lg bg-white/85 text-violet-700"><i data-lucide="book-open-check" class="size-5"></i></span></div>
                                <div class="flex min-w-0 flex-col">
                                    @if($plan->is_recommended)<span class="mb-2 w-fit rounded-full bg-violet-100 px-2 py-1 text-[10px] font-black text-violet-700">Recommended</span>@endif
                                    <h3 class="truncate text-sm font-black text-slate-950">{{ $plan->name }}</h3>
                                    <p class="mt-1 text-xs text-slate-500"><i data-lucide="calendar-days" class="mr-1 inline size-3.5"></i>{{ $plan->duration_days }} scheduled days</p>
                                    <p class="mt-2 line-clamp-2 flex-1 text-xs leading-5 text-slate-500">{{ $plan->description }}</p>
                                    <p class="mt-2 truncate text-[11px] text-slate-600"><i data-lucide="bookmark" class="mr-1 inline size-3.5 text-violet-600"></i>Starts with {{ $plan->days->first()?->passages ?: 'Schedule pending' }}</p>
                                    <form method="POST" action="{{ route('bible.plans.start', $plan) }}" class="mt-3">@csrf<button class="inline-flex h-9 min-w-28 items-center justify-center gap-2 rounded-lg border border-violet-300 px-4 text-xs font-bold text-violet-700 hover:bg-violet-50">Start Plan<i data-lucide="arrow-right" class="size-3.5"></i></button></form>
                                </div>
                            </article>
                        @empty
                            <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 p-8 text-center text-sm text-slate-500 lg:col-span-2">No plans match these filters.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center justify-between"><h2 class="font-black text-slate-950">Completed Plans</h2><span class="text-xs font-bold {{ $completedPlans->isEmpty() ? 'text-slate-400' : 'text-emerald-700' }}">{{ $completedPlans->count() }} completed</span></div>
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        @forelse($completedPlans as $plan)
                            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 last:border-0 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-full bg-emerald-50 text-emerald-600"><i data-lucide="trophy" class="size-4"></i></span><div><h3 class="text-sm font-black text-slate-900">{{ $plan->name }}</h3><p class="text-xs text-slate-500">{{ $plan->duration_days }} readings completed</p></div></div><p class="text-xs text-slate-500">Completed {{ \Carbon\CarbonImmutable::parse($plan->users->first()->pivot->completed_at)->format('M d, Y') }}</p></div>
                        @empty
                            <div class="flex items-center justify-center gap-3 bg-slate-50/50 p-7 text-sm text-slate-500"><span class="grid size-10 place-items-center rounded-full bg-violet-100 text-violet-600"><i data-lucide="trophy" class="size-5"></i></span>Completed plans will appear here.</div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="space-y-4 xl:sticky xl:top-5">
                <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-amber-50 via-rose-50 to-violet-100 p-4 shadow-sm">
                    <span class="absolute -right-10 top-12 size-36 rounded-full bg-white/45"></span><span class="absolute -bottom-16 right-12 size-40 rotate-45 rounded-3xl bg-violet-400/15"></span>
                    <div class="relative flex items-center gap-2"><span class="grid size-9 place-items-center rounded-xl bg-violet-100 text-violet-700"><i data-lucide="calendar-days" class="size-5"></i></span><h2 class="font-black text-slate-950">Today&rsquo;s Reading</h2></div>
                    @if($todayPlan && $todayReading)
                        <div class="relative mt-4 max-w-[210px] rounded-xl border border-white/80 bg-white/90 p-4 shadow-sm"><p class="text-xs font-black text-slate-900">{{ $todayPlan->name }}</p><p class="mt-3 text-[11px] text-slate-500">Day {{ (int) ($todayPlan->users->first()?->pivot?->current_day ?? 1) }} Reading</p><h3 class="mt-1 text-sm font-black text-slate-950">{{ $todayReading->passages }}</h3><a href="{{ route('bible.index', $todayReaderParameters) }}" class="mt-4 inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="book-open" class="size-3.5"></i>Read Now</a></div>
                    @else
                        <div class="relative mt-4 rounded-xl border border-white/80 bg-white/85 p-4"><p class="text-sm leading-6 text-slate-600">Start a plan to receive a scheduled reading here each day.</p><a href="#discover-plans" class="mt-3 inline-flex h-9 w-full items-center justify-center rounded-lg border border-violet-200 text-xs font-bold text-violet-700">Choose a Plan</a></div>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2"><span class="grid size-9 place-items-center rounded-xl bg-rose-50 text-rose-500"><i data-lucide="flame" class="size-5"></i></span><h2 class="font-black text-slate-950">Reading Streak</h2></div>
                    <p class="mt-4 text-3xl font-black text-slate-950">{{ $currentStreak }} <span class="text-xs font-normal text-slate-500">days</span></p><p class="mt-1 text-xs leading-5 text-slate-500">Complete scheduled readings on consecutive days to grow your streak.</p>
                    <div class="mt-4 grid grid-cols-7 gap-2 text-center">@foreach(['M','T','W','T','F','S','S'] as $weekday)<div><span class="mx-auto grid size-5 place-items-center rounded-full border {{ $loop->iteration <= min(7, $currentStreak) ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 text-transparent' }}"><i data-lucide="check" class="size-3"></i></span><span class="mt-2 block text-[10px] font-bold text-slate-500">{{ $weekday }}</span></div>@endforeach</div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2"><span class="grid size-9 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="chart-column" class="size-5"></i></span><h2 class="font-black text-slate-950">Your Progress</h2></div>
                    <div class="mt-4 grid grid-cols-2 divide-x divide-slate-100"><div><p class="text-2xl font-black text-slate-950">{{ $activePlans->count() }}</p><p class="text-xs text-slate-500">Active plans</p></div><div class="pl-5"><p class="text-2xl font-black text-slate-950">{{ $completedPlans->count() }}</p><p class="text-xs text-slate-500">Completed</p></div></div>
                    <div class="mt-4"><div class="flex justify-between text-[11px] text-slate-500"><span>Reading days completed</span><b class="text-slate-800">{{ $readingDaysCompleted }}</b></div><div class="mt-2 h-2 rounded-full bg-slate-200"><div class="h-2 rounded-full bg-violet-600" style="width: {{ min(100, $readingDaysCompleted) }}%"></div></div></div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between"><h2 class="font-black text-slate-950">Plan Categories</h2><a href="{{ route('bible.plans') }}#discover-plans" class="text-xs font-bold text-violet-700">View all</a></div>
                    <div class="mt-3 space-y-2">@foreach($categories as $category)@php($style = $categoryStyles[$loop->index % count($categoryStyles)])<a href="{{ route('bible.plans', ['category' => $category->category]) }}" class="flex items-center gap-3 rounded-lg px-1 py-1.5 text-xs text-slate-600 hover:bg-slate-50"><span class="grid size-7 place-items-center rounded-lg {{ $style['class'] }}"><i data-lucide="{{ $style['icon'] }}" class="size-3.5"></i></span><span class="font-semibold">{{ $category->category }}</span><b class="ml-auto rounded-full bg-slate-100 px-2 py-1 text-[10px] text-slate-600">{{ $category->total }}</b></a>@endforeach</div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
