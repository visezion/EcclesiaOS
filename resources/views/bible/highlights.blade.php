<x-app-layout title="Highlights" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    @php
        $palette = [
            'yellow' => ['Yellow', '#fbbf24', 'bg-amber-400', 'border-amber-400', 'bg-amber-50', 'text-amber-700'],
            'green' => ['Green', '#22c55e', 'bg-emerald-500', 'border-emerald-500', 'bg-emerald-50', 'text-emerald-700'],
            'purple' => ['Purple', '#8b5cf6', 'bg-violet-500', 'border-violet-500', 'bg-violet-50', 'text-violet-700'],
            'pink' => ['Pink', '#ec4899', 'bg-pink-500', 'border-pink-500', 'bg-pink-50', 'text-pink-700'],
            'blue' => ['Blue', '#3b82f6', 'bg-blue-500', 'border-blue-500', 'bg-blue-50', 'text-blue-700'],
        ];
        $donutStops = [];
        $donutStart = 0;
        foreach ($palette as $key => [, $hex]) {
            $donutEnd = $donutStart + ($totalHighlights > 0 ? ($colorCounts->get($key, 0) / $totalHighlights) * 100 : 0);
            $donutStops[] = $hex.' '.$donutStart.'% '.$donutEnd.'%';
            $donutStart = $donutEnd;
        }
        $donutBackground = $totalHighlights > 0 ? implode(', ', $donutStops) : '#e2e8f0 0% 100%';
    @endphp

    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, copied: null }" class="space-y-5">
        @include('bible._tabs')

        <header class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700">
                    <i data-lucide="bookmark" class="size-6"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-950">Highlights</h1>
                    <p class="mt-1 text-sm text-slate-500">Verses you have marked and highlighted for reflection and growth.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="open = true" class="inline-flex h-11 items-center gap-2 rounded-xl border border-violet-200 bg-white px-4 text-sm font-bold text-slate-800 transition hover:border-violet-400 hover:text-violet-700">
                    <i data-lucide="highlighter" class="size-5 text-violet-600"></i>Highlight New Verse
                </button>
                <a href="{{ route('bible.highlights.export') }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 transition hover:border-violet-300 hover:text-violet-700">
                    <i data-lucide="download" class="size-5 text-violet-600"></i>Export Highlights
                </a>
                <button type="button" @click="open = true; $nextTick(() => $refs.meaning?.focus())" title="Create a collection by assigning the same meaning or tag to verses" class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 transition hover:border-violet-300 hover:text-violet-700">
                    <i data-lucide="folder-plus" class="size-5 text-violet-600"></i>Create Collection
                </button>
                <a href="{{ route('bible.notes') }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 transition hover:border-violet-300 hover:text-violet-700">
                    <i data-lucide="refresh-cw" class="size-5 text-violet-600"></i>Sync with Notes
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-sm font-black text-slate-900">Highlights by Color</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-[220px_repeat(5,minmax(0,1fr))]">
                <div class="flex items-center justify-center rounded-2xl bg-slate-50 p-4">
                    <div class="relative grid size-32 place-items-center rounded-full" style="background: conic-gradient({{ $donutBackground }})">
                        <div class="grid size-20 place-items-center rounded-full bg-white text-center shadow-inner">
                            <div><p class="text-2xl font-black text-slate-950">{{ $totalHighlights }}</p><p class="text-xs font-semibold text-slate-500">Total</p></div>
                        </div>
                    </div>
                </div>
                @foreach ($palette as $key => [$name, $hex, $dotClass, $borderClass, $bgClass, $textClass])
                    <a href="{{ route('bible.highlights', ['color' => $key]) }}" class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-md">
                        <div class="flex items-center gap-2 text-sm font-bold text-slate-700"><span class="size-3 rounded-full {{ $dotClass }}"></span>{{ $name }}</div>
                        <p class="mt-7 text-3xl font-black text-slate-950">{{ $colorCounts->get($key, 0) }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-500">{{ $totalHighlights > 0 ? number_format(($colorCounts->get($key, 0) / $totalHighlights) * 100, 1) : '0.0' }}%</p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <form id="highlight-filters" method="GET" action="{{ route('bible.highlights') }}" class="grid gap-3 border-b border-slate-200 p-4 lg:grid-cols-[minmax(220px,1.2fr)_minmax(140px,.8fr)_minmax(170px,1fr)_minmax(150px,1fr)_minmax(150px,.9fr)_auto]">
                <label class="relative">
                    <span class="sr-only">Search highlights</span>
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-3 size-4 text-slate-400"></i>
                    <input name="q" value="{{ $filters['q'] }}" class="h-10 w-full rounded-xl border border-slate-200 pl-9 pr-3 text-sm focus:border-violet-400 focus:ring-violet-200" placeholder="Search highlights...">
                </label>
                <select name="color" onchange="this.form.submit()" aria-label="Filter by color" class="h-10 rounded-xl border border-slate-200 px-3 text-sm focus:border-violet-400 focus:ring-violet-200">
                    <option value="">All Colors</option>
                    @foreach ($palette as $key => [$name])<option value="{{ $key }}" @selected($filters['color'] === $key)>{{ $name }}</option>@endforeach
                </select>
                <select name="book" onchange="this.form.submit()" aria-label="Filter by book" class="h-10 rounded-xl border border-slate-200 px-3 text-sm focus:border-violet-400 focus:ring-violet-200">
                    <option value="">All Books</option>
                    @foreach ($books as $book)<option value="{{ $book }}" @selected($filters['book'] === $book)>{{ $book }}</option>@endforeach
                </select>
                <select name="tag" onchange="this.form.submit()" aria-label="Filter by tag" class="h-10 rounded-xl border border-slate-200 px-3 text-sm focus:border-violet-400 focus:ring-violet-200">
                    <option value="">All Tags</option>
                    @foreach ($tags as $tag)<option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>@endforeach
                </select>
                <select name="date" onchange="this.form.submit()" aria-label="Filter by date" class="h-10 rounded-xl border border-slate-200 px-3 text-sm focus:border-violet-400 focus:ring-violet-200">
                    <option value="">All Dates</option>
                    <option value="today" @selected($filters['date'] === 'today')>Today</option>
                    <option value="7" @selected($filters['date'] === '7')>Last 7 days</option>
                    <option value="30" @selected($filters['date'] === '30')>Last 30 days</option>
                    <option value="year" @selected($filters['date'] === 'year')>This year</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="grid size-10 place-items-center rounded-xl bg-violet-600 text-white shadow-sm hover:bg-violet-700" title="Apply filters"><i data-lucide="search" class="size-4"></i></button>
                    <a href="{{ route('bible.highlights') }}" class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-600 hover:border-violet-300 hover:text-violet-700">Clear Filters</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <div class="min-w-[1060px]">
                    <div class="grid grid-cols-[190px_minmax(300px,1fr)_150px_170px_170px_100px] gap-4 border-b border-slate-200 bg-slate-50/60 px-5 py-3 text-xs font-black text-slate-700">
                        <span>Verse Reference</span><span>Verse Snippet</span><span>Highlight Color</span><span>Meaning / Tag</span><span>Date Highlighted</span><span>Actions</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($highlights as $highlight)
                            @php
                                [$name, $hex, $dotClass, $borderClass, $bgClass, $textClass] = $palette[$highlight->color] ?? $palette['yellow'];
                                preg_match('/^(.+?)\s+(\d+):(\d+)/', $highlight->reference, $referenceParts);
                                $readerParameters = array_filter([
                                    'translation' => $highlight->translation?->abbreviation,
                                    'book' => $referenceParts[1] ?? null,
                                    'chapter' => isset($referenceParts[2]) ? (int) $referenceParts[2] : null,
                                    'verse' => isset($referenceParts[3]) ? (int) $referenceParts[3] : null,
                                ]);
                            @endphp
                            <article class="relative grid grid-cols-[190px_minmax(300px,1fr)_150px_170px_170px_100px] items-center gap-4 px-5 py-4 transition hover:bg-slate-50/80">
                                <span class="absolute inset-y-3 left-3 w-0.5 rounded-full {{ $borderClass }} border-l-2"></span>
                                <div class="pl-3"><a href="{{ route('bible.index', $readerParameters) }}" class="font-black text-violet-700 hover:text-violet-900">{{ $highlight->reference }}</a><p class="mt-1 text-xs font-semibold text-slate-500">{{ $highlight->translation?->abbreviation }}</p></div>
                                <p class="line-clamp-2 text-sm leading-6 text-slate-700">{{ $highlight->snippet }}</p>
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-700"><span class="size-3 rounded-full {{ $dotClass }}"></span>{{ $name }}</span>
                                <span class="inline-flex w-fit max-w-full rounded-lg {{ $bgClass }} px-3 py-1.5 text-xs font-bold {{ $textClass }}">{{ $highlight->meaning ?: ($highlight->tags[0] ?? 'Reflection') }}</span>
                                <time class="text-xs font-medium text-slate-500">{{ $highlight->created_at->format('M d, Y') }}<span class="mt-0.5 block">{{ $highlight->created_at->format('g:i A') }}</span></time>
                                <div class="flex items-center gap-3 text-slate-500">
                                    <button type="button" @click="navigator.clipboard?.writeText(@js($highlight->snippet)); copied = {{ $highlight->id }}; setTimeout(() => copied = null, 1500)" class="hover:text-violet-700" :title="copied === {{ $highlight->id }} ? 'Copied' : 'Copy verse'"><i x-show="copied !== {{ $highlight->id }}" data-lucide="copy" class="size-4"></i><i x-cloak x-show="copied === {{ $highlight->id }}" data-lucide="check" class="size-4 text-emerald-600"></i></button>
                                    <a href="{{ route('bible.notes', ['q' => $highlight->reference]) }}" title="Find related notes" class="hover:text-violet-700"><i data-lucide="notebook-pen" class="size-4"></i></a>
                                    <a href="{{ route('bible.index', $readerParameters) }}" title="Open in reader" class="hover:text-violet-700"><i data-lucide="ellipsis-vertical" class="size-4"></i></a>
                                </div>
                            </article>
                        @empty
                            <div class="grid place-items-center px-6 py-16 text-center">
                                <span class="grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="highlighter" class="size-7"></i></span>
                                <h2 class="mt-4 font-black text-slate-900">No highlights found</h2>
                                <p class="mt-1 text-sm text-slate-500">Highlight a verse in the Bible reader or adjust your filters.</p>
                                <button type="button" @click="open = true" class="mt-4 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white">Highlight a verse</button>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($highlights->hasPages())
                <div class="border-t border-slate-100 p-4">{{ $highlights->links() }}</div>
            @endif
        </section>

        <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-950/45 p-4" @keydown.escape.window="open = false" @click.self="open = false">
            <form method="POST" action="{{ route('bible.highlights.store') }}" class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
                @csrf
                <div class="flex items-center justify-between"><div><h2 class="text-xl font-black text-slate-950">Highlight New Verse</h2><p class="mt-1 text-sm text-slate-500">Save a verse with a color, meaning, and optional collection tags.</p></div><button type="button" @click="open = false" class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100"><i data-lucide="x" class="size-5"></i></button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-bold text-slate-700">Translation<select name="translation_id" required class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3 focus:border-violet-400 focus:ring-violet-200">@foreach ($translations as $translation)<option value="{{ $translation->id }}">{{ $translation->abbreviation }} &mdash; {{ $translation->name }}</option>@endforeach</select></label>
                    <label class="text-sm font-bold text-slate-700">Reference<input name="reference" value="{{ old('reference') }}" required placeholder="Philippians 4:13" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3 focus:border-violet-400 focus:ring-violet-200"></label>
                    <label class="text-sm font-bold text-slate-700 sm:col-span-2">Verse text<textarea name="snippet" required rows="4" class="mt-1.5 w-full rounded-xl border-slate-200 p-3 focus:border-violet-400 focus:ring-violet-200" placeholder="Enter the verse text...">{{ old('snippet') }}</textarea></label>
                    <label class="text-sm font-bold text-slate-700">Color<select name="color" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3 focus:border-violet-400 focus:ring-violet-200">@foreach ($palette as $key => [$name])<option value="{{ $key }}" @selected(old('color') === $key)>{{ $name }}</option>@endforeach</select></label>
                    <label class="text-sm font-bold text-slate-700">Meaning<input x-ref="meaning" name="meaning" value="{{ old('meaning') }}" placeholder="Faith, Peace, Strength..." class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3 focus:border-violet-400 focus:ring-violet-200"></label>
                    <label class="text-sm font-bold text-slate-700 sm:col-span-2">Collection tags <span class="font-normal text-slate-400">(comma separated)</span><input name="tags" value="{{ old('tags') }}" placeholder="Encouragement, Prayer" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3 focus:border-violet-400 focus:ring-violet-200"></label>
                </div>
                @if ($errors->any())<ul class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="open = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700">Cancel</button><button class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-violet-700">Save Highlight</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
