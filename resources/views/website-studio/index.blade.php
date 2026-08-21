<x-app-layout title="Website Studio" :breadcrumbs="$breadcrumbs">
    <div class="space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-violet-700">
                    <i data-lucide="sparkles" class="size-3.5"></i>
                    Church Website Studio
                </div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Build a website that feels like your church.</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Choose a visual direction, shape the message, and publish pages from the same app that already manages your church.</p>
            </div>
            <a href="{{ $publicUrl }}" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700">
                <i data-lucide="external-link" class="size-4"></i>
                Open public website
            </a>
            <a href="{{ route('website-studio.pages.edit', $homepage) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-violet-200 bg-white px-4 py-3 text-sm font-bold text-violet-700 hover:bg-violet-50">
                <i data-lucide="palette" class="size-4"></i>
                Design homepage
            </a>
            <a href="{{ route('website-studio.sections') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 hover:bg-amber-100">
                <i data-lucide="blocks" class="size-4"></i>
                Manage sections
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('website-studio.settings.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')
            <section class="dashboard-card overflow-hidden p-0">
                <div class="border-b border-slate-100 bg-gradient-to-r from-violet-50 via-white to-amber-50 p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">1. Select your template</h2>
                            <p class="mt-1 text-sm text-slate-500">Templates control the mood and layout. Your content stays yours.</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" @checked($settings['enabled']) class="size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                            Website is live
                        </label>
                    </div>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                    @php
                        $templateDetails = [
                            'main' => ['label' => 'Grace & Community', 'description' => 'A complete, warm, responsive church website for worship, connection, media, and next steps.', 'icon' => 'church'],
                        ];
                    @endphp
                    @foreach ($templateDetails as $key => $template)
                        <label class="group relative cursor-pointer rounded-2xl border-2 p-4 transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-lg {{ $settings['template'] === $key ? 'border-violet-600 bg-violet-50/60 shadow-md' : 'border-slate-100 bg-white' }}">
                            <input type="radio" name="template" value="{{ $key }}" @checked($settings['template'] === $key) class="absolute right-4 top-4 size-4 text-violet-600 focus:ring-violet-500">
                            <span class="mb-5 grid size-12 place-items-center rounded-2xl bg-slate-950 text-white"><i data-lucide="{{ $template['icon'] }}" class="size-5"></i></span>
                            <span class="block text-sm font-bold text-slate-950">{{ $template['label'] }}</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $template['description'] }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
                <section class="dashboard-card space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">2. Edit template design & site content</h2>
                        <p class="mt-1 text-sm text-slate-500">These values become the shared language of every public page.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="sm:col-span-2"><span class="field-label">Site name</span><input name="site_name" value="{{ old('site_name', $settings['site_name']) }}" class="field-input"></label>
                        <label class="sm:col-span-2"><span class="field-label">Tagline</span><input name="tagline" value="{{ old('tagline', $settings['tagline']) }}" class="field-input"></label>
                        <label><span class="field-label">Primary color</span><input type="color" name="primary_color" value="{{ old('primary_color', $settings['primary_color']) }}" class="h-11 w-full rounded-lg border border-slate-200 bg-white p-1"></label>
                        <label><span class="field-label">Accent color</span><input type="color" name="accent_color" value="{{ old('accent_color', $settings['accent_color']) }}" class="h-11 w-full rounded-lg border border-slate-200 bg-white p-1"></label>
                        <label><span class="field-label">Website appearance</span><select name="color_scheme" class="field-input"><option value="dark" @selected(($settings['color_scheme'] ?? 'dark') === 'dark')>Dark mode</option><option value="light" @selected(($settings['color_scheme'] ?? 'dark') === 'light')>Light mode</option></select><span class="mt-1 block text-xs text-slate-400">Sets the default appearance. Visitors can switch modes from the website header.</span></label>
                        <label><span class="field-label">Font direction</span><select name="font" class="field-input"><option @selected($settings['font'] === 'Manrope')>Manrope</option><option @selected($settings['font'] === 'Inter')>Inter</option><option @selected($settings['font'] === 'DM Sans')>DM Sans</option><option @selected($settings['font'] === 'Playfair Display')>Playfair Display</option></select></label>
                        <label><span class="field-label">Logo URL or storage path <span class="font-normal text-slate-400">(optional)</span></span><input name="logo_url" value="{{ old('logo_url', $settings['logo_url']) }}" placeholder="https://... or branding/logo.png" class="field-input"></label>
                        <label><span class="field-label">Upload logo <span class="font-normal text-slate-400">(optional)</span></span><input type="file" name="logo_file" accept="image/*" class="field-input"></label>
                        <label><span class="field-label">Hero image URL or storage path <span class="font-normal text-slate-400">(optional)</span></span><input name="hero_image_url" value="{{ old('hero_image_url', $settings['hero_image_url']) }}" placeholder="https://... or website/hero.jpg" class="field-input"></label>
                        <label><span class="field-label">Upload hero image <span class="font-normal text-slate-400">(optional)</span></span><input type="file" name="hero_image_file" accept="image/*" class="field-input"></label>
                        <label class="sm:col-span-2"><span class="field-label">Hero video URL or storage path <span class="font-normal text-slate-400">(optional; replaces hero image)</span></span><input name="hero_video_url" value="{{ old('hero_video_url', $settings['hero_video_url'] ?? '') }}" placeholder="https://... or website/hero.mp4" class="field-input"></label>
                        <label class="sm:col-span-2"><span class="field-label">Upload hero video</span><input type="file" name="hero_video_file" accept="video/mp4,video/webm,video/ogg" class="field-input"></label>
                    </div>
                </section>

                <section class="dashboard-card space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">3. Edit homepage content</h2>
                        <p class="mt-1 text-sm text-slate-500">Your homepage content is editable without touching code.</p>
                    </div>
                    <div class="space-y-4">
                        <label><span class="field-label">Hero eyebrow</span><input name="hero_eyebrow" value="{{ old('hero_eyebrow', $settings['hero_eyebrow']) }}" class="field-input"></label>
                        <label><span class="field-label">Hero heading</span><input name="hero_heading" value="{{ old('hero_heading', $settings['hero_heading']) }}" class="field-input"></label>
                        <label><span class="field-label">Hero message</span><textarea name="hero_body" rows="3" class="field-input">{{ old('hero_body', $settings['hero_body']) }}</textarea></label>
                        <div class="grid gap-3 sm:grid-cols-2"><label><span class="field-label">Button label</span><input name="hero_button_label" value="{{ old('hero_button_label', $settings['hero_button_label']) }}" class="field-input"></label><label><span class="field-label">Button link</span><input name="hero_button_url" value="{{ old('hero_button_url', $settings['hero_button_url']) }}" class="field-input"></label></div>
                    </div>
                </section>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <section class="dashboard-card space-y-4">
                    <div><h2 class="text-lg font-semibold text-slate-950">Welcome section</h2><p class="mt-1 text-sm text-slate-500">The short message visitors see after the first impression.</p></div>
                    <label><span class="field-label">Heading</span><input name="welcome_heading" value="{{ old('welcome_heading', $settings['welcome_heading']) }}" class="field-input"></label>
                    <label><span class="field-label">Message</span><textarea name="welcome_body" rows="5" class="field-input">{{ old('welcome_body', $settings['welcome_body']) }}</textarea></label>
                </section>
                <section class="dashboard-card space-y-4">
                    <div><h2 class="text-lg font-semibold text-slate-950">Experience section</h2><p class="mt-1 text-sm text-slate-500">The heading and supporting message above the campus, stream, community, and next-step cards.</p></div>
                    <label><span class="field-label">Experience heading</span><input name="experience_heading" value="{{ old('experience_heading', $settings['experience_heading']) }}" class="field-input"></label>
                    <label><span class="field-label">Experience message</span><textarea name="experience_body" rows="4" class="field-input">{{ old('experience_body', $settings['experience_body']) }}</textarea></label>
                </section>
                <section class="dashboard-card space-y-4 xl:col-span-2">
                    <div><h2 class="text-lg font-semibold text-slate-950">Services, giving & footer content</h2><p class="mt-1 text-sm text-slate-500">Every visible phrase in the service and giving sections can be changed here.</p></div>
                    <div class="grid gap-3 sm:grid-cols-3"><label><span class="field-label">Services label</span><input name="service_kicker" value="{{ old('service_kicker', $settings['service_kicker']) }}" class="field-input"></label><label class="sm:col-span-2"><span class="field-label">Services heading</span><input name="service_heading" value="{{ old('service_heading', $settings['service_heading']) }}" class="field-input"></label></div>
                    <label><span class="field-label">Services message</span><textarea name="service_body" rows="2" class="field-input">{{ old('service_body', $settings['service_body']) }}</textarea></label>
                    <div class="grid gap-3 md:grid-cols-3">@foreach ([1, 2, 3] as $serviceNumber)<div class="rounded-xl border border-slate-100 p-3"><div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Service {{ $serviceNumber }}</div><input name="service_{{ ['one', 'two', 'three'][$serviceNumber - 1] }}_title" value="{{ old('service_'.['one', 'two', 'three'][$serviceNumber - 1].'_title', $settings['service_'.['one', 'two', 'three'][$serviceNumber - 1].'_title']) }}" class="field-input mb-2"><textarea name="service_{{ ['one', 'two', 'three'][$serviceNumber - 1] }}_body" rows="3" class="field-input">{{ old('service_'.['one', 'two', 'three'][$serviceNumber - 1].'_body', $settings['service_'.['one', 'two', 'three'][$serviceNumber - 1].'_body']) }}</textarea></div>@endforeach</div>
                    <div class="grid gap-3 md:grid-cols-2"><label><span class="field-label">Giving label</span><input name="giving_kicker" value="{{ old('giving_kicker', $settings['giving_kicker']) }}" class="field-input"></label><label><span class="field-label">Giving heading</span><input name="giving_heading" value="{{ old('giving_heading', $settings['giving_heading']) }}" class="field-input"></label><label><span class="field-label">Giving button label</span><input name="giving_button_label" value="{{ old('giving_button_label', $settings['giving_button_label']) }}" class="field-input"></label><label><span class="field-label">Giving button link</span><input name="giving_button_url" value="{{ old('giving_button_url', $settings['giving_button_url']) }}" class="field-input"></label></div>
                    <label><span class="field-label">Giving message</span><textarea name="giving_body" rows="2" class="field-input">{{ old('giving_body', $settings['giving_body']) }}</textarea></label>
                    <label><span class="field-label">Footer text</span><input name="footer_text" value="{{ old('footer_text', $settings['footer_text']) }}" class="field-input"></label>
                </section>
                <section class="dashboard-card space-y-4">
                    <div><h2 class="text-lg font-semibold text-slate-950">Contact & discoverability</h2><p class="mt-1 text-sm text-slate-500">Make it easy for a first-time visitor to take the next step.</p></div>
                    <div class="grid gap-3 sm:grid-cols-2"><label><span class="field-label">Contact label</span><input name="contact_kicker" value="{{ old('contact_kicker', $settings['contact_kicker']) }}" class="field-input"></label><label><span class="field-label">Contact heading</span><input name="contact_heading" value="{{ old('contact_heading', $settings['contact_heading']) }}" class="field-input"></label></div>
                    <div class="grid gap-3 sm:grid-cols-2"><label><span class="field-label">Email</span><input type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" class="field-input"></label><label><span class="field-label">Phone</span><input name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}" class="field-input"></label></div>
                    <label><span class="field-label">Address</span><textarea name="contact_address" rows="2" class="field-input">{{ old('contact_address', $settings['contact_address']) }}</textarea></label>
                    <label><span class="field-label">SEO description</span><textarea name="seo_description" rows="2" class="field-input">{{ old('seo_description', $settings['seo_description']) }}</textarea></label>
                </section>
            </div>
            <div class="flex justify-end"><button class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>Save website settings</button></div>
        </form>

        <section class="dashboard-card space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 class="text-lg font-semibold text-slate-950">4. Select a page to edit</h2><p class="mt-1 text-sm text-slate-500">Open any page to edit its text, media, sections, order, and publishing state.</p></div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $pages->count() }} pages</span>
            </div>
            <div class="grid gap-4 xl:grid-cols-[1fr_1.2fr]">
                <form method="POST" action="{{ route('website-studio.pages.store') }}" class="rounded-2xl border border-dashed border-violet-200 bg-violet-50/40 p-4 space-y-4">
                    @csrf
                    <div class="text-sm font-bold text-slate-950">Create a page</div>
                    <label><span class="field-label">Page title</span><input name="title" required placeholder="About our church" class="field-input"></label>
                    <label><span class="field-label">URL slug <span class="font-normal text-slate-400">(optional)</span></span><input name="slug" placeholder="about" class="field-input"></label>
                    <label><span class="field-label">Opening content</span><textarea name="body" rows="4" placeholder="Tell your story..." class="field-input"></textarea></label>
                    <div><span class="field-label">Sections</span><div class="grid gap-2 sm:grid-cols-2">@foreach ($sectionTypes as $key => $label)<label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" name="section_types[]" value="{{ $key }}" @checked(in_array($key, ['welcome', 'contact'], true)) class="rounded border-slate-300 text-violet-600">{{ $label }}</label>@endforeach</div></div>
                    <select name="status" class="field-input"><option value="draft">Save as draft</option><option value="published">Publish now</option></select>
                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Create page</button>
                </form>
                <div class="space-y-3">
                    @foreach ($pages as $page)
                        <form method="POST" action="{{ route('website-studio.pages.update', $page) }}" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                            @csrf
                            @method('PUT')
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex min-w-0 items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $page->status === 'published' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}"><i data-lucide="{{ $page->slug === 'home' ? 'house' : 'file-text' }}" class="size-4"></i></span><div class="min-w-0"><input name="title" value="{{ $page->title }}" class="w-full border-0 bg-transparent p-0 text-sm font-bold text-slate-950 focus:ring-0"><div class="mt-1 text-xs text-slate-400">/site/{{ $church->slug }}/{{ $page->slug }}</div></div></div>
                                <div class="flex items-center gap-2"><a href="{{ route('website-studio.pages.edit', $page) }}" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-violet-600" title="Design page"><i data-lucide="palette" class="size-4"></i></a><a href="{{ route('website-studio.preview', $page) }}" target="_blank" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-violet-600" title="Preview"><i data-lucide="eye" class="size-4"></i></a>@if ($page->slug !== 'home')<button type="submit" form="delete-page-{{ $page->id }}" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Archive"><i data-lucide="archive" class="size-4"></i></button>@endif</div>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_150px]"><input name="slug" value="{{ $page->slug }}" class="field-input"><select name="status" class="field-input"><option value="draft" @selected($page->status === 'draft')>Draft</option><option value="published" @selected($page->status === 'published')>Published</option></select></div>
                            <textarea name="body" rows="2" placeholder="Optional page introduction" class="field-input mt-3">{{ $page->body }}</textarea>
                            <div class="mt-3 flex items-center justify-between gap-3"><div class="text-xs text-slate-400">{{ $page->updated_at?->diffForHumans() }}</div><button class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100"><i data-lucide="save" class="size-3.5"></i>Save page</button></div>
                            @foreach ($page->sections ?? [] as $section)<input type="hidden" name="section_types[]" value="{{ is_array($section) ? ($section['type'] ?? 'welcome') : $section }}">@endforeach
                        </form>
                        @if ($page->slug !== 'home')<form id="delete-page-{{ $page->id }}" method="POST" action="{{ route('website-studio.pages.destroy', $page) }}">@csrf @method('DELETE')</form>@endif
                    @endforeach
                </div>
            </div>
        </section>
    </div>
    <style>
        .field-label { display:block; margin-bottom:.4rem; font-size:.72rem; font-weight:700; color:#475569; }
        .field-input { display:block; width:100%; border-radius:.7rem; border:1px solid #e2e8f0; background:#fff; padding:.65rem .75rem; font-size:.875rem; color:#0f172a; outline:none; }
        .field-input:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgb(139 92 246 / .12); }
    </style>
</x-app-layout>
