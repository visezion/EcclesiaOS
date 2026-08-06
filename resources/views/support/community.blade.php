<x-app-layout title="Community Solutions" :breadcrumbs="$breadcrumbs">
    <div x-data="{ askOpen: false }" class="space-y-5">
        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="messages-square" class="size-7"></i></span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-violet-600">EcclesiaOS Community</p>
                    <h1 class="text-2xl font-black text-slate-950">Community Solutions</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Learn from questions, fixes and accepted solutions shared across connected churches.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('support.tickets.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700"><i data-lucide="arrow-left" class="size-4"></i>My tickets</a>
                <button type="button" x-on:click="askOpen = ! askOpen" class="inline-flex h-10 items-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white"><i data-lucide="list-plus" class="size-4"></i>Ask the community</button>
            </div>
        </header>

        <x-support-nav />

        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

        <section x-show="askOpen || {{ $errors->any() ? 'true' : 'false' }}" x-cloak x-transition class="dashboard-card">
            <div class="flex items-start justify-between gap-3">
                <div><h2 class="font-black text-slate-950">Ask a community question</h2><p class="mt-1 text-xs text-slate-500">Do not include member information, passwords, API keys, private screenshots, or confidential church data.</p></div>
                <button type="button" x-on:click="askOpen = false" class="grid size-8 place-items-center rounded-lg bg-slate-100 text-slate-500"><i data-lucide="x" class="size-4"></i></button>
            </div>
            <form method="POST" action="{{ route('support.community.store') }}" class="mt-4 grid gap-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                    <label class="text-sm font-bold text-slate-700">Category<select name="category" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm">@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="text-sm font-bold text-slate-700">Question title<input name="title" required value="{{ old('title') }}" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" placeholder="Describe the problem clearly"></label>
                </div>
                <label class="text-sm font-bold text-slate-700">Details<textarea name="body" required rows="5" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm leading-6" placeholder="What happened, what have you tried, and what result do you need?">{{ old('body') }}</textarea></label>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-start gap-2 text-xs leading-5 text-slate-600"><input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-slate-300 text-violet-600"><span>I confirm this question is safe to share with other EcclesiaOS churches.</span></label>
                    <button class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 text-sm font-bold text-white"><i data-lucide="send" class="size-4"></i>Publish question</button>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Solved questions', data_get($meta, 'solved'), 'badge-check', 'bg-emerald-50 text-emerald-600'],
                ['Open questions', data_get($meta, 'open'), 'circle-help', 'bg-amber-50 text-amber-600'],
                ['Official answers', data_get($meta, 'official_answers'), 'shield-check', 'bg-violet-50 text-violet-600'],
                ['Helpful votes', data_get($meta, 'helpful'), 'thumbs-up', 'bg-blue-50 text-blue-600'],
            ] as [$label, $value, $icon, $tone])
                <div class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><div class="text-2xl font-black text-slate-950">{{ $value === null ? '—' : number_format((int) $value) }}</div><div class="text-xs font-semibold text-slate-500">{{ $label }}</div></div></div></div>
            @endforeach
        </section>

        <section class="dashboard-card">
            <form method="GET" class="grid gap-2 md:grid-cols-[minmax(220px,1fr)_190px_160px_auto]">
                <label class="relative"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i><input name="q" value="{{ request('q') }}" class="h-10 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm" placeholder="Search questions and solutions"></label>
                <select name="category" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">All categories</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select>
                <select name="status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">All statuses</option><option value="solved" @selected(request('status') === 'solved')>Solved</option><option value="open" @selected(request('status') === 'open')>Open</option></select>
                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-xs font-bold text-white"><i data-lucide="list-filter" class="size-4"></i>Search</button>
            </form>
        </section>

        @if($unavailable)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex items-start gap-3"><i data-lucide="radio-tower" class="mt-0.5 size-5 text-amber-700"></i><div><h2 class="font-black text-amber-950">Central community is not connected</h2><p class="mt-1 text-sm text-amber-800">{{ $unavailable }}</p>@if(auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('manage settings'))<a href="{{ route('central-support.index') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-black text-amber-900">Open connection settings <i data-lucide="arrow-right" class="size-3.5"></i></a>@endif</div></div>
            </section>
        @else
            <section class="grid gap-3">
                @forelse($questions as $question)
                    <article class="dashboard-card transition hover:border-violet-200">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl {{ data_get($question, 'status') === 'solved' ? 'bg-emerald-50 text-emerald-600' : 'bg-violet-50 text-violet-600' }}"><i data-lucide="{{ data_get($question, 'status') === 'solved' ? 'badge-check' : 'message-square-text' }}" class="size-5"></i></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{{ $categories[data_get($question, 'category')] ?? str(data_get($question, 'category', 'other'))->headline() }}</span>@if(data_get($question, 'status') === 'solved')<span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700">Accepted solution</span>@endif</div>
                                <h2 class="mt-2 text-sm font-black text-slate-950">{{ data_get($question, 'title') }}</h2>
                                <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ data_get($question, 'excerpt') }}</p>
                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-500"><span>{{ data_get($question, 'church_name', 'EcclesiaOS church') }}</span><span>{{ number_format((int) data_get($question, 'answers_count', 0)) }} answers</span><span>{{ number_format((int) data_get($question, 'helpful_count', 0)) }} found helpful</span></div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="dashboard-card py-14 text-center"><span class="mx-auto grid size-12 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="search" class="size-6"></i></span><h2 class="mt-3 font-black text-slate-950">No community questions found</h2><p class="mt-1 text-sm text-slate-500">Try another search or ask the community.</p></div>
                @endforelse
            </section>
        @endif
    </div>
</x-app-layout>
