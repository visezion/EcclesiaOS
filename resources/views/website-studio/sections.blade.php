<x-app-layout title="Reusable Sections" :breadcrumbs="$breadcrumbs">
    <link rel="stylesheet" href="{{ asset('css/website-studio/section-builder.css') }}?v={{ filemtime(public_path('css/website-studio/section-builder.css')) }}">
    <div class="mx-auto max-w-[1500px] space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><div class="mb-2 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-violet-700"><i data-lucide="blocks" class="size-3.5"></i>Website Studio · Reusable sections</div><h1 class="text-3xl font-semibold tracking-tight text-slate-950">Create and reuse sections</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Build a section once, assign it to one or many pages, and update it from one place.</p></div><a href="{{ route('website-studio.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700">Back to Website Studio</a></div>
        @if (session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
        @if ($sections->isNotEmpty())
            <section class="empty-sections dashboard-card overflow-hidden">
                <div class="empty-sections-glow"></div>
                <div class="relative grid items-center gap-8 px-6 py-12 lg:grid-cols-[1fr_360px] lg:px-12 lg:py-16">
                    <div><span class="empty-icon">✦</span><p class="mt-6 text-xs font-black uppercase tracking-[.18em] text-violet-600">Your section library</p><h2 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Build beautiful pages, one reusable section at a time.</h2><p class="mt-4 max-w-xl text-sm leading-7 text-slate-500">Create a custom layout with columns, nested sub-columns, text, images, videos, buttons, and more. Then place it on any page from one simple editor.</p><a href="{{ route('website-studio.sections.create') }}" class="mt-7 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 hover:bg-violet-700">Create another section <span>→</span></a><span class="empty-library-count">✦ {{ $sections->count() }} available · Reusable sections</span></div>
                    <div class="empty-preview"><div class="empty-preview-bar"><span></span><span></span><span></span></div><div class="empty-preview-hero"></div><div class="empty-preview-lines"><i></i><i></i><i></i></div><div class="empty-preview-grid"><b></b><b></b><b></b></div></div>
                </div>
            </section>
            <section class="dashboard-card">
                <div class="mb-4 flex items-center justify-between"><div><h2 class="text-lg font-bold text-slate-950">Created sections</h2><p class="mt-1 text-sm text-slate-500">Open a section to edit its layout, widgets, columns, and media.</p></div><span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">{{ $sections->count() }} available</span></div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach ($sections as $section)<a href="{{ route('website-studio.sections.edit', $section['id']) }}" class="group rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-violet-300 hover:bg-violet-50"><div class="flex items-center justify-between gap-3"><strong class="text-sm text-slate-900">{{ $section['title'] }}</strong><span class="text-violet-600 transition group-hover:translate-x-1">→</span></div><p class="mt-2 text-xs text-slate-500">{{ collect($section['page_slugs'] ?? [])->count() }} page(s) · Open editor</p></a>@endforeach</div>
            </section>
        @else
            <section class="empty-sections dashboard-card overflow-hidden">
                <div class="empty-sections-glow"></div>
                <div class="relative grid items-center gap-8 px-6 py-12 lg:grid-cols-[1fr_360px] lg:px-12 lg:py-16">
                    <div><span class="empty-icon">✦</span><p class="mt-6 text-xs font-black uppercase tracking-[.18em] text-violet-600">Your section library</p><h2 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Build beautiful pages, one reusable section at a time.</h2><p class="mt-4 max-w-xl text-sm leading-7 text-slate-500">Create a custom layout with columns, nested sub-columns, text, images, videos, buttons, and more. Then place it on any page from one simple editor.</p><a href="{{ route('website-studio.sections.create') }}" class="mt-7 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 hover:bg-violet-700">Create your first section <span>→</span></a></div>
                    <div class="empty-preview"><div class="empty-preview-bar"><span></span><span></span><span></span></div><div class="empty-preview-hero"></div><div class="empty-preview-lines"><i></i><i></i><i></i></div><div class="empty-preview-grid"><b></b><b></b><b></b></div></div>
                </div>
            </section>
        @endif
        <div class="grid gap-5 xl:grid-cols-[390px_1fr]">
            <section class="dashboard-card h-fit space-y-4"><div><h2 class="text-lg font-bold text-slate-950">New section</h2><p class="helper">Add text, media, a button, and choose where it appears.</p></div><form method="POST" action="{{ route('website-studio.sections.store') }}" enctype="multipart/form-data" class="space-y-3">@csrf
                <label><span class="field-label">Section title</span><input name="title" required class="field-input" placeholder="A place to belong"></label><label><span class="field-label">Small label <span class="optional">(optional)</span></span><input name="eyebrow" class="field-input" placeholder="Your next step"></label><label><span class="field-label">Text <span class="optional">(optional)</span></span><textarea name="body" rows="5" class="field-input" placeholder="Write the message visitors should see..."></textarea></label><div class="builder-shell" data-builder><div class="mb-2 text-xs font-bold text-slate-700">Add content widgets</div><div class="widget-toolbar">@foreach (['heading' => 'Heading', 'text' => 'Text', 'quote' => 'Quote', 'image' => 'Image', 'video' => 'Video', 'button' => 'Button', 'spacer' => 'Spacer'] as $type => $label)<button type="button" class="widget-add" data-add-widget="{{ $type }}">+ {{ $label }}</button>@endforeach</div><div class="widget-list" data-widget-list></div><input type="hidden" name="components" data-components-output><script type="application/json" data-components-seed>[]</script></div><div class="grid gap-3 sm:grid-cols-2"><label><span class="field-label">Button text</span><input name="button_label" class="field-input" placeholder="Learn more"></label><label><span class="field-label">Button link</span><input name="button_url" class="field-input" placeholder="/contact or https://..."></label></div><label><span class="field-label">Image URL <span class="optional">(optional)</span></span><input name="image_url" class="field-input" placeholder="https://... or website/photo.jpg"></label><label><span class="field-label">Upload image <span class="optional">(optional)</span></span><input type="file" name="image_file" accept="image/*" class="field-input"></label><label><span class="field-label">Video URL <span class="optional">(optional)</span></span><input name="video_url" class="field-input" placeholder="https://... or website/video.mp4"></label><label><span class="field-label">Upload video <span class="optional">(optional)</span></span><input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg" class="field-input"></label><div><span class="field-label">Show this section on</span><div class="space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-3">@foreach ($pages as $page)<label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="page_slugs[]" value="{{ $page->slug }}" @checked($page->slug === 'home') class="rounded border-slate-300 text-violet-600">{{ $page->title }}</label>@endforeach</div></div><button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Create section</button>
            </form></section>
            <section class="space-y-4"><div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-950">Your reusable sections</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $sections->count() }} sections</span></div>@forelse ($sections as $section)<form method="POST" action="{{ route('website-studio.sections.update', $section['id']) }}" enctype="multipart/form-data" class="dashboard-card space-y-4">@csrf @method('PUT')<div class="flex items-start justify-between gap-3"><div><h3 class="text-base font-bold text-slate-950">{{ $section['title'] }}</h3><p class="mt-1 text-xs text-slate-500">Shown on: {{ collect($section['page_slugs'] ?? [])->join(', ') }}</p></div><button type="submit" form="delete-section-{{ $section['id'] }}" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Delete section"><i data-lucide="trash-2" class="size-4"></i></button></div><div class="grid gap-3 md:grid-cols-2"><label><span class="field-label">Title</span><input name="title" value="{{ $section['title'] }}" required class="field-input"></label><label><span class="field-label">Small label</span><input name="eyebrow" value="{{ $section['eyebrow'] ?? '' }}" class="field-input"></label><label class="md:col-span-2"><span class="field-label">Text</span><textarea name="body" rows="3" class="field-input">{{ $section['body'] ?? '' }}</textarea></label><label><span class="field-label">Button text</span><input name="button_label" value="{{ $section['button_label'] ?? '' }}" class="field-input"></label><label><span class="field-label">Button link</span><input name="button_url" value="{{ $section['button_url'] ?? '' }}" class="field-input"></label><label><span class="field-label">Image URL</span><input name="image_url" value="{{ $section['image_url'] ?? '' }}" class="field-input"></label><label><span class="field-label">Replace image</span><input type="file" name="image_file" accept="image/*" class="field-input"></label><label><span class="field-label">Video URL</span><input name="video_url" value="{{ $section['video_url'] ?? '' }}" class="field-input"></label><label><span class="field-label">Replace video</span><input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg" class="field-input"></label></div><div><span class="field-label">Show this section on</span><div class="grid gap-2 sm:grid-cols-3">@foreach ($pages as $page)<label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" name="page_slugs[]" value="{{ $page->slug }}" @checked(in_array($page->slug, $section['page_slugs'] ?? [], true)) class="rounded border-slate-300 text-violet-600">{{ $page->title }}</label>@endforeach</div></div><button class="inline-flex items-center gap-2 rounded-lg bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100"><i data-lucide="save" class="size-3.5"></i>Save section</button></form><form id="delete-section-{{ $section['id'] }}" method="POST" action="{{ route('website-studio.sections.destroy', $section['id']) }}">@csrf @method('DELETE')</form>@empty<div class="dashboard-card p-12 text-center"><i data-lucide="blocks" class="mx-auto size-8 text-slate-300"></i><h3 class="mt-3 font-bold text-slate-900">No reusable sections yet</h3><p class="mt-1 text-sm text-slate-500">Create your first section from the form.</p></div>@endforelse</section>
        </div>
    </div>
    <style>.field-label{display:block;margin-bottom:.35rem;font-size:.72rem;font-weight:700;color:#475569}.field-input{display:block;width:100%;border-radius:.7rem;border:1px solid #e2e8f0;background:#fff;padding:.65rem .75rem;font-size:.875rem;color:#0f172a;outline:none}.field-input:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgb(139 92 246 / .12)}.helper{font-size:.8rem;line-height:1.5;color:#64748b}.optional{font-weight:400;color:#94a3b8}</style>
    <style>
        .dashboard-card{border:1px solid #e5e7eb;border-radius:1rem;background:#fff;box-shadow:0 10px 30px rgb(15 23 42 / .04)}
        .created-sections-grid{grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
        [data-new-section-link]{box-shadow:0 10px 20px rgb(109 40 217 / .18)}
        .created-sections-grid>a{position:relative;min-height:112px;overflow:hidden;border-radius:1rem!important;background:linear-gradient(135deg,#fff 0%,#fafaff 100%)!important;padding:1.15rem!important;box-shadow:0 4px 14px rgb(15 23 42 / .03)}
        .created-sections-grid>a:before{position:absolute;top:0;bottom:0;left:0;width:4px;background:linear-gradient(#7c3aed,#4f46e5);content:''}
        .created-sections-grid>a strong{font-size:.9rem}
        .section-table-wrap{overflow-x:auto;border:1px solid #e5e7eb;border-radius:1rem;background:#fff;box-shadow:0 10px 30px rgb(15 23 42 / .04)}
        .section-table{width:100%;min-width:720px;border-collapse:collapse;text-align:left}
        .section-table th{background:#f8fafc;padding:1rem 1.25rem;color:#64748b;font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
        .section-table td{border-top:1px solid #f1f5f9;padding:1rem 1.25rem;color:#64748b;font-size:.8rem}
        .section-table tr:hover td{background:#fafaff}
        .table-section-name{display:flex;align-items:center;gap:.7rem;color:#0f172a}
        .table-section-name strong{font-size:.9rem}
        .table-section-icon{display:grid;place-items:center;width:2rem;height:2rem;border-radius:.65rem;background:#ede9fe;color:#7c3aed;font-size:.9rem}
        .section-table small{display:block;margin:4px 0 0 2.7rem;color:#94a3b8;font-size:.7rem}
        .table-status{display:inline-flex;border-radius:999px;background:#ecfdf5;padding:.3rem .6rem;color:#047857;font-size:.68rem;font-weight:800}
        .table-edit{color:#6d28d9;font-weight:800;white-space:nowrap}.table-edit:hover{color:#4c1d95}
        .table-actions{display:inline-flex;align-items:center;justify-content:flex-end;gap:1rem}.table-delete{color:#e11d48;font-size:.78rem;font-weight:800}.table-delete:hover{text-decoration:underline}
        .section-view-controls{display:inline-flex;gap:.25rem;margin-left:auto;border:1px solid #e5e7eb;border-radius:.7rem;background:#f8fafc;padding:.2rem}.section-view-controls button{border-radius:.5rem;padding:.45rem .65rem;color:#64748b;font-size:.7rem;font-weight:800}.section-view-controls button.is-active{background:#fff;color:#6d28d9;box-shadow:0 2px 8px rgb(15 23 42 / .08)}.section-card-view{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.75rem;margin-top:1rem}.section-library-card{display:flex;align-items:center;gap:.75rem;border:1px solid #e5e7eb;border-radius:1rem;background:linear-gradient(135deg,#fff,#fafaff);padding:1rem;transition:.2s}.section-library-card:hover{border-color:#c4b5fd;box-shadow:0 8px 22px rgb(109 40 217 / .1);transform:translateY(-2px)}.library-card-icon{display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:.75rem;background:#ede9fe;color:#7c3aed;font-size:1.1rem}.section-library-card strong{display:block;color:#0f172a;font-size:.85rem}.section-library-card small{display:block;margin-top:.25rem;color:#94a3b8;font-size:.7rem}.library-card-arrow{margin-left:auto;color:#7c3aed;font-size:1.1rem}
        .empty-sections{position:relative;background:radial-gradient(circle at 88% 42%,rgb(237 233 254 / .75),transparent 35%),linear-gradient(120deg,#fff,#fafaff)}.empty-sections-glow{position:absolute;top:-9rem;right:-5rem;width:24rem;height:24rem;border-radius:50%;background:radial-gradient(circle,rgb(124 58 237 / .16),transparent 68%)}.empty-icon{display:grid;place-items:center;width:3rem;height:3rem;border-radius:1rem;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;font-size:1.3rem;box-shadow:0 12px 25px rgb(109 40 217 / .22)}.empty-preview{position:relative;border:1px solid #ddd6fe;border-radius:1.25rem;background:rgb(255 255 255 / .94);padding:1rem;box-shadow:0 18px 35px rgb(76 29 149 / .12);transform:rotate(1deg)}.empty-preview:after{position:absolute;top:31%;left:18%;right:18%;z-index:2;border:1px solid #ddd6fe;border-radius:.75rem;background:#fff;padding:.85rem;color:#6366f1;content:'Text    Image    Video    Button    Columns';font-size:.72rem;font-weight:800;text-align:center;box-shadow:0 10px 24px rgb(76 29 149 / .12)}.empty-preview-bar{display:flex;gap:.3rem;border-bottom:1px solid #ede9fe;padding-bottom:.7rem}.empty-preview-bar span{width:.4rem;height:.4rem;border-radius:50%;background:#c4b5fd}.empty-preview-hero{height:5rem;margin-top:.8rem;border-radius:.7rem;background:linear-gradient(135deg,#4f46e5,#8b5cf6)}.empty-preview-lines{display:grid;gap:.45rem;margin-top:1rem}.empty-preview-lines i{display:block;height:.35rem;border-radius:999px;background:#ddd6fe}.empty-preview-lines i:nth-child(2){width:70%}.empty-preview-lines i:nth-child(3){width:48%}.empty-preview-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-top:1rem}.empty-preview-grid b{height:3.5rem;border-radius:.6rem;background:#ede9fe}
        .empty-sections .relative{grid-template-columns:minmax(0,1fr) 720px}.empty-sections .empty-preview{width:100%;min-height:430px;margin-left:auto}.empty-sections .empty-preview-hero{height:10rem}.empty-sections .empty-preview-grid b{height:7rem}.empty-sections .relative>div:first-child:after{display:inline-flex;margin-top:2rem;width:max-content;border:1px solid #ede9fe;border-radius:.8rem;background:#fff;padding:.75rem 1rem;color:#64748b;content:'✦  0 available  ·  Reusable sections';font-size:.72rem;font-weight:800;box-shadow:0 8px 20px rgb(15 23 42 / .05)}
        .empty-library-count{display:inline-flex;margin-top:2rem;width:max-content;border:1px solid #ede9fe;border-radius:.8rem;background:#fff;padding:.75rem 1rem;color:#64748b;font-size:.72rem;font-weight:800;box-shadow:0 8px 20px rgb(15 23 42 / .05)}.empty-sections:has(.empty-library-count) .relative>div:first-child:after{display:none}
    </style>
    <script id="section-components-map" type="application/json">@json($sections->mapWithKeys(fn ($section) => [$section['id'] => ['components' => $section['components'] ?? [], 'column_widths' => $section['column_widths'] ?? []]]))</script><script src="{{ asset('js/website-studio/section-builder.js') }}?v={{ filemtime(public_path('js/website-studio/section-builder.js')) }}" defer></script>
    <script>
        const legacyGrid = [...document.querySelectorAll('.grid.gap-5')].find((element) => element.className.includes('xl:grid-cols'));
        if (legacyGrid) legacyGrid.style.display = 'none';
        document.querySelectorAll('a[href*="/website-studio/sections/"][href$="/edit"]').forEach((link) => link.closest('.grid')?.classList.add('created-sections-grid'));
        const sectionGrid = [...document.querySelectorAll('.created-sections-grid')].find((grid) => grid.querySelector('a[href$="/edit"]'));
        if (sectionGrid) {
            const table = document.createElement('div');
            table.className = 'section-table-wrap';
            table.innerHTML = '<table class="section-table"><thead><tr><th>Section</th><th>Pages using it</th><th>Layout</th><th class="text-right">Action</th></tr></thead><tbody></tbody></table>';
            const body = table.querySelector('tbody');
            sectionGrid.querySelectorAll('a[href$="/edit"]').forEach((card) => {
                const title = card.querySelector('strong')?.textContent?.trim() || 'Untitled section';
                const meta = card.querySelector('p')?.textContent?.trim() || 'Reusable section';
                const row = document.createElement('tr');
                row.innerHTML = `<td><div class="table-section-name"><span class="table-section-icon">✦</span><strong>${title}</strong></div><small>Reusable website content</small></td><td>${meta.replace(' · Open editor', '')}</td><td><span class="table-status">Ready to use</span></td><td class="text-right"><div class="table-actions"><a class="table-edit" href="${card.href}">Open editor <span>→</span></a></div></td>`;
                const actions = row.querySelector('.table-actions');
                const deleteForm = document.createElement('form');
                deleteForm.method = 'POST';
                deleteForm.action = card.href.replace(/\/edit$/, '');
                deleteForm.innerHTML = `<input type="hidden" name="_token" value="${document.querySelector('input[name=_token]')?.value || ''}"><input type="hidden" name="_method" value="DELETE"><button class="table-delete" type="submit">Delete</button>`;
                deleteForm.addEventListener('submit', (event) => { if (!window.confirm(`Delete "${title}"? This cannot be undone.`)) event.preventDefault(); });
                actions.appendChild(deleteForm);
                body.appendChild(row);
            });
            sectionGrid.replaceWith(table);
            const panel = table.closest('section.dashboard-card');
            const header = panel?.querySelector(':scope > div:first-child');
            const controls = document.createElement('div');
            controls.className = 'section-view-controls';
            controls.innerHTML = '<button type="button" data-view="cards">▦ Cards</button><button type="button" data-view="table">☷ List</button>';
            header?.appendChild(controls);
            const cards = document.createElement('div');
            cards.className = 'section-card-view';
            table.querySelectorAll('tbody tr').forEach((row) => {
                const title = row.querySelector('.table-section-name strong')?.textContent || 'Untitled section';
                const pages = row.cells[1]?.textContent?.trim() || 'No pages assigned';
                const href = row.querySelector('.table-edit')?.href || '#';
                cards.insertAdjacentHTML('beforeend', `<a href="${href}" class="section-library-card"><span class="library-card-icon">✦</span><span><strong>${title}</strong><small>${pages} · Reusable section</small></span><span class="library-card-arrow">→</span></a>`);
            });
            table.parentNode.insertBefore(cards, table);
            table.style.display = 'none';
            controls.querySelector('[data-view="cards"]').classList.add('is-active');
            controls.querySelector('[data-view="cards"]').addEventListener('click', () => { cards.style.display = 'grid'; table.style.display = 'none'; controls.querySelector('[data-view="cards"]').classList.add('is-active'); controls.querySelector('[data-view="table"]').classList.remove('is-active'); });
            controls.querySelector('[data-view="table"]').addEventListener('click', () => { cards.style.display = 'none'; table.style.display = 'block'; controls.querySelector('[data-view="table"]').classList.add('is-active'); controls.querySelector('[data-view="cards"]').classList.remove('is-active'); });
        }
        const pageHeader = document.querySelector('h1')?.parentElement?.parentElement;
        if (pageHeader && !pageHeader.querySelector('[data-new-section-link]')) {
            const link = document.createElement('a');
            link.href = '{{ route('website-studio.sections.create') }}';
            link.textContent = '+ New section';
            link.dataset.newSectionLink = '';
            link.className = 'rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 hover:bg-violet-700';
            pageHeader.appendChild(link);
            const mediaLink = document.createElement('a');
            mediaLink.href = '{{ route('website-studio.media') }}';
            mediaLink.textContent = 'Media library';
            mediaLink.className = 'rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-bold text-violet-700 hover:bg-violet-100';
            pageHeader.appendChild(mediaLink);
        }
        document.querySelectorAll('form[action*="/website-studio/sections/"]').forEach((form) => {
            if (form.querySelector('input[name="_method"][value="DELETE"]')) return;
            const link = document.createElement('a');
            link.href = `${form.action}/edit`;
            link.textContent = 'Open full editor';
            link.className = 'mb-3 inline-flex rounded-lg bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100';
            form.prepend(link);
        });
    </script>
</x-app-layout>
