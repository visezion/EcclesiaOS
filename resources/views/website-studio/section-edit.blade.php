<x-app-layout title="Edit Section" :breadcrumbs="$breadcrumbs">
    <link rel="stylesheet" href="{{ asset('css/website-studio/section-builder.css') }}?v={{ filemtime(public_path('css/website-studio/section-builder.css')) }}">

    <div class="website-studio-admin section-edit-page w-full space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-violet-700">
                    <i data-lucide="blocks" class="size-3.5"></i>
                    Website Studio &middot; Reusable sections
                </div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Edit {{ $section['title'] }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Update the content, layout, and publish locations for this reusable section.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('website-studio.media') }}" class="inline-flex items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-bold text-violet-700 transition hover:bg-violet-100"><i data-lucide="images" class="size-4"></i>Open media library</a>
                <a href="{{ route('website-studio.sections') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="arrow-left" class="size-4"></i>Back to sections</a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <form id="delete-section" method="POST" action="{{ route('website-studio.sections.destroy', $section['id']) }}">@csrf @method('DELETE')</form>
        <form method="POST" action="{{ route('website-studio.sections.update', $section['id']) }}" enctype="multipart/form-data" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0 space-y-5">
                    <section class="dashboard-card space-y-5">
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-violet-50 text-sm font-black text-violet-700">1</span>
                            <div><h2 class="text-lg font-bold text-slate-950">Section details</h2><p class="mt-1 text-sm text-slate-500">Give this reusable section a clear name and optional introduction.</p></div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label><span class="field-label">Section title</span><input name="title" value="{{ old('title', $section['title']) }}" required class="field-input" placeholder="A place to belong"></label>
                            <label><span class="field-label">Small label <span class="optional">(optional)</span></span><input name="eyebrow" value="{{ old('eyebrow', $section['eyebrow'] ?? '') }}" class="field-input" placeholder="Your next step"></label>
                        </div>
                        <label><span class="field-label">Intro text <span class="optional">(optional)</span></span><textarea name="body" rows="3" class="field-input" placeholder="Write a short introduction...">{{ old('body', $section['body'] ?? '') }}</textarea></label>
                    </section>

                    <section class="dashboard-card overflow-hidden p-0">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-violet-50 via-white to-cyan-50 p-5 sm:p-6">
                            <div class="flex items-start gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-sm font-black text-violet-700 shadow-sm ring-1 ring-violet-100">2</span>
                                <div><h2 class="text-lg font-bold text-slate-950">Design columns and widgets</h2><p class="mt-1 text-sm text-slate-500">Arrange columns, backgrounds, text, images, videos, buttons, and other content blocks.</p></div>
                            </div>
                        </div>
                        <div class="p-4 sm:p-6">
                            <div class="builder-shell" data-builder><div data-builder-canvas></div><input type="hidden" name="components" data-components-output><script type="application/json" data-components-seed>@json($section['components'] ?? [])</script></div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5 xl:sticky xl:top-5 xl:self-start">
                    <section class="dashboard-card space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-violet-50 text-sm font-black text-violet-700">3</span>
                            <div><h2 class="text-base font-bold text-slate-950">Publish location</h2><p class="mt-1 text-xs leading-5 text-slate-500">Choose every page that should display this section.</p></div>
                        </div>
                        <div class="space-y-2">
                            @foreach ($pages as $page)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-violet-200 hover:bg-violet-50"><input type="checkbox" name="page_slugs[]" value="{{ $page->slug }}" @checked(in_array($page->slug, old('page_slugs', $section['page_slugs'] ?? []), true)) class="rounded border-slate-300 text-violet-600 focus:ring-violet-500"><span>{{ $page->title }}</span></label>
                            @endforeach
                        </div>
                    </section>

                    <section class="dashboard-card space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-700"><i data-lucide="paintbrush" class="size-4"></i></span>
                            <div><h2 class="text-base font-bold text-slate-950">Section appearance</h2><p class="mt-1 text-xs leading-5 text-slate-500">Set the background and size for the entire section.</p></div>
                        </div>
                        <label><span class="field-label">Background color</span><input type="color" name="background_color" value="{{ old('background_color', $section['background_color'] ?? '#ffffff') }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white p-1"></label>
                        <label><span class="field-label">Background image URL <span class="optional">(optional)</span></span><input name="background_image_url" value="{{ old('background_image_url', $section['background_image_url'] ?? '') }}" class="field-input" placeholder="https://..."></label>
                        <label><span class="field-label">Replace background image</span><input type="file" name="background_image_file" accept="image/*" class="field-input"></label>
                        <label><span class="field-label">Background video URL <span class="optional">(optional)</span></span><input name="background_video_url" value="{{ old('background_video_url', $section['background_video_url'] ?? '') }}" class="field-input" placeholder="https://..."></label>
                        <label><span class="field-label">Replace background video</span><input type="file" name="background_video_file" accept="video/mp4,video/webm,video/ogg" class="field-input"></label>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <label><span class="field-label">Content width</span><select name="content_width" class="field-input"><option value="default" @selected(old('content_width', $section['content_width'] ?? 'default') === 'default')>Default</option><option value="wide" @selected(old('content_width', $section['content_width'] ?? '') === 'wide')>Wide</option><option value="full" @selected(old('content_width', $section['content_width'] ?? '') === 'full')>Full width</option></select></label>
                            <label><span class="field-label">Section height</span><select name="section_height" class="field-input"><option value="auto" @selected(old('section_height', $section['section_height'] ?? 'auto') === 'auto')>Fit content</option><option value="compact" @selected(old('section_height', $section['section_height'] ?? '') === 'compact')>Compact</option><option value="tall" @selected(old('section_height', $section['section_height'] ?? '') === 'tall')>Tall</option><option value="full" @selected(old('section_height', $section['section_height'] ?? '') === 'full')>Full screen</option></select></label>
                        </div>
                    </section>

                    <section class="dashboard-card space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-800"><i data-lucide="image-plus" class="size-4"></i></span>
                            <div><h2 class="text-base font-bold text-slate-950">Optional section media</h2><p class="mt-1 text-xs leading-5 text-slate-500">Add shared media for the whole section.</p></div>
                        </div>
                        <label><span class="field-label">Image URL</span><input name="image_url" value="{{ old('image_url', $section['image_url'] ?? '') }}" class="field-input" placeholder="https://..."></label>
                        <label><span class="field-label">Replace image</span><input type="file" name="image_file" accept="image/*" class="field-input"></label>
                        <label><span class="field-label">Video URL</span><input name="video_url" value="{{ old('video_url', $section['video_url'] ?? '') }}" class="field-input" placeholder="https://..."></label>
                        <label><span class="field-label">Replace video</span><input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg" class="field-input"></label>
                    </section>
                </aside>
            </div>

            <div class="sticky bottom-3 z-20 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <button type="submit" form="delete-section" class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-5 py-3 text-sm font-bold text-rose-600 transition hover:bg-rose-50" onclick="return confirm('Delete this reusable section?')"><i data-lucide="trash-2" class="size-4"></i>Delete section</button>
                <div class="flex gap-2">
                    <a href="{{ route('website-studio.sections') }}" class="inline-flex flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 sm:flex-none">Cancel</a>
                    <button class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 transition hover:bg-violet-700 sm:flex-none"><i data-lucide="save" class="size-4"></i>Save section</button>
                </div>
            </div>
        </form>
    </div>

    <style>
        .section-edit-page aside input[type=file]{min-height:4rem;border:1px dashed color-mix(in srgb,var(--studio-primary,var(--brand-primary,#7c3aed)) 38%,#fff);border-radius:.8rem;background:color-mix(in srgb,var(--studio-primary,var(--brand-primary,#7c3aed)) 4%,#fff);padding:.75rem;color:#64748b;font-size:.75rem}
        .section-edit-page aside input[type=file]::file-selector-button{margin-right:.6rem;border:0;border-radius:.5rem;background:color-mix(in srgb,var(--studio-primary,var(--brand-primary,#7c3aed)) 14%,#fff);padding:.45rem .65rem;color:var(--studio-primary,var(--brand-primary,#6d28d9));font-size:.72rem;font-weight:800}
        .section-edit-page .builder-shell{border-color:color-mix(in srgb,var(--studio-primary,var(--brand-primary,#7c3aed)) 22%,#fff);background:color-mix(in srgb,var(--studio-primary,var(--brand-primary,#7c3aed)) 3%,#fff)}
        .field-label{display:block;margin-bottom:.35rem;font-size:.72rem;font-weight:700;color:#475569}
        .field-input{display:block;width:100%;border-radius:.7rem;border:1px solid #e2e8f0;background:#fff;padding:.65rem .75rem;font-size:.875rem;color:#0f172a;outline:none}
        .field-input:focus{border-color:var(--studio-primary,var(--brand-primary,#8b5cf6));box-shadow:0 0 0 3px color-mix(in srgb,var(--studio-primary,var(--brand-primary,#8b5cf6)) 12%,transparent)}
        .optional{font-weight:400;color:#94a3b8}
    </style>
    <script src="{{ asset('js/website-studio/section-builder.js') }}?v={{ filemtime(public_path('js/website-studio/section-builder.js')) }}" defer></script>
</x-app-layout>
