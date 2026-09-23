<x-app-layout title="Media Library" :breadcrumbs="$breadcrumbs">
    <div class="website-studio-admin mx-auto max-w-[1500px] space-y-6">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#0b1734] via-[#172554] to-[#312e81] px-6 py-8 text-white shadow-xl shadow-indigo-950/15 sm:px-10 sm:py-10">
            <div class="pointer-events-none absolute -right-20 -top-24 -z-10 size-72 rounded-full bg-violet-500/25 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 left-1/3 -z-10 size-80 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="relative flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.18em] text-indigo-100">
                        <span class="grid size-5 place-items-center rounded-full bg-emerald-400/20 text-emerald-300">✦</span>
                        Website Studio <span class="text-indigo-300">/</span> Media Library
                    </div>
                    <h1 class="text-3xl font-black tracking-tight text-white sm:text-5xl">Your church media, beautifully organized.</h1>
                    <p class="mt-4 max-w-xl text-sm leading-7 text-indigo-100/80 sm:text-base">Upload once, reuse everywhere, and keep every image ready for your church website.</p>
                </div>
                <div class="flex shrink-0 items-center gap-4 rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
                    <span class="grid size-12 place-items-center rounded-xl bg-white/15 text-2xl text-cyan-200">▧</span>
                    <div>
                        <p class="text-2xl font-black text-white">{{ $media->count() }}</p>
                        <p class="text-xs font-semibold text-indigo-100/75">Reusable image{{ $media->count() === 1 ? '' : 's' }}</p>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="dashboard-card overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-5 px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div class="flex items-start gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-50 text-xl text-indigo-600">↑</span>
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Upload a reusable image</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Add a high-quality image to reuse across pages, sections, and galleries.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('website-studio.media.upload') }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    @csrf
                    <label class="flex min-h-11 cursor-pointer items-center rounded-xl border border-dashed border-indigo-300 bg-indigo-50/60 px-3 text-xs font-semibold text-slate-600 transition hover:border-indigo-500 hover:bg-indigo-50">
                        <input type="file" name="media" accept="image/*" required class="sr-only">
                        <span>Choose an image</span>
                    </label>
                    <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-slate-200 transition hover:bg-indigo-700">Upload image</button>
                </form>
            </div>
        </section>

        <section class="dashboard-card rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-indigo-600">Your collection</p>
                    <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Saved images</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $media->count() }} image{{ $media->count() === 1 ? '' : 's' }} available for reuse.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span>Ready to use</span>
            </div>

            @if ($media->isNotEmpty())
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($media as $item)
                        <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-100/60">
                            <div class="aspect-[4/3] overflow-hidden bg-slate-100"><img src="{{ asset('storage/'.ltrim($item['path'], '/')) }}" alt="{{ $item['name'] ?? 'Website image' }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105"></div>
                            <div class="space-y-3 p-3"><p class="truncate text-xs font-bold text-slate-800" title="{{ $item['name'] ?? '' }}">{{ $item['name'] ?? 'Image' }}</p><input readonly value="{{ asset('storage/'.ltrim($item['path'], '/')) }}" onclick="this.select()" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-[10px] text-slate-500"><form method="POST" action="{{ route('website-studio.media.delete', $item['id']) }}" onsubmit="return confirm('Delete this image from the media library?')">@csrf @method('DELETE')<button class="w-full rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50">Delete image</button></form></div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-indigo-200 bg-gradient-to-br from-indigo-50/70 to-slate-50 px-6 py-16 text-center">
                    <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-indigo-100 text-2xl text-indigo-600">✦</div>
                    <h3 class="mt-5 text-lg font-black text-slate-900">Your media library is ready for its first image</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Upload a church photo, logo, or announcement graphic above. It will be available throughout Website Studio.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
