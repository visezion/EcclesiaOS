<x-app-layout title="Media Library" :breadcrumbs="$breadcrumbs">
    <div class="website-studio-admin mx-auto max-w-[1500px] space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-violet-700">
                    <i data-lucide="images" class="size-3.5"></i>
                    Website Studio · Media library
                </div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Manage your website media</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Upload images once, keep them organized, and reuse them across pages, sections, and galleries.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-xl border border-violet-100 bg-violet-50 px-4 py-3 text-sm font-bold text-violet-700">
                    <i data-lucide="image" class="size-4"></i>
                    {{ $media->count() }} reusable image{{ $media->count() === 1 ? '' : 's' }}
                </span>
                <a href="{{ route('website-studio.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700">
                    <i data-lucide="arrow-left" class="size-4"></i>
                    Back to Website Studio
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="dashboard-card overflow-hidden">
            <div class="flex flex-col gap-5 px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div class="flex items-start gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="upload" class="size-5"></i></span>
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Upload a reusable image</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Add a high-quality image to reuse across pages, sections, and galleries.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('website-studio.media.upload') }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    @csrf
                    <label class="flex min-h-11 cursor-pointer items-center rounded-xl border border-dashed border-violet-300 bg-violet-50/60 px-3 text-xs font-semibold text-slate-600 transition hover:border-violet-500 hover:bg-violet-50">
                        <input type="file" name="media" accept="image/*" required class="sr-only">
                        <span>Choose an image</span>
                    </label>
                    <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700"><i data-lucide="upload" class="size-4"></i>Upload image</button>
                </form>
            </div>
        </section>

        <section class="dashboard-card p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-violet-600">Your collection</p>
                    <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Saved images</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $media->count() }} image{{ $media->count() === 1 ? '' : 's' }} available for reuse.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span>Ready to use</span>
            </div>

            @if ($media->isNotEmpty())
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($media as $item)
                        <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg hover:shadow-violet-100/60">
                            <div class="aspect-[4/3] overflow-hidden bg-slate-100"><img src="{{ asset('storage/'.ltrim($item['path'], '/')) }}" alt="{{ $item['name'] ?? 'Website image' }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105"></div>
                            <div class="space-y-3 p-3"><p class="truncate text-xs font-bold text-slate-800" title="{{ $item['name'] ?? '' }}">{{ $item['name'] ?? 'Image' }}</p><input readonly value="{{ asset('storage/'.ltrim($item['path'], '/')) }}" onclick="this.select()" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-[10px] text-slate-500"><form method="POST" action="{{ route('website-studio.media.delete', $item['id']) }}" onsubmit="return confirm('Delete this image from the media library?')">@csrf @method('DELETE')<button class="w-full rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50">Delete image</button></form></div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-violet-200 bg-gradient-to-br from-violet-50/70 to-slate-50 px-6 py-16 text-center">
                    <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-violet-100 text-violet-600"><i data-lucide="images" class="size-7"></i></div>
                    <h3 class="mt-5 text-lg font-black text-slate-900">Your media library is ready for its first image</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Upload a church photo, logo, or announcement graphic above. It will be available throughout Website Studio.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
