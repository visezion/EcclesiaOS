<x-app-layout title="Support Knowledge Base" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4">
        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4"><span class="grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="book-open" class="size-7"></i></span><div><p class="text-xs font-bold uppercase tracking-wide text-violet-600">Support Center</p><h1 class="text-2xl font-black text-slate-950">Knowledge Base</h1><p class="mt-0.5 text-sm text-slate-500">Official guides, troubleshooting steps, and practices created from resolved issues.</p></div></div>
            <a href="{{ route('support.tickets.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white"><i data-lucide="list-plus" class="size-4"></i>Still need help?</a>
        </header>

        <x-support-nav />

        <section class="dashboard-card">
            <h2 class="font-black text-slate-950">Search articles and guides</h2>
            <form method="GET" class="mt-3 grid gap-2 md:grid-cols-[minmax(240px,1fr)_200px_160px_auto]">
                <label class="relative"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i><input name="q" value="{{ request('q') }}" class="h-10 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm" placeholder="Search by keyword, topic, or article title"></label>
                <select name="category" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ data_get($category, 'slug') }}" @selected(request('category') === data_get($category, 'slug'))>{{ data_get($category, 'name') }}</option>@endforeach</select>
                <select name="sort" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="relevant">Most relevant</option><option value="updated" @selected(request('sort') === 'updated')>Recently updated</option><option value="helpful" @selected(request('sort') === 'helpful')>Most helpful</option></select>
                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-xs font-bold text-white"><i data-lucide="search" class="size-4"></i>Search</button>
            </form>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
                @forelse($categories as $category)
                    <a href="{{ route('support.knowledge', ['category' => data_get($category, 'slug')]) }}" class="rounded-xl border border-slate-200 p-3 text-center transition hover:border-violet-200 hover:bg-violet-50"><span class="mx-auto grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="book-open-check" class="size-4"></i></span><span class="mt-2 block text-xs font-bold text-slate-800">{{ data_get($category, 'name') }}</span><span class="mt-1 block text-[10px] text-slate-400">{{ number_format((int) data_get($category, 'articles_count', 0)) }} articles</span></a>
                @empty
                    @foreach(['Getting started', 'Members', 'Attendance', 'Giving', 'Integrations', 'Security', 'Troubleshooting'] as $category)
                        <div class="rounded-xl border border-slate-200 p-3 text-center"><span class="mx-auto grid size-9 place-items-center rounded-lg bg-slate-50 text-slate-400"><i data-lucide="book-open" class="size-4"></i></span><span class="mt-2 block text-xs font-bold text-slate-600">{{ $category }}</span></div>
                    @endforeach
                @endforelse
            </div>
        </section>

        @if($unavailable)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5"><div class="flex items-start gap-3"><i data-lucide="radio-tower" class="mt-0.5 size-5 text-amber-700"></i><div><h2 class="font-black text-amber-950">Knowledge service is not connected</h2><p class="mt-1 text-sm text-amber-800">{{ $unavailable }}</p>@if(auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('manage settings'))<a href="{{ route('central-support.index') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-black text-amber-900">Open connection settings <i data-lucide="arrow-right" class="size-3.5"></i></a>@endif</div></div></section>
        @else
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 p-4"><div><h2 class="font-black text-slate-950">All articles</h2><p class="mt-1 text-xs text-slate-500">{{ number_format((int) data_get($meta, 'total', $articles->count())) }} available guides</p></div></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($articles as $article)
                            @php($articleKey = data_get($article, 'id') ?? data_get($article, 'slug'))
                            <article class="grid gap-3 p-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center"><span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="book-open-check" class="size-5"></i></span><div class="min-w-0"><h3 class="text-sm font-black text-slate-900">{{ data_get($article, 'title') }}</h3><p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ data_get($article, 'excerpt') }}</p><div class="mt-2 flex flex-wrap gap-3 text-[10px] text-slate-400"><span>{{ data_get($article, 'category_name', 'Guide') }}</span><span>{{ data_get($article, 'read_time', 'Quick read') }}</span><span>Updated {{ data_get($article, 'updated_human', 'recently') }}</span></div></div><div class="flex items-center gap-2"><span class="rounded-full bg-emerald-50 px-2 py-1 text-[9px] font-bold text-emerald-700">{{ number_format((int) data_get($article, 'helpful_percent', 0)) }}% helpful</span>@if($articleKey)<a href="{{ route('support.knowledge.article', ['article' => $articleKey]) }}" class="inline-flex items-center gap-1 rounded-lg bg-violet-50 px-2.5 py-2 text-[10px] font-bold text-violet-700 hover:bg-violet-100">Read article <i data-lucide="arrow-right" class="size-3"></i></a>@endif</div></article>
                        @empty
                            <div class="p-12 text-center text-sm text-slate-500">No articles match this search.</div>
                        @endforelse
                    </div>
                </section>
                <aside class="space-y-4">
                    <section class="dashboard-card"><h2 class="flex items-center gap-2 font-black text-slate-950"><i data-lucide="sparkles" class="size-4 text-violet-600"></i>Popular searches</h2><div class="mt-3 flex flex-wrap gap-2">@foreach(['livestream','attendance','members','giving','reports','notifications','permissions','check-in'] as $term)<a href="{{ route('support.knowledge', ['q' => $term]) }}" class="rounded-full border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $term }}</a>@endforeach</div></section>
                    <section class="dashboard-card"><h2 class="font-black text-slate-950">From resolved tickets</h2><p class="mt-2 text-xs leading-5 text-slate-500">Central support can convert verified resolutions into sanitized guides that help every connected church.</p><a href="{{ route('support.community') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-bold text-violet-600">Browse community solutions <i data-lucide="arrow-right" class="size-3.5"></i></a></section>
                </aside>
            </div>
        @endif
    </div>
</x-app-layout>
