@php
    $design = $page->design ?? [];
    $allSections = [
        'hero' => ['label' => 'Welcome hero', 'description' => 'The opening message and media.'],
        'welcome' => ['label' => 'Welcome message', 'description' => 'Your church introduction.'],
        'services' => ['label' => 'Service times', 'description' => 'Gathering times and service details.'],
        'events' => ['label' => 'Upcoming events', 'description' => 'Live events from EcclesiaOS.'],
        'ministries' => ['label' => 'Ministries', 'description' => 'Ministries from your church records.'],
        'locations' => ['label' => 'Locations', 'description' => 'Campuses and location information.'],
        'sermons' => ['label' => 'Sermons & media', 'description' => 'Published messages and videos.'],
        'store' => ['label' => 'Church store', 'description' => 'Available bookstore products.'],
        'giving' => ['label' => 'Giving call-to-action', 'description' => 'A clear path to give online.'],
        'contact' => ['label' => 'Contact & visit', 'description' => 'Address, email, phone, and next steps.'],
    ];
    $customSectionMap = $customSections->keyBy(fn ($section) => (string) ($section['id'] ?? ''));
    $storedPageSections = collect($page->sections ?? [])->map(fn ($section) => is_array($section) ? ($section['type'] ?? null) : $section)->filter()->map(fn ($section) => (string) $section)->filter(fn ($section) => array_key_exists($section, $allSections) || $customSectionMap->has($section))->values();
    $assignedCustomIds = $customSectionMap->filter(fn ($section) => in_array($page->slug, $section['page_slugs'] ?? ['home'], true))->keys()->map(fn ($id) => (string) $id)->values();
    $activeSections = $storedPageSections->filter(fn ($section) => array_key_exists($section, $allSections) || $customSectionMap->has($section))->values()->all();
    $activeSections = array_values(array_unique(array_merge($activeSections, $assignedCustomIds->all())));
    $orderedSections = array_values(array_unique(array_merge($storedPageSections->all(), array_keys($allSections), $customSectionMap->keys()->map(fn ($id) => (string) $id)->all())));
