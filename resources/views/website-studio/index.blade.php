<x-app-layout title="Website Studio" :breadcrumbs="$breadcrumbs">
    <div class="website-studio-admin space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-violet-700">
                    <i data-lucide="sparkles" class="size-3.5"></i>
                    Church Website Studio
                </div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Build a website that feels like your church.</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Choose a visual direction, shape the message, and publish pages from the same app that already manages your church.</p>
            </div>
            <div class="flex flex-wrap gap-2">
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
            <a href="{{ route('website-studio.navigation') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-bold text-cyan-800 hover:bg-cyan-100">
                <i data-lucide="menu" class="size-4"></i>
                {{ __('Navigation Builder') }}
            </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="website-studio-shell">
        <div class="website-studio-main">
        <section class="website-studio-overview">
            <div class="grid gap-5 md:grid-cols-2">
                <article class="dashboard-card website-status-card"><div class="website-studio-card-icon {{ ($settings['enabled'] ?? true) ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}"><i data-lucide="{{ ($settings['enabled'] ?? true) ? 'check-circle-2' : 'circle-pause' }}" class="size-5"></i></div><div><p class="website-studio-card-label">Website status</p><h2>{{ ($settings['enabled'] ?? true) ? 'Website is live' : 'Website is offline' }}</h2><p>{{ ($settings['enabled'] ?? true) ? 'Your public website and published pages are visible.' : 'Publish your website when you are ready.' }}</p><a href="{{ $publicUrl }}" target="_blank" rel="noreferrer">View site <span>→</span></a></div></article>
                <article class="dashboard-card website-template-card"><div class="website-studio-card-icon bg-violet-50 text-violet-600"><i data-lucide="layout-template" class="size-5"></i></div><div><p class="website-studio-card-label">Current template</p><h2>{{ $templates[$settings['template']] ?? 'Grace & Community' }}</h2><p>A welcoming design system for connection, media, and next steps.</p><a href="#template-settings">Edit template <span>→</span></a></div></article>
            </div>
        </section>
        <section class="website-section-shortcuts">
            <div class="website-shortcut-heading"><div><p class="website-studio-card-label">Design & content</p><h2>Customize your website</h2></div><span>Quick edit sections</span></div>
            <div class="website-shortcut-grid">
                @foreach ([['homepage-content','house','Edit homepage content','Update your homepage hero, message, and actions.'],['welcome-section','heart','Welcome section','Introduce your church and mission.'],['experience-section','users','Experience section','Highlight what visitors will experience.'],['sermon-next-step','play','Sermon next-step section','Guide visitors toward their next step.'],['services-giving','gift','Services, giving & footer','Manage service details and generosity content.'],['contact-discovery','map-pin','Contact & discoverability','Update contact details and search content.']] as [$target, $icon, $title, $description])
                    <a href="#{{ $target }}" class="website-shortcut-card"><span><i data-lucide="{{ $icon }}" class="size-4"></i></span><div><h3>{{ $title }}</h3><p>{{ $description }}</p><strong>Edit section →</strong></div></a>
                @endforeach
            </div>
        </section>

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
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($settings['enabled']) class="size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">Website is live</label>
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

            <div id="template-settings" class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
                <section class="dashboard-card space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">2. Edit template design & site content</h2>
                        <p class="mt-1 text-sm text-slate-500">These values become the shared language of every public page.</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <label><span class="field-label">Site name</span><input name="site_name" value="{{ old('site_name', $settings['site_name']) }}" class="field-input"></label>
                        <label><span class="field-label">Tagline</span><input name="tagline" value="{{ old('tagline', $settings['tagline']) }}" class="field-input"></label>
                        <label><span class="field-label">Website appearance</span><select name="color_scheme" class="field-input"><option value="dark" @selected(($settings['color_scheme'] ?? 'dark') === 'dark')>Dark mode</option><option value="light" @selected(($settings['color_scheme'] ?? 'dark') === 'light')>Light mode</option></select><span class="mt-1 block text-xs text-slate-400">Sets the default appearance. Visitors can switch modes.</span></label>
                        <label><span class="field-label">Primary color</span><input type="color" name="primary_color" value="{{ old('primary_color', $settings['primary_color']) }}" class="h-11 w-full rounded-lg border border-slate-200 bg-white p-1"></label>
                        <label><span class="field-label">Accent color</span><input type="color" name="accent_color" value="{{ old('accent_color', $settings['accent_color']) }}" class="h-11 w-full rounded-lg border border-slate-200 bg-white p-1"></label>
                        <label><span class="field-label">Font direction</span><select name="font" class="field-input"><option @selected($settings['font'] === 'Manrope')>Manrope</option><option @selected($settings['font'] === 'Inter')>Inter</option><option @selected($settings['font'] === 'DM Sans')>DM Sans</option><option @selected($settings['font'] === 'Playfair Display')>Playfair Display</option></select></label>
                        @php
                            $heroSlideSeed = old('hero_slides');
                            $heroSlideSeed = is_string($heroSlideSeed) ? json_decode($heroSlideSeed, true) : ($heroSlideSeed ?? ($settings['hero_slides'] ?? []));
                            $heroSlideSeed = is_array($heroSlideSeed) ? $heroSlideSeed : [];
                        @endphp
                        <div class="md:col-span-3 hero-slide-manager" data-hero-slide-manager>
                            <div class="hero-slide-manager-head"><div><span class="field-label">Homepage hero media</span><p>Add up to 12 images or videos. Visitors will see them in this order.</p></div><button type="button" data-add-hero-slide><i data-lucide="plus" class="size-4"></i>Add media</button></div>
                            <div class="hero-slide-list" data-hero-slide-list>
                                @foreach ($heroSlideSeed as $slideIndex => $slide)
                                    @php($slideKey = 'saved-'.$slideIndex)
                                    <div class="hero-slide-row" data-hero-slide-row data-slide-key="{{ $slideKey }}">
                                        <span class="hero-slide-number">{{ $slideIndex + 1 }}</span>
                                        <label><span class="field-label">Type</span><select name="hero_slides[{{ $slideKey }}][type]" class="field-input" data-hero-field="type"><option value="image" @selected(($slide['type'] ?? 'image') === 'image')>Image</option><option value="video" @selected(($slide['type'] ?? 'image') === 'video')>Video</option></select></label>
                                        <label><span class="field-label">Media link <span class="optional">(optional)</span></span><input name="hero_slides[{{ $slideKey }}][url]" class="field-input" data-hero-field="url" value="{{ $slide['url'] ?? '' }}" placeholder="Paste an image or video link"></label>
                                        <label><span class="field-label">Upload or replace</span><input type="file" name="hero_slide_files[{{ $slideKey }}]" accept="image/*,video/mp4,video/webm,video/ogg" class="field-input"></label>
                                        <label><span class="field-label">Media position</span><select name="hero_slides[{{ $slideKey }}][position]" class="field-input">@foreach (['center' => 'Center', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right'] as $position => $label)<option value="{{ $position }}" @selected(($slide['position'] ?? 'center') === $position)>{{ $label }}</option>@endforeach</select></label>
                                        <label data-poster-field><span class="field-label">Video preview <span class="optional">(optional)</span></span><input name="hero_slides[{{ $slideKey }}][poster]" class="field-input" data-hero-field="poster" value="{{ $slide['poster'] ?? '' }}" placeholder="Paste a preview image link"></label>
                                        <label data-poster-field><span class="field-label">Upload poster</span><input type="file" name="hero_slide_poster_files[{{ $slideKey }}]" accept="image/*" class="field-input"></label>
                                        <div class="hero-slide-actions"><button type="button" data-hero-up title="Move up"><i data-lucide="arrow-up" class="size-4"></i></button><button type="button" data-hero-down title="Move down"><i data-lucide="arrow-down" class="size-4"></i></button><button type="button" class="is-danger" data-remove-hero-slide title="Remove"><i data-lucide="trash-2" class="size-4"></i></button></div>
                                    </div>
                                @endforeach
                            </div>
                            <input type="hidden" name="hero_slides_configured" value="1">
                            <template data-hero-slide-template><div class="hero-slide-row" data-hero-slide-row data-slide-key="__KEY__"><span class="hero-slide-number"></span><label><span class="field-label">Type</span><select name="hero_slides[__KEY__][type]" class="field-input" data-hero-field="type"><option value="image">Image</option><option value="video">Video</option></select></label><label><span class="field-label">Media link <span class="optional">(optional)</span></span><input name="hero_slides[__KEY__][url]" class="field-input" data-hero-field="url" placeholder="Paste an image or video link"></label><label><span class="field-label">Upload media</span><input type="file" name="hero_slide_files[__KEY__]" accept="image/*,video/mp4,video/webm,video/ogg" class="field-input"></label><label><span class="field-label">Media position</span><select name="hero_slides[__KEY__][position]" class="field-input"><option value="center">Center</option><option value="top">Top</option><option value="bottom">Bottom</option><option value="left">Left</option><option value="right">Right</option></select></label><label data-poster-field hidden><span class="field-label">Video preview <span class="optional">(optional)</span></span><input name="hero_slides[__KEY__][poster]" class="field-input" data-hero-field="poster" placeholder="Paste a preview image link"></label><label data-poster-field hidden><span class="field-label">Upload preview</span><input type="file" name="hero_slide_poster_files[__KEY__]" accept="image/*" class="field-input"></label><div class="hero-slide-actions"><button type="button" data-hero-up title="Move up"><i data-lucide="arrow-up" class="size-4"></i></button><button type="button" data-hero-down title="Move down"><i data-lucide="arrow-down" class="size-4"></i></button><button type="button" class="is-danger" data-remove-hero-slide title="Remove"><i data-lucide="trash-2" class="size-4"></i></button></div></div></template>
                            <label class="mt-4 block"><span class="field-label">Hero media height (px)</span><input type="number" name="hero_height" min="300" max="900" step="10" value="{{ old('hero_height', $settings['hero_height'] ?? 560) }}" class="field-input"><span class="mt-1 block text-xs text-slate-400">Adjusts the hero image/video height on desktop; mobile remains responsive.</span></label>
                        </div>
                    </div>
                </section>

                <section id="homepage-content" class="dashboard-card space-y-5 website-edit-panel" data-editor-title="Edit homepage content">
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
                <section id="welcome-section" class="dashboard-card space-y-4 website-edit-panel" data-editor-title="Welcome section">
                    <div><h2 class="text-lg font-semibold text-slate-950">Welcome section</h2><p class="mt-1 text-sm text-slate-500">The short message visitors see after the first impression.</p></div>
                    <label><span class="field-label">Heading</span><input name="welcome_heading" value="{{ old('welcome_heading', $settings['welcome_heading']) }}" class="field-input"></label>
                    <label><span class="field-label">Message</span><textarea name="welcome_body" rows="5" class="field-input">{{ old('welcome_body', $settings['welcome_body']) }}</textarea></label>
                </section>
                <section id="experience-section" class="dashboard-card space-y-4 website-edit-panel" data-editor-title="Experience section">
                    <div><h2 class="text-lg font-semibold text-slate-950">Experience section</h2><p class="mt-1 text-sm text-slate-500">The heading and supporting message above the campus, stream, community, and next-step cards.</p></div>
                    <label><span class="field-label">Experience heading</span><input name="experience_heading" value="{{ old('experience_heading', $settings['experience_heading']) }}" class="field-input"></label>
                    <label><span class="field-label">Experience message</span><textarea name="experience_body" rows="4" class="field-input">{{ old('experience_body', $settings['experience_body']) }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label><span class="field-label">Section label</span><input name="experience_kicker" value="{{ old('experience_kicker', $settings['experience_kicker']) }}" class="field-input"></label>@foreach (['one' => 'First card', 'two' => 'Second card', 'three' => 'Third card', 'four' => 'Fourth card'] as $experienceKey => $experienceLabel)<div class="rounded-xl border border-slate-100 p-3"><span class="field-label">{{ $experienceLabel }}</span><input name="experience_{{ $experienceKey }}_title" value="{{ old('experience_'.$experienceKey.'_title', $settings['experience_'.$experienceKey.'_title']) }}" class="field-input mb-2" placeholder="Card title"><textarea name="experience_{{ $experienceKey }}_body" rows="2" class="field-input mb-2" placeholder="Card description">{{ old('experience_'.$experienceKey.'_body', $settings['experience_'.$experienceKey.'_body']) }}</textarea><input name="experience_{{ $experienceKey }}_link" value="{{ old('experience_'.$experienceKey.'_link', $settings['experience_'.$experienceKey.'_link']) }}" class="field-input" placeholder="Button label"></div>@endforeach</div>
                </section>
                <section id="sermon-next-step" class="dashboard-card space-y-4 xl:col-span-2 website-edit-panel" data-editor-title="Sermon next-step section">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div><h2 class="text-lg font-semibold text-slate-950">Sermon next-step section</h2><p class="mt-1 text-sm text-slate-500">Edit the faith invitation shown below each sermon’s message details.</p></div>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="hidden" name="sermon_next_step_enabled" value="0"><input type="checkbox" name="sermon_next_step_enabled" value="1" @checked($settings['sermon_next_step_enabled'] ?? true) class="size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">Show on sermon pages</label>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label><span class="field-label">Section label</span><input name="sermon_next_step_kicker" value="{{ old('sermon_next_step_kicker', $settings['sermon_next_step_kicker'] ?? 'Your next step') }}" class="field-input"></label>
                        <label class="md:col-span-2"><span class="field-label">Heading</span><input name="sermon_next_step_heading" value="{{ old('sermon_next_step_heading', $settings['sermon_next_step_heading'] ?? 'Take your next step of faith') }}" class="field-input"></label>
                    </div>
                    <label><span class="field-label">Supporting message</span><textarea name="sermon_next_step_body" rows="2" class="field-input">{{ old('sermon_next_step_body', $settings['sermon_next_step_body'] ?? '') }}</textarea></label>
                    <div class="grid gap-3 lg:grid-cols-3">
                        @foreach (['one' => 'Card one', 'two' => 'Card two', 'three' => 'Card three'] as $stepKey => $stepLabel)
                            <div class="rounded-xl border border-slate-100 p-3">
                                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">{{ $stepLabel }}</div>
                                <div class="grid grid-cols-[4rem_1fr] gap-2"><label><span class="field-label">Icon</span><input name="sermon_next_step_{{ $stepKey }}_icon" value="{{ old('sermon_next_step_'.$stepKey.'_icon', $settings['sermon_next_step_'.$stepKey.'_icon'] ?? '♡') }}" class="field-input"></label><label><span class="field-label">Title</span><input name="sermon_next_step_{{ $stepKey }}_title" value="{{ old('sermon_next_step_'.$stepKey.'_title', $settings['sermon_next_step_'.$stepKey.'_title'] ?? '') }}" class="field-input"></label></div>
                                <label class="mt-2 block"><span class="field-label">Description</span><textarea name="sermon_next_step_{{ $stepKey }}_body" rows="4" class="field-input">{{ old('sermon_next_step_'.$stepKey.'_body', $settings['sermon_next_step_'.$stepKey.'_body'] ?? '') }}</textarea></label>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2"><label><span class="field-label">Button label</span><input name="sermon_next_step_{{ $stepKey }}_link_label" value="{{ old('sermon_next_step_'.$stepKey.'_link_label', $settings['sermon_next_step_'.$stepKey.'_link_label'] ?? 'Learn more') }}" class="field-input"></label><label><span class="field-label">Button link</span><input name="sermon_next_step_{{ $stepKey }}_link" value="{{ old('sermon_next_step_'.$stepKey.'_link', $settings['sermon_next_step_'.$stepKey.'_link'] ?? '#contact') }}" class="field-input"></label></div>
                            </div>
                        @endforeach
                    </div>
                </section>
                <section id="services-giving" class="dashboard-card space-y-4 xl:col-span-2 website-edit-panel" data-editor-title="Services, giving & footer">
                    <div><h2 class="text-lg font-semibold text-slate-950">Services, giving & footer content</h2><p class="mt-1 text-sm text-slate-500">Every visible phrase in the service and giving sections can be changed here.</p></div>
                    <div class="grid gap-3 sm:grid-cols-3"><label><span class="field-label">Services label</span><input name="service_kicker" value="{{ old('service_kicker', $settings['service_kicker']) }}" class="field-input"></label><label class="sm:col-span-2"><span class="field-label">Services heading</span><input name="service_heading" value="{{ old('service_heading', $settings['service_heading']) }}" class="field-input"></label></div>
                    <label><span class="field-label">Services message</span><textarea name="service_body" rows="2" class="field-input">{{ old('service_body', $settings['service_body']) }}</textarea></label>
                    <div class="grid gap-3 md:grid-cols-3">@foreach ([1, 2, 3] as $serviceNumber)<div class="rounded-xl border border-slate-100 p-3"><div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Service {{ $serviceNumber }}</div><input name="service_{{ ['one', 'two', 'three'][$serviceNumber - 1] }}_title" value="{{ old('service_'.['one', 'two', 'three'][$serviceNumber - 1].'_title', $settings['service_'.['one', 'two', 'three'][$serviceNumber - 1].'_title']) }}" class="field-input mb-2"><textarea name="service_{{ ['one', 'two', 'three'][$serviceNumber - 1] }}_body" rows="3" class="field-input">{{ old('service_'.['one', 'two', 'three'][$serviceNumber - 1].'_body', $settings['service_'.['one', 'two', 'three'][$serviceNumber - 1].'_body']) }}</textarea></div>@endforeach</div>
                    <div class="grid gap-3 md:grid-cols-2"><label><span class="field-label">Giving label</span><input name="giving_kicker" value="{{ old('giving_kicker', $settings['giving_kicker']) }}" class="field-input"></label><label><span class="field-label">Giving heading</span><input name="giving_heading" value="{{ old('giving_heading', $settings['giving_heading']) }}" class="field-input"></label><label><span class="field-label">Giving button label</span><input name="giving_button_label" value="{{ old('giving_button_label', $settings['giving_button_label']) }}" class="field-input"></label><label><span class="field-label">Giving button link</span><input name="giving_button_url" value="{{ old('giving_button_url', $settings['giving_button_url']) }}" class="field-input"></label></div>
                    <label><span class="field-label">Giving message</span><textarea name="giving_body" rows="2" class="field-input">{{ old('giving_body', $settings['giving_body']) }}</textarea></label>
                    <label><span class="field-label">Footer text</span><input name="footer_text" value="{{ old('footer_text', $settings['footer_text']) }}" class="field-input"></label>
                </section>
                <section id="contact-discovery" class="dashboard-card space-y-4 website-edit-panel" data-editor-title="Contact & discoverability">
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
                <div class="website-page-actions"><label><i data-lucide="search" class="size-4"></i><input type="search" placeholder="Search pages..." data-page-search></label><a href="#create-page" data-create-page-open><i data-lucide="plus" class="size-4"></i>Add new page</a></div>
            </div>
            <div class="website-pages-layout">
                <form id="create-page" method="POST" action="{{ route('website-studio.pages.store') }}" class="website-create-page space-y-4" role="dialog" aria-modal="true" aria-labelledby="create-page-title" data-create-page-modal>
                    @csrf
                    <div class="website-create-page-header">
                        <div><p class="website-studio-card-label">Website page</p><h3 id="create-page-title">Create a new page</h3><span>Add content and choose the sections visitors will see.</span></div>
                        <button type="button" data-create-page-close aria-label="Close new page form"><i data-lucide="x" class="size-5"></i></button>
                    </div>
                    <label><span class="field-label">Page title</span><input name="title" required placeholder="About our church" class="field-input"></label>
                    <label><span class="field-label">URL slug <span class="font-normal text-slate-400">(optional)</span></span><input name="slug" placeholder="about" class="field-input"></label>
                    <label><span class="field-label">Opening content</span><textarea name="body" rows="4" placeholder="Tell your story..." class="field-input"></textarea></label>
                    <div><span class="field-label">Sections</span><div class="grid gap-2 sm:grid-cols-2">@foreach ($sectionTypes as $key => $label)<label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" name="section_types[]" value="{{ $key }}" @checked(in_array($key, ['welcome', 'contact'], true)) class="rounded border-slate-300 text-violet-600">{{ $label }}</label>@endforeach</div></div>
                    <select name="status" class="field-input"><option value="draft">Save as draft</option><option value="published">Publish now</option></select>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Create page</button>
                </form>
                <div class="website-page-grid" data-page-grid>
                    @foreach ($pages as $page)
                        <form method="POST" action="{{ route('website-studio.pages.update', $page) }}" class="website-page-card rounded-2xl border border-slate-100 bg-white p-4 shadow-sm" data-page-card data-page-title="{{ strtolower($page->title) }}">
                            @csrf
                            @method('PUT')
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex min-w-0 items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $page->status === 'published' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}"><i data-lucide="{{ $page->slug === 'home' ? 'house' : 'file-text' }}" class="size-4"></i></span><div class="min-w-0"><input name="title" value="{{ $page->title }}" class="w-full border-0 bg-transparent p-0 text-sm font-bold text-slate-950 focus:ring-0"><div class="mt-1 text-xs text-slate-400">/site/{{ $church->slug }}/{{ $page->slug }}</div></div></div>
                                <div class="flex items-center gap-2"><a href="{{ route('website-studio.pages.edit', $page) }}" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-violet-600" title="Edit page"><i data-lucide="palette" class="size-4"></i></a><a href="{{ route('website-studio.preview', $page) }}" target="_blank" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-violet-600" title="Preview"><i data-lucide="eye" class="size-4"></i></a>@if ($page->slug !== 'home')<button type="submit" form="delete-page-{{ $page->id }}" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Archive"><i data-lucide="archive" class="size-4"></i></button>@endif</div>
                            </div>
                            <div class="website-page-card-fields"><input name="slug" value="{{ $page->slug }}" class="field-input"><select name="status" class="field-input"><option value="draft" @selected($page->status === 'draft')>Draft</option><option value="published" @selected($page->status === 'published')>Published</option></select></div>
                            <textarea name="body" rows="2" placeholder="Optional page introduction" class="field-input website-page-card-body">{{ $page->body }}</textarea>
                            <div class="website-page-card-footer"><span class="website-page-status {{ $page->status === 'published' ? 'is-published' : 'is-draft' }}">{{ ucfirst($page->status) }}</span><button class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100"><i data-lucide="save" class="size-3.5"></i>Save</button></div>
                            @foreach ($page->sections ?? [] as $section)<input type="hidden" name="section_types[]" value="{{ is_array($section) ? ($section['type'] ?? 'welcome') : $section }}">@endforeach
                        </form>
                        @if ($page->slug !== 'home')<form id="delete-page-{{ $page->id }}" method="POST" action="{{ route('website-studio.pages.destroy', $page) }}">@csrf @method('DELETE')</form>@endif
                    @endforeach
                </div>
            </div>
        </section>
        </div>
        <aside class="dashboard-card website-preview-card"><div class="flex items-center justify-between gap-3"><div><p class="website-studio-card-label">Website preview</p><h2>Live homepage preview</h2></div><span class="website-live-pill"><span></span>{{ ($settings['enabled'] ?? true) ? 'Live' : 'Draft' }}</span></div><div class="website-preview-frame"><iframe src="{{ $previewUrl }}" title="Website preview" loading="lazy"></iframe></div><a href="{{ $publicUrl }}" target="_blank" rel="noreferrer" class="website-preview-link">Open public website <span>↗</span></a></aside>
        </div>
    </div>

    <div class="website-modal-backdrop" data-studio-modal-backdrop hidden></div>
    <style>
        .field-label { display:block; margin-bottom:.4rem; font-size:.72rem; font-weight:700; color:#475569; }
        .field-input { display:block; width:100%; border-radius:.7rem; border:1px solid #e2e8f0; background:#fff; padding:.65rem .75rem; font-size:.875rem; color:#0f172a; outline:none; }
        .field-input:focus { border-color:var(--studio-primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--studio-primary) 14%,transparent); }
        .website-navigation-panel { border:1px solid color-mix(in srgb, var(--studio-primary, var(--brand-primary, #6d4aff)) 16%, transparent); background:linear-gradient(145deg,#fff 0%,color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 3%, #fff) 100%); }
        .website-navigation-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding-bottom:.15rem; }
        .website-navigation-heading h2 { margin:0; color:#101828; font-size:1rem; font-weight:800; }
        .website-navigation-heading p:not(.website-studio-card-label) { margin:.3rem 0 0; color:#667085; font-size:.76rem; line-height:1.45; }
        .website-navigation-icon { display:grid; width:2.15rem; height:2.15rem; flex:0 0 auto; place-items:center; border:1px solid color-mix(in srgb, var(--studio-primary, var(--brand-primary, #6d4aff)) 16%, transparent); border-radius:.7rem; background:color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 9%, #fff); color:var(--studio-primary, var(--brand-primary, #6941c6)); }
        .website-navigation-count { flex:0 0 auto; border:1px solid color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 25%, #fff); border-radius:999px; background:color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 5%, #fff); padding:.3rem .55rem; color:var(--studio-primary, var(--brand-primary, #6941c6)); font-size:.65rem; font-weight:800; white-space:nowrap; }
        .website-navigation-list { display:flex; flex-direction:column; gap:.55rem; }
        .website-navigation-row { display:grid; grid-template-columns:minmax(9rem,1fr) minmax(13rem,1.55fr) auto auto; align-items:end; gap:.65rem; padding:.65rem; border:1px solid #e4e7ec; border-radius:.75rem; background:#fff; transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease; }
        .website-navigation-row:hover { border-color:color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 38%, #fff); box-shadow:0 5px 16px rgb(15 23 42 / .06); transform:translateY(-1px); }
        .website-navigation-row .field-label { margin-bottom:.28rem; font-size:.65rem; text-transform:uppercase; letter-spacing:.04em; color:#98a2b3; }
        .website-navigation-row .field-input { min-height:2.35rem; padding:.5rem .65rem; font-size:.78rem; }
        .website-navigation-visible { display:inline-flex; min-height:2.35rem; align-items:center; gap:.4rem; padding:0 .2rem .05rem; color:#475569; font-size:.72rem; font-weight:750; white-space:nowrap; }
        .website-navigation-remove, .website-navigation-add { display:inline-flex; min-height:2.35rem; align-items:center; justify-content:center; gap:.35rem; border-radius:.6rem; padding:.5rem .65rem; font-size:.7rem; font-weight:800; transition:background .15s ease,color .15s ease,border-color .15s ease; }
        .website-navigation-remove { border:1px solid #fecdd3; background:#fff; color:#e11d48; }
        .website-navigation-remove:hover { background:#fff1f2; border-color:#fda4af; }
        .website-navigation-add { border:1px solid color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 25%, #fff); background:color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 9%, #fff); color:var(--studio-primary, var(--brand-primary, #6941c6)); }
        .website-navigation-add:hover { background:color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 14%, #fff); border-color:color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 38%, #fff); }
        .website-navigation-hint { display:inline-flex; align-items:center; gap:.35rem; margin:0; color:#98a2b3; font-size:.68rem; }
        .website-studio-overview { align-items:stretch; }
        .website-studio-overview .dashboard-card { border:1px solid #e6eaf2; border-radius:1rem; background:#fff; box-shadow:0 8px 24px rgb(15 23 42 / .04); }
        .website-status-card, .website-template-card { display:flex; gap:1rem; align-items:flex-start; padding:1.25rem; }
        .website-studio-card-icon { display:grid; width:2.5rem; height:2.5rem; flex:0 0 auto; place-items:center; border-radius:.8rem; }
        .website-studio-card-label { margin:0 0 .25rem; color:#98a2b3; font-size:.67rem; font-weight:800; letter-spacing:.11em; text-transform:uppercase; }
        .website-status-card h2, .website-template-card h2, .website-preview-card h2 { margin:0; color:#101828; font-size:1rem; font-weight:800; }
        .website-status-card p:not(.website-studio-card-label), .website-template-card p:not(.website-studio-card-label) { margin:.45rem 0 .7rem; color:#667085; font-size:.78rem; line-height:1.5; }
        .website-status-card a, .website-template-card a, .website-preview-link { color:var(--studio-primary); font-size:.78rem; font-weight:800; text-decoration:none; }
        .website-status-card a span, .website-template-card a span, .website-preview-link span { margin-left:.25rem; }
        .website-preview-card { padding:1rem; }
        .website-live-pill { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; background:#ecfdf3; padding:.3rem .55rem; color:#027a48; font-size:.68rem; font-weight:800; }
        .website-live-pill span { width:.4rem; height:.4rem; border-radius:50%; background:#12b76a; }
        .website-preview-frame { height:330px; margin:1rem 0 .8rem; overflow:hidden; border:1px solid #e4e7ec; border-radius:.8rem; background:#f8fafc; }
        .website-preview-frame iframe { width:100%; height:100%; border:0; transform-origin:top left; }
        .website-studio-shell { display:grid; grid-template-columns:minmax(0,1fr) 462px; gap:1.25rem; align-items:start; }
        .website-studio-main { min-width:0; display:flex; flex-direction:column; gap:1.25rem; }
        .website-studio-overview > div { grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr); }
        .website-preview-card { position:sticky; top:1rem; min-width:0; box-shadow:0 14px 40px rgb(15 23 42 / .08); }
        .website-preview-frame { height:720px; }
        .website-preview-frame iframe { width:1200px; height:2100px; transform:scale(.355); }
        .website-section-shortcuts { padding-top:.1rem; }
        .website-shortcut-heading { display:flex; align-items:end; justify-content:space-between; gap:1rem; margin-bottom:.75rem; }
        .website-shortcut-heading h2 { margin:0; color:#101828; font-size:.95rem; font-weight:800; }
        .website-shortcut-heading > span { color:#98a2b3; font-size:.72rem; font-weight:700; }
        .website-shortcut-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.7rem; }
        .website-shortcut-card { display:flex; gap:.75rem; min-width:0; border:1px solid #e4e7ec; border-radius:.8rem; background:#fff; padding:.85rem; color:inherit; text-decoration:none; transition:border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .website-shortcut-card:hover { border-color:color-mix(in srgb,var(--studio-primary) 38%,#fff); box-shadow:0 8px 24px color-mix(in srgb,var(--studio-primary) 10%,transparent); transform:translateY(-1px); }
        .website-shortcut-card > span { display:grid; width:2rem; height:2rem; flex:0 0 auto; place-items:center; border-radius:.55rem; background:color-mix(in srgb,var(--studio-primary) 9%,#fff); color:var(--studio-primary); }
        .website-shortcut-card h3 { margin:0; color:#101828; font-size:.78rem; font-weight:800; }
        .website-shortcut-card p { margin:.2rem 0 .5rem; color:#667085; font-size:.66rem; line-height:1.45; }
        .website-shortcut-card strong { color:var(--studio-primary); font-size:.66rem; }
        #template-settings { grid-template-columns:1fr; scroll-margin-top:1rem; }
        .website-edit-panel { display:none; }
        .website-edit-panel.is-open { position:fixed; inset:5vh auto auto 50%; z-index:1002; display:block; width:min(920px,calc(100vw - 2rem)); max-height:90vh; overflow:auto; transform:translateX(-50%); border:1px solid #e4e7ec; border-radius:1.1rem; background:#fff; padding:1.35rem; box-shadow:0 30px 90px rgb(15 23 42 / .28); }
        .website-editor-actions { position:sticky; bottom:-1.35rem; display:flex; justify-content:flex-end; gap:.65rem; margin:1.25rem -1.35rem -1.35rem; border-top:1px solid #eaecf0; background:rgb(255 255 255 / .96); padding:1rem 1.35rem; backdrop-filter:blur(12px); }
        .website-editor-actions button { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; border-radius:.7rem; padding:.65rem .95rem; font-size:.75rem; font-weight:800; }
        .website-editor-cancel { border:1px solid #d0d5dd; background:#fff; color:#344054; }
        .website-editor-save { border:1px solid var(--studio-primary); background:var(--studio-primary); color:#fff; box-shadow:0 5px 14px color-mix(in srgb,var(--studio-primary) 22%,transparent); }
        .website-modal-backdrop { position:fixed; inset:0; z-index:1000; background:rgb(15 23 42 / .62); backdrop-filter:blur(5px); }
        .website-modal-backdrop[hidden] { display:none !important; }
        .website-page-actions { display:flex; align-items:center; gap:.6rem; }
        .website-page-actions label { display:flex; align-items:center; gap:.45rem; min-width:190px; border:1px solid #e4e7ec; border-radius:.65rem; background:#fff; padding:.48rem .65rem; color:#98a2b3; }
        .website-page-actions input { min-width:0; width:100%; border:0; padding:0; color:#344054; font-size:.75rem; outline:none; box-shadow:none; }
        .website-page-actions a { display:inline-flex; align-items:center; gap:.4rem; border-radius:.65rem; background:var(--studio-primary); padding:.58rem .75rem; color:#fff; font-size:.72rem; font-weight:800; text-decoration:none; }
        .website-create-page { position:fixed; inset:5vh auto auto 50%; z-index:1002; display:none; width:min(850px,calc(100vw - 2rem)); max-height:90vh; overflow:auto; transform:translateX(-50%); border:1px solid #e4e7ec; border-radius:1.1rem; background:#fff; padding:1.35rem; box-shadow:0 30px 90px rgb(15 23 42 / .3); }
        .website-create-page.is-open { display:block; }
        .website-create-page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; border-bottom:1px solid #eaecf0; margin:-.15rem 0 .25rem; padding-bottom:1rem; }
        .website-create-page-header h3 { margin:0; color:#101828; font-size:1rem; font-weight:800; }
        .website-create-page-header span { display:block; margin-top:.3rem; color:#667085; font-size:.75rem; }
        .website-create-page-header button { display:grid; width:2.25rem; height:2.25rem; flex:0 0 auto; place-items:center; border:1px solid #e4e7ec; border-radius:.65rem; background:#fff; color:#475467; }
        .website-page-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.65rem; }
        .website-page-grid > form[id^="delete-page-"] { display:none !important; }
        .website-page-card { min-width:0; padding:.8rem !important; box-shadow:none !important; }
        .website-page-card:hover { border-color:color-mix(in srgb, var(--studio-primary, var(--brand-primary, #7c3aed)) 38%, #fff); box-shadow:0 8px 22px rgb(15 23 42 / .06) !important; }
        .website-page-card-fields, .website-page-card-body { position:absolute !important; width:1px !important; height:1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; }
        .website-page-card-footer { display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-top:.65rem; }
        .website-page-status { display:inline-flex; align-items:center; border-radius:999px; padding:.23rem .48rem; font-size:.62rem; font-weight:800; }
        .website-page-status.is-published { background:#ecfdf3; color:#027a48; }
        .website-page-status.is-draft { background:#fff7ed; color:#c2410c; }
        @media (max-width: 1350px) { .website-studio-shell { grid-template-columns:minmax(0,1fr) 396px; } .website-preview-frame iframe { transform:scale(.297); } }
        @media (max-width: 1100px) { .website-studio-shell { grid-template-columns:1fr; } .website-preview-card { position:relative; top:auto; } .website-preview-frame { height:520px; } .website-preview-frame iframe { transform:scale(.42); } }
        .hero-slide-manager { border:1px solid #e2e8f0; border-radius:1rem; background:#f8fafc; padding:1rem; }.hero-slide-manager-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.8rem; }.hero-slide-manager-head p { margin:.2rem 0 0; color:#64748b; font-size:.75rem; }.hero-slide-manager-head button { display:inline-flex; align-items:center; gap:.4rem; border:0; border-radius:.7rem; background:var(--studio-primary); padding:.65rem .85rem; color:#fff; font-size:.75rem; font-weight:700; }.hero-slide-list { display:grid; gap:.7rem; }.hero-slide-row { display:grid; grid-template-columns:2rem 7rem minmax(10rem,1fr) minmax(10rem,1fr) minmax(10rem,1fr) minmax(10rem,1fr) auto; align-items:end; gap:.65rem; border:1px solid #e2e8f0; border-radius:.85rem; background:#fff; padding:.75rem; }.hero-slide-number { display:grid; width:2rem; height:2.65rem; place-items:center; border-radius:.6rem; background:color-mix(in srgb,var(--studio-primary) 10%,#fff); color:var(--studio-primary); font-size:.75rem; font-weight:800; }.hero-slide-actions { display:flex; gap:.3rem; padding-bottom:.15rem; }.hero-slide-actions button { display:grid; width:2.25rem; height:2.25rem; place-items:center; border:1px solid #e2e8f0; border-radius:.6rem; background:#fff; color:#64748b; }.hero-slide-actions button:hover { border-color:var(--studio-primary); color:var(--studio-primary); }.hero-slide-actions button.is-danger:hover { border-color:#fecdd3; color:#e11d48; }
        @media (max-width: 1250px) { .hero-slide-row { grid-template-columns:2rem 7rem 1fr 1fr; }.hero-slide-row [data-poster-field] { grid-column:span 2; }.hero-slide-actions { grid-column:4; justify-content:flex-end; } }
        @media (max-width: 760px) { .website-shortcut-grid, .website-page-grid { grid-template-columns:1fr; } .website-studio-overview > div { grid-template-columns:1fr; } .website-page-actions { align-items:stretch; flex-direction:column; } .website-page-actions label { min-width:0; width:100%; } .website-preview-card { display:none; } .website-edit-panel.is-open, .website-create-page.is-open { inset:1rem auto auto 50%; width:calc(100vw - 2rem); max-height:calc(100vh - 2rem); padding:1rem; } .website-editor-actions { bottom:-1rem; margin:1rem -1rem -1rem; padding:.85rem 1rem; } .website-navigation-heading { gap:.65rem; } .website-navigation-count { font-size:.6rem; } .website-navigation-row { grid-template-columns:1fr 1fr; } .website-navigation-row > label:nth-child(2) { grid-column:1 / -1; } .website-navigation-visible { min-height:2.2rem; } .website-navigation-remove { min-height:2.2rem; } .hero-slide-manager-head { align-items:flex-start; flex-direction:column; }.hero-slide-row { grid-template-columns:1fr; }.hero-slide-number,.hero-slide-row [data-poster-field],.hero-slide-actions { grid-column:1; }.hero-slide-actions { justify-content:flex-end; } }
        @media (max-width: 430px) { .website-navigation-heading { flex-direction:column; } .website-navigation-row { grid-template-columns:1fr; } .website-navigation-row > label:nth-child(2) { grid-column:auto; } .website-navigation-visible, .website-navigation-remove { width:100%; } .website-navigation-hint { max-width:15rem; } }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const heroManager = document.querySelector('[data-hero-slide-manager]');
            if (heroManager) {
                const heroList = heroManager.querySelector('[data-hero-slide-list]');
                const heroTemplate = heroManager.querySelector('[data-hero-slide-template]');
                let heroSequence = Date.now();
                const refreshHeroSlides = function () {
                    const rows = Array.from(heroList.querySelectorAll('[data-hero-slide-row]'));
                    rows.forEach(function (row, index) {
                        row.querySelector('.hero-slide-number').textContent = String(index + 1);
                        const isVideo = row.querySelector('[data-hero-field="type"]').value === 'video';
                        row.querySelectorAll('[data-poster-field]').forEach(function (field) { field.hidden = !isVideo; });
                    });
                };
                heroManager.addEventListener('input', refreshHeroSlides);
                heroManager.addEventListener('change', refreshHeroSlides);
                heroManager.addEventListener('click', function (event) {
                    const row = event.target.closest('[data-hero-slide-row]');
                    if (event.target.closest('[data-add-hero-slide]')) {
                        if (heroList.children.length >= 12) return;
                        const key = `slide-${heroSequence++}`;
                        heroList.insertAdjacentHTML('beforeend', heroTemplate.innerHTML.replaceAll('__KEY__', key));
                        if (window.lucide) window.lucide.createIcons();
                    } else if (event.target.closest('[data-remove-hero-slide]')) {
                        row?.remove();
                    } else if (event.target.closest('[data-hero-up]') && row?.previousElementSibling) {
                        heroList.insertBefore(row, row.previousElementSibling);
                    } else if (event.target.closest('[data-hero-down]') && row?.nextElementSibling) {
                        heroList.insertBefore(row.nextElementSibling, row);
                    }
                    refreshHeroSlides();
                });
                heroManager.closest('form')?.addEventListener('submit', refreshHeroSlides);
                refreshHeroSlides();
            }
            const search = document.querySelector('[data-page-search]');
            const backdrop = document.querySelector('[data-studio-modal-backdrop]');
            const createModal = document.querySelector('[data-create-page-modal]');
            let activeSection = null;

            function lockPage() {
                backdrop.hidden = false;
                document.documentElement.style.overflow = 'hidden';
            }

            function unlockPage() {
                if (activeSection || createModal.classList.contains('is-open')) return;
                backdrop.hidden = true;
                document.documentElement.style.overflow = '';
            }

            function closeSection() {
                if (!activeSection) return;
                activeSection.classList.remove('is-open');
                activeSection = null;
                unlockPage();
            }

            function closeCreatePage() {
                if (!createModal.classList.contains('is-open')) return;
                createModal.classList.remove('is-open');
                unlockPage();
            }

            document.querySelectorAll('.website-edit-panel').forEach(function (panel) {
                const actions = document.createElement('div');
                actions.className = 'website-editor-actions';
                actions.innerHTML = '<button type="button" class="website-editor-cancel" data-editor-close>Cancel</button><button type="submit" class="website-editor-save"><span>Save changes</span></button>';
                panel.appendChild(actions);
            });

            document.querySelectorAll('.website-shortcut-card').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    const panel = document.querySelector(link.getAttribute('href'));
                    if (!panel) return;
                    event.preventDefault();
                    closeCreatePage();
                    closeSection();
                    activeSection = panel;
                    panel.classList.add('is-open');
                    lockPage();
                    const firstField = panel.querySelector('input:not([type="hidden"]), textarea, select');
                    if (firstField) window.setTimeout(function () { firstField.focus(); }, 80);
                });
            });

            document.addEventListener('click', function (event) {
                if (event.target.closest('[data-editor-close]')) closeSection();
            });

            document.querySelector('[data-create-page-open]').addEventListener('click', function (event) {
                event.preventDefault();
                closeSection();
                createModal.classList.add('is-open');
                lockPage();
                window.setTimeout(function () { createModal.querySelector('input[name="title"]').focus(); }, 80);
            });

            document.querySelector('[data-create-page-close]').addEventListener('click', closeCreatePage);
            backdrop.addEventListener('click', function () {
                closeSection();
                closeCreatePage();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') return;
                closeSection();
                closeCreatePage();
            });

            if (search) {
                search.addEventListener('input', function () {
                    const query = search.value.trim().toLowerCase();
                    document.querySelectorAll('[data-page-card]').forEach(function (card) {
                        card.hidden = query !== '' && !card.dataset.pageTitle.includes(query);
                    });
                });
            }
        });
    </script>
</x-app-layout>
