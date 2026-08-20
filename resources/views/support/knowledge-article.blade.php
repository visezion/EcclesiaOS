<x-app-layout title="{{ $article['title'] ?? 'Knowledge Base Article' }}" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4">
        <x-support-nav />

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        @if($unavailable)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex items-start gap-3"><i data-lucide="radio-tower" class="mt-0.5 size-5 text-amber-700"></i><div><h1 class="font-black text-amber-950">Article unavailable</h1><p class="mt-1 text-sm text-amber-800">{{ $unavailable }}</p></div></div>
            </section>
        @elseif($article)
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 bg-violet-50/60 p-5 sm:p-7">
                    <a href="{{ route('support.knowledge') }}" class="inline-flex items-center gap-1 text-xs font-bold text-violet-700"><i data-lucide="arrow-left" class="size-3.5"></i>Back to Knowledge Base</a>
                    <p class="mt-5 text-xs font-bold uppercase tracking-wide text-violet-600">{{ data_get($article, 'category_name', 'Guide') }}</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ data_get($article, 'title') }}</h1>
                    <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-500"><span>{{ data_get($article, 'read_time', 'Quick read') }}</span><span>Updated {{ data_get($article, 'updated_human', 'recently') }}</span></div>
                </header>
                <div class="prose prose-slate max-w-none p-5 text-sm leading-7 sm:p-7">
                    {!! nl2br(e((string) data_get($article, 'content', data_get($article, 'body', data_get($article, 'description', data_get($article, 'excerpt', '')))))) !!}
                </div>
                <footer class="border-t border-slate-100 bg-slate-50 p-5 sm:px-7">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="text-sm font-black text-slate-900">Was this article helpful?</p><p class="mt-1 text-xs text-slate-500">Your feedback helps central support improve this guide.</p></div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('support.knowledge.article.helpful', ['article' => $articleId]) }}">@csrf<input type="hidden" name="helpful" value="1"><button class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700"><i data-lucide="thumbs-up" class="size-3.5"></i>Yes, helpful</button></form>
                            <form method="POST" action="{{ route('support.knowledge.article.helpful', ['article' => $articleId]) }}">@csrf<input type="hidden" name="helpful" value="0"><button class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100"><i data-lucide="thumbs-down" class="size-3.5"></i>Not really</button></form>
                        </div>
                    </div>
                </footer>
            </article>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">This article could not be found.</section>
        @endif
    </div>
</x-app-layout>