@endphp
<x-app-layout title="Edit {{ $page->title }}" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><div class="mb-2 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-violet-700"><i data-lucide="layers-3" class="size-3.5"></i>Step 4 · Edit page</div><h1 class="text-2xl font-semibold text-slate-950">{{ $page->title }}</h1><p class="mt-1 text-sm text-slate-500">Choose the sections you need, move them into the right order, then save and preview.</p></div><div class="flex gap-2"><a href="{{ route('website-studio.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-600">All pages</a><a href="{{ route('website-studio.preview', $page) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="eye" class="size-4"></i>Preview</a></div></div>
        @if (session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
        <div class="grid gap-3 md:grid-cols-4"><div class="step-card active"><span>1</span><strong>Page details</strong><small>Name, URL, SEO, publish state.</small></div><div class="step-card active"><span>2</span><strong>Page design</strong><small>Hero text, colors, and image.</small></div><div class="step-card active"><span>3</span><strong>Page sections</strong><small>Add, remove, and reorder.</small></div><div class="step-card"><span>4</span><strong>Save & preview</strong><small>Publish when everything looks right.</small></div></div>

        <form method="POST" action="{{ route('website-studio.pages.update', $page) }}" enctype="multipart/form-data" class="space-y-5">
            @csrf @method('PUT')
            <section class="dashboard-card space-y-5"><div><h2 class="card-title">1. Page details</h2><p class="helper">Control the page name, address, search preview, and publishing state.</p></div><div class="grid gap-4 md:grid-cols-2"><label><span class="field-label">Page title</span><input name="title" value="{{ old('title', $page->title) }}" required class="field-input"></label><label><span class="field-label">URL slug</span><input name="slug" value="{{ old('slug', $page->slug) }}" required class="field-input"></label><label><span class="field-label">Publishing state</span><select name="status" class="field-input"><option value="draft" @selected($page->status === 'draft')>Draft — visitors cannot see it</option><option value="published" @selected($page->status === 'published')>Published — visible publicly</option></select></label><label><span class="field-label">SEO title <span class="optional">(optional)</span></span><input name="seo_title" value="{{ old('seo_title', $page->seo_title) }}" class="field-input"></label><label class="md:col-span-2"><span class="field-label">SEO description <span class="optional">(optional)</span></span><textarea name="seo_description" rows="2" class="field-input">{{ old('seo_description', $page->seo_description) }}</textarea></label></div></section>
            <section class="dashboard-card space-y-5"><div><h2 class="card-title">2. Page design</h2><p class="helper">Customize this page's hero without changing the whole church website.</p></div><div class="grid gap-4 md:grid-cols-3"><label><span class="field-label">Template</span><select name="page_template" class="field-input"><option value="inherit" @selected(! in_array(($design['template'] ?? null), array_keys($templates), true))>Use site default ({{ $settings['template'] }})</option>@foreach ($templates as $key => $label)<option value="{{ $key }}" @selected(($design['template'] ?? null) === $key)>{{ $label }}</option>@endforeach</select></label><label><span class="field-label">Page primary color <span class="optional">(optional)</span></span><input type="color" name="page_primary_color" value="{{ $design['primary_color'] ?? $settings['primary_color'] }}" class="color-input"></label><label><span class="field-label">Page accent color <span class="optional">(optional)</span></span><input type="color" name="page_accent_color" value="{{ $design['accent_color'] ?? $settings['accent_color'] }}" class="color-input"></label></div><div class="grid gap-4 md:grid-cols-2"><label><span class="field-label">Hero eyebrow</span><input name="page_hero_eyebrow" value="{{ old('page_hero_eyebrow', $design['hero_eyebrow'] ?? '') }}" placeholder="In this community" class="field-input"></label><label><span class="field-label">Hero heading</span><input name="page_hero_heading" value="{{ old('page_hero_heading', $design['hero_heading'] ?? '') }}" placeholder="Leave blank to use the page title" class="field-input"></label><label class="md:col-span-2"><span class="field-label">Hero message</span><textarea name="page_hero_body" rows="3" class="field-input" placeholder="A short introduction for this page">{{ old('page_hero_body', $design['hero_body'] ?? '') }}</textarea></label><label><span class="field-label">Hero image URL or storage path <span class="optional">(optional)</span></span><input name="page_hero_image_url" value="{{ old('page_hero_image_url', $design['hero_image_url'] ?? '') }}" placeholder="https://... or website/page-hero.jpg" class="field-input"></label><label><span class="field-label">Upload page hero image <span class="optional">(optional)</span></span><input type="file" name="page_hero_image_file" accept="image/*" class="field-input"></label></div></section>
            <section class="dashboard-card space-y-5"><div><h2 class="card-title">3. Page introduction</h2><p class="helper">Add the main written introduction visitors should read on this page.</p></div><textarea name="body" rows="6" class="field-input" placeholder="Tell people what this page is about...">{{ old('body', $page->body) }}</textarea></section>
            <section class="dashboard-card section-manager"><div class="section-manager-head"><div><span class="section-manager-kicker"><i data-lucide="grip-vertical" class="size-3.5"></i>Drag-and-drop page builder</span><h2 class="card-title mt-2">4. Page sections</h2><p class="helper">Switch sections on or off, then drag the handle to arrange the exact order visitors will see.</p></div><div class="section-manager-summary"><span data-section-count></span><small>Changes appear after you save</small></div></div><div class="section-drop-note"><i data-lucide="mouse-pointer-2" class="size-4"></i><span>Grab <strong>⋮⋮</strong> and drop a section anywhere in the list. Arrow buttons remain available for precise movement.</span></div><div id="section-list" class="section-list">@foreach ($orderedSections as $key) @php($isCustomSection = $customSectionMap->has($key)) @php($section = $isCustomSection ? $customSectionMap->get($key) : ($allSections[$key] ?? ['label' => $key, 'description' => '']))<div class="section-row {{ in_array($key, $activeSections, true) ? 'is-active' : '' }}" data-section-row data-section-key="{{ $key }}"><input type="hidden" name="section_order[]" value="{{ $key }}"><button type="button" class="section-drag-handle" draggable="true" title="Drag to reorder" aria-label="Drag {{ $section['title'] ?? $section['label'] }} to reorder">⋮⋮</button><span class="section-order-number" data-section-number></span><label class="section-row-content"><input type="checkbox" name="{{ $isCustomSection ? 'custom_section_ids[]' : 'section_types[]' }}" value="{{ $key }}" @checked(in_array($key, $activeSections, true)) class="section-check rounded border-slate-300 text-violet-600 focus:ring-violet-500"><span class="min-w-0"><span class="section-row-title"><strong>{{ $section['title'] ?? $section['label'] }}</strong><em>{{ $isCustomSection ? 'Reusable' : 'Built-in' }}</em></span><span class="section-row-description">{{ $isCustomSection ? 'Reusable section · '.collect($section['page_slugs'] ?? [])->count().' page(s)' : $section['description'] }}</span></span></label><span class="section-state" data-section-state></span><span class="section-actions"><button type="button" data-move="up" title="Move up" aria-label="Move {{ $section['title'] ?? $section['label'] }} up">↑</button><button type="button" data-move="down" title="Move down" aria-label="Move {{ $section['title'] ?? $section['label'] }} down">↓</button></span></div>@endforeach</div></section>
            <div class="sticky bottom-3 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur"><a href="{{ route('website-studio.index') }}" class="text-sm font-bold text-slate-500 hover:text-slate-900">← Back to Website Studio</a><button class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>Save page</button></div>
        </form>
    </div>
    <style>
        .card-title{font-size:1.1rem;font-weight:800;color:#0f172a}.helper{margin-top:.25rem;font-size:.82rem;line-height:1.4;color:#64748b}.optional{font-weight:400;color:#94a3b8}.field-label{display:block;margin-bottom:.4rem;font-size:.72rem;font-weight:700;color:#475569}.field-input{display:block;width:100%;border-radius:.7rem;border:1px solid #e2e8f0;background:#fff;padding:.7rem .8rem;font-size:.875rem;color:#0f172a;outline:none}.field-input:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgb(139 92 246 / .12)}.color-input{display:block;height:43px;width:100%;border-radius:.7rem;border:1px solid #e2e8f0;background:#fff;padding:.25rem}.step-card{border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:1rem}.step-card.active{border-color:#c4b5fd;background:#faf8ff}.step-card span{display:grid;height:26px;width:26px;place-items:center;border-radius:999px;background:#e2e8f0;color:#64748b;font-size:.75rem;font-weight:800}.step-card.active span{background:#7c3aed;color:#fff}.step-card strong,.step-card small{display:block}.step-card strong{margin-top:.7rem;font-size:.8rem;color:#0f172a}.step-card small{margin-top:.2rem;font-size:.7rem;line-height:1.4;color:#64748b}
        .section-manager{overflow:hidden;padding:0}.section-manager-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;padding:24px 26px 20px;background:linear-gradient(135deg,#fff 35%,#f7f5ff)}.section-manager-kicker{display:inline-flex;align-items:center;gap:7px;color:#6d28d9;font-size:.68rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.section-manager-summary{min-width:170px;border:1px solid #ddd6fe;border-radius:14px;background:#fff;padding:11px 14px;box-shadow:0 8px 24px rgb(109 40 217 / .08)}.section-manager-summary span,.section-manager-summary small{display:block}.section-manager-summary span{color:#5b21b6;font-size:.78rem;font-weight:900}.section-manager-summary small{margin-top:2px;color:#94a3b8;font-size:.63rem}.section-drop-note{display:flex;align-items:center;gap:9px;border-block:1px solid #ede9fe;background:#faf8ff;padding:11px 26px;color:#6b7280;font-size:.72rem}.section-list{display:grid;gap:10px;padding:18px 20px 22px}.section-row{display:flex;align-items:center;gap:12px;min-height:76px;border:1px solid #e2e8f0;border-radius:15px;background:#fff;padding:10px 12px;box-shadow:0 2px 8px rgb(15 23 42 / .025);transition:border-color .16s,box-shadow .16s,transform .16s,opacity .16s}.section-row:hover{border-color:#c4b5fd;box-shadow:0 8px 24px rgb(109 40 217 / .08)}.section-row.is-active{border-color:#c4b5fd;background:linear-gradient(90deg,#faf8ff,#fff 58%)}.section-row:has(.section-check:not(:checked)){opacity:.58}.section-row.is-dragging{z-index:2;opacity:.35;transform:scale(.985)}.section-row.is-drop-before{box-shadow:0 -4px 0 #7c3aed}.section-row.is-drop-after{box-shadow:0 4px 0 #7c3aed}.section-drag-handle{display:grid;height:44px;width:34px;flex:0 0 auto;place-items:center;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#94a3b8;font-size:1rem;font-weight:900;cursor:grab;letter-spacing:-3px;touch-action:none;user-select:none}.section-drag-handle:active{cursor:grabbing}.section-order-number{display:grid;height:30px;width:30px;flex:0 0 auto;place-items:center;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:.7rem;font-weight:900}.section-row.is-active .section-order-number{background:#ede9fe;color:#6d28d9}.section-row-content{display:flex;min-width:0;flex:1;align-items:flex-start;gap:12px;cursor:pointer}.section-check{margin-top:3px;flex:0 0 auto}.section-row-title{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.section-row-title strong{color:#0f172a;font-size:.84rem}.section-row-title em{border-radius:999px;background:#f1f5f9;padding:3px 7px;color:#64748b;font-size:.58rem;font-style:normal;font-weight:800;text-transform:uppercase}.section-row.is-active .section-row-title em{background:#ede9fe;color:#6d28d9}.section-row-description{display:block;margin-top:4px;color:#64748b;font-size:.7rem}.section-state{min-width:56px;color:#94a3b8;font-size:.62rem;font-weight:900;text-align:center;text-transform:uppercase}.section-row.is-active .section-state{color:#16a34a}.section-actions{display:flex;gap:5px}.section-actions button{display:grid;height:32px;width:32px;place-items:center;border-radius:8px;background:#f1f5f9;color:#475569;font-weight:800}.section-actions button:hover{background:#ede9fe;color:#6d28d9}@media(max-width:700px){.section-manager-head{align-items:flex-start;flex-direction:column;padding:20px}.section-manager-summary{width:100%}.section-drop-note{padding-inline:20px}.section-list{padding:14px}.section-order-number,.section-state{display:none}.section-row{gap:8px}.section-actions{flex-direction:column}.section-actions button{height:27px}}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded',()=>{
            const list=document.querySelector('#section-list');
            const count=document.querySelector('[data-section-count]');
            if(!list)return;
            let draggedRow=null;
            const clearDropState=()=>list.querySelectorAll('[data-section-row]').forEach(row=>row.classList.remove('is-drop-before','is-drop-after'));
            const refresh=()=>{
                const rows=[...list.querySelectorAll('[data-section-row]')];
                rows.forEach((row,index)=>{
                    const active=row.querySelector('.section-check').checked;
                    row.classList.toggle('is-active',active);
                    row.querySelector('[data-section-number]').textContent=index+1;
                    row.querySelector('[data-section-state]').textContent=active?'Visible':'Hidden';
                });
                count.textContent=`${rows.filter(row=>row.querySelector('.section-check').checked).length} of ${rows.length} sections visible`;
            };
            list.addEventListener('change',refresh);
            list.addEventListener('click',event=>{
                const button=event.target.closest('[data-move]');
                if(!button)return;
                const row=button.closest('[data-section-row]');
                if(button.dataset.move==='up'&&row.previousElementSibling)row.parentNode.insertBefore(row,row.previousElementSibling);
                if(button.dataset.move==='down'&&row.nextElementSibling)row.parentNode.insertBefore(row.nextElementSibling,row);
                refresh();
            });
            const finishPointerDrag=()=>{
                draggedRow?.classList.remove('is-dragging');
                draggedRow=null;
                clearDropState();
                refresh();
            };
            list.addEventListener('pointerdown',event=>{
                const handle=event.target.closest('.section-drag-handle');
                if(!handle||event.button!==0)return;
                draggedRow=handle.closest('[data-section-row]');
                draggedRow.classList.add('is-dragging');
                handle.setPointerCapture?.(event.pointerId);
                event.preventDefault();
            });
            list.addEventListener('pointermove',event=>{
                if(!draggedRow)return;
                const target=document.elementFromPoint(event.clientX,event.clientY)?.closest('[data-section-row]');
                if(!target||target===draggedRow)return;
                const after=event.clientY>target.getBoundingClientRect().top+(target.offsetHeight/2);
                list.insertBefore(draggedRow,after?target.nextElementSibling:target);
                clearDropState();
                target.classList.add(after?'is-drop-after':'is-drop-before');
                refresh();
            });
            list.addEventListener('pointerup',finishPointerDrag);
            list.addEventListener('pointercancel',finishPointerDrag);
            list.addEventListener('dragstart',event=>{
                const handle=event.target.closest('.section-drag-handle');
                if(!handle)return;
                draggedRow=handle.closest('[data-section-row]');
                draggedRow.classList.add('is-dragging');
                event.dataTransfer.effectAllowed='move';
                event.dataTransfer.setData('text/plain',draggedRow.dataset.sectionKey||'section');
            });
            list.addEventListener('dragover',event=>{
                if(!draggedRow)return;
                const target=event.target.closest('[data-section-row]');
                if(!target||target===draggedRow)return;
                event.preventDefault();
                clearDropState();
                const after=event.clientY>target.getBoundingClientRect().top+(target.offsetHeight/2);
                target.classList.add(after?'is-drop-after':'is-drop-before');
            });
            list.addEventListener('drop',event=>{
                if(!draggedRow)return;
                const target=event.target.closest('[data-section-row]');
                if(!target||target===draggedRow)return;
                event.preventDefault();
                const after=event.clientY>target.getBoundingClientRect().top+(target.offsetHeight/2);
                list.insertBefore(draggedRow,after?target.nextElementSibling:target);
                clearDropState();
                refresh();
            });
            list.addEventListener('dragend',()=>{
                draggedRow?.classList.remove('is-dragging');
                draggedRow=null;
                clearDropState();
            });
            refresh();
        });
    </script>
</x-app-layout>
