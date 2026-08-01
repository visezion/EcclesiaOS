<x-app-layout title="Verse Comparison" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    <div
        x-data="{
            highlightDifferences: true,
            copied: false,
            copyComparison() {
                navigator.clipboard?.writeText({{ Js::from($comparisonCopyText) }});
                this.copied = true;
                setTimeout(() => this.copied = false, 1800);
            },
            shareComparison() {
                const text = {{ Js::from($comparisonCopyText) }};
                if (navigator.share) {
                    navigator.share({ title: 'Bible verse comparison', text });
                } else {
                    navigator.clipboard?.writeText(text);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1800);
                }
            }
        }"
        class="space-y-5"
    >
        @include('bible._tabs')

        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="scale" class="size-6"></i></span>
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Verse Comparison <i data-lucide="bookmark" class="ml-1 inline size-5 text-slate-500"></i></h1>
                    <p class="mt-1 text-sm text-slate-500">Compare Bible verses across multiple translations side by side.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('bible.search', ['q' => $book, 'tool' => 'commentaries', 'book' => $book, 'chapter' => $chapter]) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:border-emerald-200 hover:bg-emerald-50"><i data-lucide="notebook-tabs" class="size-4 text-emerald-600"></i>Commentaries</a>
                <a href="{{ route('bible.compare', ['book' => $book, 'chapter' => $chapter, 'verse' => $verse]) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:border-violet-200 hover:bg-violet-50"><i data-lucide="git-compare-arrows" class="size-4 text-violet-600"></i>Cross References</a>
                <button type="button" @click="copyComparison" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="bookmark" class="size-4"></i><span x-text="copied ? 'Copied' : 'Save Comparison'"></span></button>
            </div>
        </header>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_330px]">
            <main class="min-w-0 space-y-4">
                <form id="compare-reference-picker" data-options-url="{{ route('bible.reference-options') }}" method="GET" action="{{ route('bible.compare') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="text-xs font-bold text-slate-500">Book<select data-bible-book name="book" class="mt-1.5 h-11 w-full rounded-lg border border-slate-200 px-3 text-sm">@foreach($books as $item)<option value="{{ $item }}" @selected($book === $item)>{{ $item }}</option>@endforeach</select></label>
                        <label class="text-xs font-bold text-slate-500">Chapter<select data-bible-chapter name="chapter" class="mt-1.5 h-11 w-full rounded-lg border border-slate-200 px-3 text-sm">@foreach($chapters as $item)<option value="{{ $item }}" @selected($chapter === (int) $item)>Chapter {{ $item }}</option>@endforeach</select></label>
                        <label class="text-xs font-bold text-slate-500">Verse<select data-bible-verse name="verse" class="mt-1.5 h-11 w-full rounded-lg border border-slate-200 px-3 text-sm">@foreach($verseNumbers as $item)<option value="{{ $item }}" @selected($verse === (int) $item)>Verse {{ $item }}</option>@endforeach</select></label>
                    </div>
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="flex items-center gap-2 text-xs font-bold text-slate-500"><i data-lucide="languages" class="size-4 text-violet-600"></i>Translations ({{ $availableTranslations->count() }})</p>
                            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="scale" class="size-3.5"></i>Compare Selected</button>
                        </div>
                        <div class="mt-3 flex max-h-36 flex-wrap gap-2 overflow-y-auto pr-1">
                            @foreach($availableTranslations as $translation)
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 transition has-[:checked]:border-violet-300 has-[:checked]:bg-violet-50 has-[:checked]:text-violet-700">
                                    <input type="checkbox" name="versions[]" value="{{ $translation->id }}" @checked($selectedTranslationIds->contains($translation->id)) class="size-3.5 accent-violet-600">
                                    {{ $translation->abbreviation }}
                                    <span class="text-[10px] font-normal text-slate-400">{{ Str::limit($translation->name, 22) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </form>

                <section class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <button type="button" role="switch" :aria-checked="highlightDifferences.toString()" @click="highlightDifferences = ! highlightDifferences" class="inline-flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm font-bold text-slate-700">
                        <span class="grid size-8 place-items-center rounded-lg bg-amber-50 text-amber-600"><i data-lucide="highlighter" class="size-4"></i></span>
                        Highlight Differences
                        <span class="relative h-6 w-11 rounded-full transition" :class="highlightDifferences ? 'bg-violet-600' : 'bg-slate-300'"><span class="absolute left-1 top-1 size-4 rounded-full bg-white shadow transition" :class="highlightDifferences ? 'translate-x-5' : ''"></span></span>
                    </button>
                    <span class="hidden h-8 w-px bg-slate-200 sm:block"></span>
                    <button type="button" @click="copyComparison" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"><i data-lucide="copy" class="size-4 text-blue-600"></i><span x-text="copied ? 'Copied' : 'Copy'"></span></button>
                    <button type="button" @click="shareComparison" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"><i data-lucide="share-2" class="size-4 text-emerald-600"></i>Share</button>
                    <div x-show="highlightDifferences" x-transition class="ml-auto flex flex-wrap items-center gap-3 text-[11px] font-semibold text-slate-500">
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-slate-300"></span>Matching text</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-amber-400"></span>Different wording</span>
                    </div>
                </section>

                @php
                    $comparisonStyles = [
                        ['line' => 'bg-violet-500', 'soft' => 'bg-violet-100 text-violet-950 ring-violet-200', 'badge' => 'bg-violet-50 text-violet-700'],
                        ['line' => 'bg-blue-500', 'soft' => 'bg-blue-100 text-blue-950 ring-blue-200', 'badge' => 'bg-blue-50 text-blue-700'],
                        ['line' => 'bg-emerald-500', 'soft' => 'bg-emerald-100 text-emerald-950 ring-emerald-200', 'badge' => 'bg-emerald-50 text-emerald-700'],
                        ['line' => 'bg-orange-500', 'soft' => 'bg-orange-100 text-orange-950 ring-orange-200', 'badge' => 'bg-orange-50 text-orange-700'],
                        ['line' => 'bg-pink-500', 'soft' => 'bg-pink-100 text-pink-950 ring-pink-200', 'badge' => 'bg-pink-50 text-pink-700'],
                    ];
                @endphp
                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @forelse($translations as $translation)
                        @php($style = $comparisonStyles[$loop->index % count($comparisonStyles)])
                        @php($difference = $differences->get($translation->id))
                        @php($isBaseline = $baselineTranslation?->id === $translation->id)
                        <article class="relative flex min-h-[350px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                            <span class="absolute inset-x-0 top-0 h-1 {{ $style['line'] }}"></span>
                            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 h-11 w-1 rounded-full {{ $style['line'] }}"></span>
                                    <div><h2 class="font-black text-slate-950">{{ $translation->abbreviation }}</h2><p class="text-xs text-slate-500">{{ $translation->name }}</p></div>
                                </div>
                                @if($isBaseline)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-1 text-[10px] font-black text-violet-700"><i data-lucide="flag" class="size-3"></i>Baseline</span>
                                @else
                                    <span class="rounded-full px-2 py-1 text-[10px] font-black {{ $style['badge'] }}">{{ $difference['similarity'] }}% match</span>
                                @endif
                            </div>
                            <div class="mt-5 flex flex-1 gap-3">
                                <strong class="text-sm text-slate-700">{{ $verse }}</strong>
                                @if($verses->get($translation->id))
                                    <p class="text-base leading-7 text-slate-700">
                                        @foreach($difference['tokens'] as $token)
                                            <span @if($token['different']) :class="highlightDifferences ? '{{ $style['soft'] }} rounded px-0.5 ring-1' : ''" @endif class="transition-colors">{{ $token['text'] }}</span>{{ $loop->last ? '' : ' ' }}
                                        @endforeach
                                    </p>
                                @else
                                    <p class="text-sm leading-6 text-slate-400">This verse is not available in this translation yet.</p>
                                @endif
                            </div>
                            <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-3">
                                <a href="{{ route('bible.index', ['translation' => $translation->abbreviation, 'book' => $book, 'chapter' => $chapter]) }}" class="inline-flex items-center gap-1.5 font-bold text-violet-700"><i data-lucide="book-open" class="size-4"></i>{{ $book }} {{ $chapter }}:{{ $verse }}</a>
                                @unless($isBaseline)<span x-show="highlightDifferences" class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400"><i data-lucide="sparkles" class="size-3"></i>{{ $difference['different_count'] }} different</span>@endunless
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500 md:col-span-2 xl:col-span-4">Select at least one installed translation to compare.</div>
                    @endforelse
                </section>

                <section class="rounded-xl border border-violet-100 bg-gradient-to-r from-violet-50 to-blue-50 p-5">
                    <div class="flex items-start gap-3"><span class="grid size-9 place-items-center rounded-xl bg-white text-violet-600 shadow-sm"><i data-lucide="lightbulb" class="size-5"></i></span><div><h2 class="font-black text-slate-950">About Verse Comparisons</h2><p class="mt-1 text-sm leading-6 text-slate-600">Highlighted words show wording that differs from the baseline translation. Comparing translations can reveal useful nuances while preserving each translation&rsquo;s complete wording.</p></div></div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><h2 class="flex items-center gap-2 text-sm font-black text-slate-950"><i data-lucide="notebook-pen" class="size-4 text-emerald-600"></i>Comparison Notes</h2><span class="text-[11px] font-bold text-violet-700">Private</span></div><textarea maxlength="500" placeholder="Add your notes about this comparison..." class="mt-3 h-28 w-full resize-none rounded-lg border border-slate-200 p-3 text-sm text-slate-700"></textarea><p class="mt-2 text-right text-[10px] text-slate-400">Your notes are private.</p></section>
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><h2 class="flex items-center gap-2 text-sm font-black text-slate-950"><i data-lucide="route" class="size-4 text-blue-600"></i>Related Passages</h2><a href="{{ route('bible.search', ['q' => $book, 'book' => $book, 'chapter' => $chapter]) }}" class="text-[11px] font-bold text-violet-700">View all</a></div><div class="mt-3 space-y-2">@forelse($relatedVerses as $related)<a href="{{ route('bible.compare', ['book' => $book, 'chapter' => $chapter, 'verse' => $related->verse, 'versions' => $selectedTranslationIds->all()]) }}" class="flex items-start gap-2 rounded-lg p-2 hover:bg-violet-50"><i data-lucide="book-open" class="mt-0.5 size-4 shrink-0 text-violet-600"></i><span><strong class="block text-xs text-violet-700">{{ $book }} {{ $chapter }}:{{ $related->verse }}</strong><span class="text-xs text-slate-500">{{ Str::limit($related->text, 70) }}</span></span></a>@empty<p class="text-sm text-slate-500">No related passages available.</p>@endforelse</div></section>
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><h2 class="flex items-center gap-2 text-sm font-black text-slate-950"><i data-lucide="history" class="size-4 text-orange-500"></i>Recent Comparison</h2><span class="text-[11px] font-bold text-violet-700">{{ $book }} {{ $chapter }}:{{ $verse }}</span></div><div class="mt-3 rounded-lg bg-slate-50 p-3"><p class="text-xs font-black text-violet-700">{{ $book }} {{ $chapter }}:{{ $verse }}</p><p class="mt-1 text-xs text-slate-500">{{ $translations->pluck('abbreviation')->join(', ') }}</p></div></section>
            </aside>
        </div>
    </div>
    @include('bible._reference-picker-script', ['pickerId' => 'compare-reference-picker'])
</x-app-layout>
