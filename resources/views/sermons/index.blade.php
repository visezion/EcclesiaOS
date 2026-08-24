@php
    $publishedCount = $sermons->where('status', 'published')->count();
    $draftCount = $sermons->where('status', 'draft')->count();
    $youtubeCount = $sermons->whereNotNull('youtube_video_id')->count();
    $liveCount = $sermons->whereIn('youtube_live_status', ['upcoming', 'live'])->count();
    $sermonAsset = fn ($path) => str_starts_with((string) $path, 'http') ? $path : asset('storage/'.ltrim((string) $path, '/'));
@endphp

<x-app-layout title="Sermons & Media" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto w-full max-w-[1680px] space-y-4">
        <section class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-r from-white via-white to-violet-50/80 p-5 shadow-sm sm:p-6">
            <div class="pointer-events-none absolute -right-16 -top-24 size-64 rounded-full bg-violet-100/70"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-600"><i data-lucide="radio" class="size-6"></i></div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.16em] text-violet-600">Website content</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Sermons & Media</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Organize sermons, recordings, and live broadcasts for your church website. YouTube content and uploaded media stay together in one library.</p>
                    </div>
                </div>
                <div class="relative flex shrink-0 flex-wrap gap-2">
                    <a href="{{ route('website-studio.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:border-violet-200 hover:text-violet-700"><i data-lucide="panels-top-left" class="size-4 text-violet-600"></i>Website Studio</a>
                    <a href="{{ route('sermons.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 transition hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Add sermon</a>
                </div>
            </div>
            <div class="relative mt-5 flex flex-col gap-3 border-t border-violet-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-red-50 text-red-600"><i data-lucide="youtube" class="size-4"></i></span><div><p class="text-xs font-bold text-slate-800">YouTube channel sync</p><p class="text-xs text-slate-500">@if ($youtubeConnection){{ $youtubeConnection->channel_title ?: 'Connected channel' }} · {{ $youtubeConnection->last_synced_at ? 'Synced '.$youtubeConnection->last_synced_at->diffForHumans() : 'Ready for first sync' }}@else Connect your channel to import uploads and live broadcasts automatically.@endif</p></div>@if ($youtubeConnection)<span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-700">Connected</span>@endif</div>
                <div class="flex flex-wrap gap-2">
                    @if ($youtubeConnection)
                        <form method="POST" action="{{ route('sermons.youtube.sync') }}">@csrf<button class="inline-flex items-center gap-2 rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-800"><i data-lucide="refresh-cw" class="size-3.5"></i>Sync now</button></form>
                        <form method="POST" action="{{ route('sermons.youtube.disconnect') }}" onsubmit="return confirm('Disconnect YouTube? Imported sermons will remain in your library.')">@csrf @method('DELETE')<button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-rose-200 hover:text-rose-600"><i data-lucide="unlink" class="size-3.5"></i>Disconnect</button></form>
                    @elseif ($youtubeCredentials)
                        <a href="{{ route('sermons.youtube.connect') }}" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-700"><i data-lucide="link" class="size-3.5"></i>Connect YouTube</a>
                    @else
                        <a href="{{ route('youtube-integration.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-violet-200 hover:text-violet-700"><i data-lucide="settings-2" class="size-3.5"></i>Configure integration</a>
                    @endif
                </div>
            </div>
        </section>

        @if (session('status'))<div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"><i data-lucide="circle-check" class="size-4"></i>{{ session('status') }}</div>@endif
        @if (session('error'))<div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"><i data-lucide="circle-alert" class="size-4"></i>{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"><i data-lucide="circle-alert" class="size-4"></i>{{ $errors->first() }}</div>@endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="dashboard-card flex items-center gap-3 p-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><i data-lucide="library-big" class="size-5"></i></span><div><p class="text-2xl font-black leading-none text-slate-950">{{ $sermons->count() }}</p><p class="mt-1 text-xs text-slate-500">Total sermons</p></div></div>
            <div class="dashboard-card flex items-center gap-3 p-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="circle-check" class="size-5"></i></span><div><p class="text-2xl font-black leading-none text-emerald-600">{{ $publishedCount }}</p><p class="mt-1 text-xs text-slate-500">Published</p></div></div>
            <div class="dashboard-card flex items-center gap-3 p-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600"><i data-lucide="file-pen-line" class="size-5"></i></span><div><p class="text-2xl font-black leading-none text-amber-600">{{ $draftCount }}</p><p class="mt-1 text-xs text-slate-500">Drafts</p></div></div>
            <div class="dashboard-card flex items-center gap-3 p-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="radio-tower" class="size-5"></i></span><div><p class="text-2xl font-black leading-none text-violet-600">{{ $liveCount }}</p><p class="mt-1 text-xs text-slate-500">Live / upcoming · {{ $youtubeCount }} YouTube</p></div></div>
        </div>

        <div x-data="{ query: '', status: 'all', media: 'all', view: 'list' }" class="space-y-4">
        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
                <label class="relative min-w-0 flex-1 lg:max-w-md"><i data-lucide="search" class="pointer-events-none absolute left-3 top-3 size-4 text-slate-400"></i><input x-model="query" type="search" placeholder="Title, speaker, or scripture..." class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-violet-400 focus:ring-violet-200"></label>
                <div class="flex flex-col gap-2 sm:flex-row"><select x-model="status" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700"><option value="all">All statuses</option><option value="published">Published</option><option value="draft">Drafts</option></select><select x-model="media" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700"><option value="all">All media</option><option value="youtube">YouTube</option><option value="video">Uploaded video</option><option value="audio">Audio</option></select><div class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 p-1"><button type="button" x-on:click="view = 'list'" x-bind:class="view === 'list' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-400'" class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-bold transition"><i data-lucide="list" class="size-3.5"></i>List</button><button type="button" x-on:click="view = 'gallery'" x-bind:class="view === 'gallery' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-400'" class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-bold transition"><i data-lucide="layout-grid" class="size-3.5"></i>Gallery</button></div><a href="{{ route('sermons.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-3 text-sm font-bold text-white transition hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>New sermon</a></div>
            </div>
        </section>

        <section class="dashboard-card overflow-hidden p-0">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-base font-bold text-slate-950">Sermon library</h2><p class="mt-1 text-xs text-slate-500">{{ $sermons->count() }} visible {{ Str::plural('message', $sermons->count()) }} · Manage the content shown on your public website.</p></div><span class="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-slate-400"><i data-lucide="shield-check" class="size-3.5"></i>Church media records</span></div>
            <div x-cloak x-bind:class="view === 'list' ? 'block' : 'hidden'" role="table" aria-label="Sermon list" class="min-w-[900px]">
                <div x-cloak x-bind:style="view === 'list' ? 'display:grid;grid-template-columns:minmax(300px,2fr) minmax(160px,1fr) minmax(150px,1fr) 150px;' : 'display:none;'" role="row" class="gap-4 border-b border-dashed border-slate-200 bg-slate-50/70 px-5 py-3 text-[10px] font-black uppercase tracking-[.12em] text-slate-400"><div role="columnheader">Message</div><div role="columnheader">Speaker & date</div><div role="columnheader">Media</div><div role="columnheader" class="text-right">Actions</div></div>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($sermons as $sermon)
                    @php
                        $mediaType = $sermon->youtube_video_id ? 'youtube' : ($sermon->video_url ? 'video' : ($sermon->audio_url ? 'audio' : 'none'));
                        $searchText = Str::lower($sermon->title.' '.($sermon->speaker ?? '').' '.($sermon->summary ?? '').' '.($sermon->scripture ?? ''));
                    @endphp
                    <div role="row" x-data="{ searchText: @js($searchText), itemStatus: @js($sermon->status), itemMedia: @js($mediaType) }" x-cloak x-bind:style="view === 'list' && (query === '' || searchText.includes(query.toLowerCase())) && (status === 'all' || itemStatus === status) && (media === 'all' || itemMedia === media) ? 'display:grid;grid-template-columns:minmax(300px,2fr) minmax(160px,1fr) minmax(150px,1fr) 150px;' : 'display:none;'" class="gap-4 border-b border-slate-100 px-5 py-4 transition last:border-b-0 hover:bg-violet-50/30">
                        <div role="cell" class="flex min-w-0 items-center gap-3">@if ($sermon->thumbnail_url)<img src="{{ $sermonAsset($sermon->thumbnail_url) }}" alt="{{ $sermon->title }}" class="size-12 shrink-0 rounded-lg object-cover ring-1 ring-slate-200">@else<span class="grid size-12 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-violet-600 to-slate-950 text-white"><i data-lucide="play" class="size-5 fill-current"></i></span>@endif<div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="truncate text-sm font-bold text-slate-950">{{ $sermon->title }}</h3><span class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-wide {{ $sermon->status === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $sermon->status }}</span></div><p class="mt-1 line-clamp-1 text-xs text-slate-400">{{ $sermon->summary ?: ($sermon->scripture ?: 'No summary added') }}</p></div></div>
                        <div role="cell" class="text-sm text-slate-600"><p class="font-semibold">{{ $sermon->speaker ?: 'Teaching team' }}</p><p class="mt-1 text-xs text-slate-400">{{ $sermon->preached_at ? $sermon->preached_at->format('M j, Y') : 'Date not set' }}</p></div>
                        <div role="cell" class="flex flex-wrap items-center gap-2 text-xs">@if ($sermon->youtube_video_id)<span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1.5 font-bold text-red-600"><i data-lucide="youtube" class="size-3.5"></i>YouTube</span>@elseif ($sermon->video_url)<span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-2.5 py-1.5 font-bold text-violet-700"><i data-lucide="video" class="size-3.5"></i>Video</span>@elseif ($sermon->audio_url)<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1.5 font-bold text-slate-600"><i data-lucide="headphones" class="size-3.5"></i>Audio</span>@else<span class="text-slate-400">No media</span>@endif @if ($sermon->youtube_live_status && $sermon->youtube_live_status !== 'none')<span class="rounded-full bg-sky-50 px-2 py-1.5 text-[10px] font-black uppercase text-sky-700">{{ $sermon->youtube_live_status }}</span>@endif</div>
                        <div role="cell" class="flex shrink-0 justify-start gap-2 md:justify-end"><a href="{{ route('sermons.edit', $sermon) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:border-violet-200 hover:text-violet-700"><i data-lucide="pencil" class="size-3.5"></i>Edit</a><form method="POST" action="{{ route('sermons.destroy', $sermon) }}" onsubmit="return confirm('Archive this sermon?')">@csrf @method('DELETE')<button class="inline-flex items-center gap-1.5 rounded-lg border border-rose-100 bg-white px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50"><i data-lucide="archive" class="size-3.5"></i>Archive</button></form></div>
                    </div>
                @empty
                    <div class="p-16 text-center"><span class="mx-auto grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="radio" class="size-6"></i></span><h2 class="mt-4 font-bold text-slate-950">Your library is ready for its first message</h2><p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500">Create a sermon to connect teaching, media, and a message of hope to your public website.</p><a href="{{ route('sermons.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white"><i data-lucide="plus" class="size-4"></i>Create sermon</a></div>
                @endforelse
                @if ($sermons->isNotEmpty())
                    <div x-cloak x-bind:class="view === 'gallery' ? 'grid' : 'hidden'" class="gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
                        @foreach ($sermons as $sermon)
                            @php
                                $galleryMediaType = $sermon->youtube_video_id ? 'youtube' : ($sermon->video_url ? 'video' : ($sermon->audio_url ? 'audio' : 'none'));
                                $gallerySearchText = Str::lower($sermon->title.' '.($sermon->speaker ?? '').' '.($sermon->summary ?? '').' '.($sermon->scripture ?? ''));
                            @endphp
                            <article x-data="{ searchText: @js($gallerySearchText), itemStatus: @js($sermon->status), itemMedia: @js($galleryMediaType) }" x-show="(query === '' || searchText.includes(query.toLowerCase())) && (status === 'all' || itemStatus === status) && (media === 'all' || itemMedia === media)" x-cloak class="group overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-lg hover:shadow-violet-100/50">
                                <div class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br from-violet-600 to-slate-950">@if ($sermon->thumbnail_url)<img src="{{ $sermonAsset($sermon->thumbnail_url) }}" alt="{{ $sermon->title }}" class="size-full object-cover transition duration-300 group-hover:scale-105">@else<span class="grid size-full place-items-center text-white"><i data-lucide="play" class="size-10 fill-current opacity-90"></i></span>@endif<div class="absolute inset-x-3 top-3 flex items-center justify-between"><span class="rounded-full bg-white/90 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700">{{ $sermon->status }}</span>@if ($sermon->youtube_live_status && $sermon->youtube_live_status !== 'none')<span class="rounded-full bg-sky-600 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-white">{{ $sermon->youtube_live_status }}</span>@endif</div></div>
                                <div class="p-3"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><h3 class="line-clamp-2 text-xs font-bold leading-4 text-slate-950">{{ $sermon->title }}</h3><p class="mt-1 text-[11px] font-medium text-slate-500">{{ $sermon->speaker ?: 'Teaching team' }}</p></div>@if ($sermon->youtube_video_id)<span class="shrink-0 rounded-full bg-red-50 p-1 text-red-600"><i data-lucide="youtube" class="size-3"></i></span>@elseif ($sermon->video_url)<span class="shrink-0 rounded-full bg-violet-50 p-1 text-violet-600"><i data-lucide="video" class="size-3"></i></span>@elseif ($sermon->audio_url)<span class="shrink-0 rounded-full bg-slate-100 p-1 text-slate-600"><i data-lucide="headphones" class="size-3"></i></span>@endif</div><p class="mt-2 line-clamp-2 text-[11px] leading-4 text-slate-400">{{ $sermon->summary ?: ($sermon->scripture ?: 'No summary added') }}</p><div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2"><span class="text-[10px] text-slate-400">{{ $sermon->preached_at ? $sermon->preached_at->format('M j, Y') : 'Date not set' }}</span><a href="{{ route('sermons.edit', $sermon) }}" class="inline-flex items-center gap-1 text-[11px] font-bold text-violet-700 hover:text-violet-800"><i data-lucide="pencil" class="size-3"></i>Edit</a></div></div>
                            </article>
                        @endforeach
                    </div>
                @endif
                @if ($sermons->isNotEmpty())<div x-show="query !== '' || status !== 'all' || media !== 'all'" x-cloak class="p-10 text-center"><i data-lucide="search" class="mx-auto size-7 text-slate-300"></i><p class="mt-3 text-sm font-bold text-slate-700">No sermons match these filters.</p><button type="button" x-on:click="query = ''; status = 'all'; media = 'all'" class="mt-2 text-xs font-bold text-violet-700">Clear filters</button></div>@endif
            </div>
        </section>
        </div>
    </div>
</x-app-layout>
