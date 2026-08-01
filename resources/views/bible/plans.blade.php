<x-app-layout title="Reading Plans" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    @php
        $readerParameters = function ($reading): array {
            if (! $reading) return [];
            $firstPassage = trim(explode(';', $reading->passages)[0]);
            preg_match('/^(.+?)\s+(\d+)/', $firstPassage, $parts);
            return array_filter(['book' => $parts[1] ?? null, 'chapter' => isset($parts[2]) ? (int) $parts[2] : null]);
        };
        $todayReaderParameters = $readerParameters($todayReading);
    @endphp
    <div class="space-y-5">
        @include('bible._tabs')

        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700"><i data-lucide="calendar-days" class="size-6"></i></span><div><h1 class="text-2xl font-black text-slate-950">Reading Plans</h1><p class="mt-1 text-sm text-slate-500">Follow scheduled Bible readings and build a consistent daily habit.</p></div></div>
            <div class="flex flex-wrap gap-2"><a href="{{ route('bible.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700"><i data-lucide="book-open" class="size-4"></i>Read Bible</a>@if($canManagePlans)<a href="{{ route('bible.admin.plans.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white"><i data-lucide="settings-2" class="size-4"></i>Manage Plans</a>@endif</div>
        </header>

        @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif

        <form method="GET" action="{{ route('bible.plans') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-5">
            <label class="relative"><span class="sr-only">Search plans</span><i data-lucide="search" class="absolute left-3 top-3 size-4 text-slate-400"></i><input name="q" value="{{ $filters['q'] }}" class="h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm focus:border-violet-500 focus:ring-violet-200" placeholder="Search reading plans..."></label>
            <select name="category" class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-violet-500 focus:ring-violet-200"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category->category }}" @selected($filters['category'] === $category->category)>{{ $category->category }}</option>@endforeach</select>
            <select name="status" class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-violet-500 focus:ring-violet-200"><option value="">All Statuses</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="completed" @selected($filters['status'] === 'completed')>Completed</option></select>
            <select name="duration" class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-violet-500 focus:ring-violet-200"><option value="">Any Duration</option><option value="30" @selected($filters['duration'] === '30')>30 days or less</option><option value="90" @selected($filters['duration'] === '90')>90 days or less</option><option value="365" @selected($filters['duration'] === '365')>Up to one year</option></select>
            <div class="flex gap-2"><button class="flex-1 rounded-xl bg-violet-600 px-4 text-sm font-bold text-white">Apply Filters</button><a href="{{ route('bible.plans') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-500">Clear</a></div>
        </form>

        <div class="grid gap-5 xl:grid-cols-4">
            <main class="space-y-6 xl:col-span-3">
                <section>
                    <div class="mb-3 flex items-center justify-between"><div><h2 class="font-black text-slate-950">My Active Plans</h2><p class="text-xs text-slate-500">Complete each scheduled day to advance your progress.</p></div><span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">{{ $activePlans->count() }} active</span></div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        @forelse($activePlans as $plan)
                            @php
                                $membership = $plan->users->first();
                                $day = (int) ($membership?->pivot?->current_day ?? 1);
                                $reading = $plan->days->firstWhere('day_number', $day);
                                $percent = min(100, (int) round((max(0, $day - 1) / max(1, $plan->duration_days)) * 100));
                                $parameters = $readerParameters($reading);
                            @endphp
                            <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between"><span class="grid size-11 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="book-open-check" class="size-5"></i></span><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600">Day {{ $day }}/{{ $plan->duration_days }}</span></div>
                                <h3 class="mt-3 font-black text-slate-950">{{ $plan->name }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ $plan->description }}</p>
                                <div class="mt-4 rounded-xl bg-violet-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-violet-600">Today&rsquo;s reading</p><p class="mt-1 text-sm font-black text-slate-900">{{ $reading?->title ?: 'Schedule unavailable' }}</p><p class="mt-1 text-xs text-slate-600">{{ $reading?->passages }}</p>@if($reading?->reflection)<p class="mt-2 border-t border-violet-100 pt-2 text-xs italic text-slate-500">{{ $reading->reflection }}</p>@endif</div>
                                <div class="mt-4 flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-violet-600" style="width: {{ $percent }}%"></div></div><span class="text-xs font-black text-slate-500">{{ $percent }}%</span></div>
                                <div class="mt-3 flex items-center justify-between text-xs text-slate-500"><span><i data-lucide="flame" class="mr-1 inline size-3.5 text-orange-500"></i>{{ (int) ($membership?->pivot?->current_streak ?? 0) }} day streak</span><span>{{ max(0, $plan->duration_days - $day + 1) }} days left</span></div>
                                <div class="mt-auto grid grid-cols-2 gap-2 pt-4"><a href="{{ route('bible.index', $parameters) }}" class="inline-flex items-center justify-center gap-1 rounded-lg border border-violet-200 px-3 py-2 text-xs font-bold text-violet-700"><i data-lucide="book-open" class="size-3.5"></i>Read</a>@if($reading)<form method="POST" action="{{ route('bible.plans.complete-day', $plan) }}">@csrf<input type="hidden" name="day" value="{{ $day }}"><button class="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white"><i data-lucide="check" class="size-3.5"></i>Complete Day</button></form>@endif</div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 lg:col-span-3"><i data-lucide="calendar-plus" class="mx-auto mb-3 size-8 text-violet-500"></i>You have no active plan. Start one below to begin a daily reading journey.</div>
                        @endforelse
                    </div>
                </section>

                <section id="discover-plans">
                    <div class="mb-3 flex items-center justify-between"><div><h2 class="font-black text-slate-950">Discover Reading Plans</h2><p class="text-xs text-slate-500">Every plan contains a complete day-by-day Bible schedule.</p></div><span class="text-xs font-bold text-violet-700">{{ $availablePlans->count() }} available</span></div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @forelse($availablePlans as $plan)
                            <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><span class="grid size-10 place-items-center rounded-xl {{ $plan->is_recommended ? 'bg-amber-50 text-amber-600' : 'bg-sky-50 text-sky-600' }}"><i data-lucide="{{ $plan->is_recommended ? 'sparkles' : 'calendar-days' }}" class="size-5"></i></span>@if($plan->is_recommended)<span class="text-[10px] font-black uppercase text-amber-600">Recommended</span>@endif</div><h3 class="mt-3 text-sm font-black text-slate-950">{{ $plan->name }}</h3><p class="mt-1 text-xs font-semibold text-violet-700">{{ $plan->duration_days }} scheduled days</p><p class="mt-3 flex-1 text-xs leading-5 text-slate-500">{{ $plan->description }}</p><p class="mt-3 text-[11px] text-slate-500"><i data-lucide="book-open" class="mr-1 inline size-3.5"></i>Starts with {{ $plan->days->first()?->passages }}</p><form method="POST" action="{{ route('bible.plans.start', $plan) }}" class="mt-4">@csrf<button class="w-full rounded-lg border border-violet-200 py-2 text-xs font-bold text-violet-700 hover:bg-violet-50">Start Plan <i data-lucide="arrow-right" class="ml-1 inline size-3.5"></i></button></form></article>
                        @empty<p class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-4">No plans match these filters.</p>@endforelse
                    </div>
                </section>

                <section>
                    <div class="mb-3 flex items-center justify-between"><h2 class="font-black text-slate-950">Completed Plans</h2><span class="text-xs font-bold text-emerald-700">{{ $completedPlans->count() }} completed</span></div>
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        @forelse($completedPlans as $plan)<div class="flex flex-col gap-3 border-b border-slate-100 p-4 last:border-0 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-full bg-emerald-50 text-emerald-600"><i data-lucide="circle-check" class="size-4"></i></span><div><h3 class="text-sm font-black text-slate-900">{{ $plan->name }}</h3><p class="text-xs text-slate-500">{{ $plan->duration_days }} readings completed</p></div></div><div class="text-xs text-slate-500">Completed {{ CarbonCarbon::parse($plan->users->first()->pivot->completed_at)->format('M d, Y') }}</div></div>@empty<div class="p-8 text-center text-sm text-slate-500">Completed plans will appear here.</div>@endforelse
                    </div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2"><span class="grid size-9 place-items-center rounded-xl bg-amber-50 text-amber-500"><i data-lucide="sun" class="size-5"></i></span><h2 class="font-black text-slate-950">Today&rsquo;s Reading</h2></div>@if($todayPlan && $todayReading)<p class="mt-4 text-xs font-bold text-violet-700">{{ $todayPlan->name }}</p><h3 class="mt-1 font-black text-slate-950">{{ $todayReading->title }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $todayReading->passages }}</p>@if($todayReading->reflection)<p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs italic leading-5 text-amber-800">{{ $todayReading->reflection }}</p>@endif<a href="{{ route('bible.index', $todayReaderParameters) }}" class="mt-4 block rounded-lg bg-violet-600 py-2.5 text-center text-xs font-bold text-white"><i data-lucide="book-open" class="mr-1 inline size-3.5"></i>Read Now</a>@else<p class="mt-4 text-sm leading-6 text-slate-500">Start a plan to receive a real scheduled reading here each day.</p><a href="#discover-plans" class="mt-4 block rounded-lg border border-violet-200 py-2.5 text-center text-xs font-bold text-violet-700">Choose a Plan</a>@endif</section>
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2"><span class="grid size-9 place-items-center rounded-xl bg-rose-50 text-rose-500"><i data-lucide="flame" class="size-5"></i></span><h2 class="font-black text-slate-950">Reading Streak</h2></div><p class="mt-4 text-center text-3xl font-black text-slate-950">{{ (int) ($todayPlan?->users->first()?->pivot?->current_streak ?? 0) }} <span class="text-xs font-normal text-slate-500">days</span></p><p class="mt-2 text-center text-xs text-slate-500">Complete scheduled readings on consecutive days to grow your streak.</p></section>
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><h2 class="font-black text-slate-950">Your Progress</h2><i data-lucide="chart-column" class="size-5 text-violet-600"></i></div><div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-xl bg-violet-50 p-3"><p class="text-2xl font-black text-violet-700">{{ $activePlans->count() }}</p><p class="text-xs text-slate-500">Active plans</p></div><div class="rounded-xl bg-emerald-50 p-3"><p class="text-2xl font-black text-emerald-700">{{ $completedPlans->count() }}</p><p class="text-xs text-slate-500">Completed</p></div></div><div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500"><span>Reading days completed</span><b class="text-slate-900">{{ $enrolledPlans->sum(fn ($plan) => filled($plan->users->first()?->pivot?->completed_at) ? $plan->duration_days : max(0, (int) ($plan->users->first()?->pivot?->current_day ?? 1) - 1)) }}</b></div></section>
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><h2 class="font-black text-slate-950">Plan Categories</h2><div class="mt-3 space-y-3">@foreach($categories as $category)<a href="{{ route('bible.plans', ['category' => $category->category]) }}" class="flex items-center justify-between text-xs text-slate-600"><span class="flex items-center gap-2"><i data-lucide="tag" class="size-3.5 text-violet-500"></i>{{ $category->category }}</span><b>{{ $category->total }}</b></a>@endforeach</div></section>
            </aside>
        </div>
    </div>
</x-app-layout>
