<x-app-layout :title="__('Navigation Builder')" :breadcrumbs="$breadcrumbs">
    <div class="website-studio-admin space-y-6">
        <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-violet-700">
                    <i data-lucide="menu" class="size-3.5"></i>
                    {{ __('Public website navigation') }}
                </div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ __('Navigation Builder') }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ __('Create a clear visitor journey and choose a polished topbar style for every public website page.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('website-studio.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-violet-200 hover:text-violet-700">
                    <i data-lucide="arrow-left" class="size-4"></i>{{ __('Website Studio') }}
                </a>
                <a href="{{ $publicUrl }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white transition hover:bg-violet-700">
                    <i data-lucide="external-link" class="size-4"></i>{{ __('Open public website') }}
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('website-studio.navigation.update') }}" class="space-y-6" data-navigation-builder>
            @csrf
            @method('PUT')

            <section class="dashboard-card overflow-hidden p-0">
                <div class="border-b border-slate-100 bg-gradient-to-r from-violet-50 via-white to-cyan-50 p-5 sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-violet-600">{{ __('Topbar design') }}</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-950">{{ __('Choose your menu style') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ __('The selected design is shared by the homepage, events, sermons, and every public page.') }}</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-violet-100 bg-white px-3 py-1.5 text-xs font-bold text-violet-700 shadow-sm">
                            <i data-lucide="sparkles" class="size-3.5"></i>{{ count($menuStyles) }} {{ __('professional styles') }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-5">
                    @foreach ($menuStyles as $key => $style)
                        <label class="menu-style-card {{ old('menu_style', $settings['menu_style'] ?? 'classic') === $key ? 'is-selected' : '' }}" data-menu-style-card>
                            <input type="radio" name="menu_style" value="{{ $key }}" @checked(old('menu_style', $settings['menu_style'] ?? 'classic') === $key) class="sr-only" data-menu-style>
                            <span class="menu-style-preview preview-{{ $key }}" aria-hidden="true">
                                <span class="preview-bar"><i></i><b></b><em></em><em></em><em></em><strong></strong></span>
                                <span class="preview-body"><i></i><b></b><em></em></span>
                            </span>
                            <span class="mt-4 flex items-start justify-between gap-2">
                                <span>
                                    <strong class="block text-sm font-bold text-slate-950">{{ __($style['label']) }}</strong>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __($style['description']) }}</span>
                                </span>
                                <span class="style-check"><i data-lucide="check" class="size-3.5"></i></span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section class="dashboard-card space-y-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700"><i data-lucide="menu" class="size-4"></i></span>
                            <div>
                                <h2 class="text-lg font-bold text-slate-950">{{ __('Menu links') }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ __('Drag the grip handle or use the arrows to arrange links in the order visitors should see them.') }}</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600"><span data-navigation-count>{{ count($navigation) }}</span>/8 {{ __('links') }}</span>
                    </div>

                    <div class="space-y-3" data-navigation-list>
                        @foreach (old('navigation', $navigation) as $index => $item)
                            @include('website-studio._navigation-item', ['index' => $index, 'item' => $item])
                        @endforeach
                    </div>

                    <template data-navigation-template>
                        @include('website-studio._navigation-item', ['index' => '__INDEX__', 'item' => []])
                    </template>

                    <template data-submenu-template>
                        @include('website-studio._navigation-child', ['parentIndex' => '__PARENT__', 'childIndex' => '__CHILD__', 'child' => []])
                    </template>

                    <button type="button" class="navigation-add" data-navigation-add><i data-lucide="plus" class="size-4"></i>{{ __('Add menu item') }}</button>
                </section>

                <aside class="dashboard-card h-fit space-y-4 xl:sticky xl:top-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">{{ __('Live structure') }}</p>
                        <h2 class="mt-1 text-base font-bold text-slate-950">{{ __('Visitor menu preview') }}</h2>
                    </div>
                    <div class="rounded-2xl bg-slate-950 p-4 shadow-xl shadow-slate-200">
                        <div class="flex items-center gap-2 border-b border-white/10 pb-3"><span class="size-7 rounded-lg bg-violet-500"></span><span class="min-w-0 truncate text-xs font-bold text-white">{{ $settings['site_name'] }}</span></div>
                        <div class="mt-3 flex flex-wrap gap-2" data-live-links></div>
                    </div>
                    <div class="rounded-xl border border-cyan-100 bg-cyan-50 p-4 text-xs leading-5 text-cyan-900">
                        <div class="flex items-start gap-2"><i data-lucide="info" class="mt-0.5 size-4 shrink-0"></i><p>{{ __('Internal paths, section anchors, and full HTTPS links are supported. Hidden links stay saved but do not appear publicly.') }}</p></div>
                    </div>
                </aside>
            </div>

            <div class="sticky bottom-4 z-10 flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-2xl shadow-slate-300/50 backdrop-blur-xl">
                <p class="hidden text-sm text-slate-500 sm:block">{{ __('Changes apply to every public website page after saving.') }}</p>
                <button type="submit" class="ml-auto inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 transition hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>{{ __('Save navigation') }}</button>
            </div>
        </form>
    </div>

    <style>
        .field-label { display:block; margin-bottom:.35rem; color:#64748b; font-size:.68rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .field-input { width:100%; min-height:2.65rem; border:1px solid #e2e8f0; border-radius:.75rem; background:#fff; padding:.62rem .75rem; color:#0f172a; font-size:.8rem; outline:none; transition:.15s; }
        .field-input:focus { border-color:var(--studio-primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--studio-primary) 14%,transparent); }
        .menu-style-card { position:relative; cursor:pointer; border:2px solid #eef2f7; border-radius:1rem; background:#fff; padding:.75rem; transition:.2s; }
        .menu-style-card:hover { transform:translateY(-2px); border-color:color-mix(in srgb,var(--studio-primary) 38%,#fff); box-shadow:0 14px 30px rgb(15 23 42 / .08); }
        .menu-style-card.is-selected { border-color:var(--studio-primary); background:color-mix(in srgb,var(--studio-primary) 5%,#fff); box-shadow:0 0 0 3px color-mix(in srgb,var(--studio-primary) 9%,transparent); }
        .style-check { display:grid; width:1.45rem; height:1.45rem; flex:0 0 auto; place-items:center; border-radius:50%; background:#eef2f7; color:transparent; }
        .menu-style-card.is-selected .style-check { background:var(--studio-primary); color:#fff; }
        .menu-style-preview { position:relative; display:block; height:98px; overflow:hidden; border-radius:.7rem; background:linear-gradient(145deg,#f8fafc,#e2e8f0); }
        .preview-bar { position:absolute; z-index:2; top:0; right:0; left:0; display:flex; height:25px; align-items:center; gap:4px; padding:0 7px; background:#111827; }
        .preview-bar i { width:12px; height:12px; margin-right:auto; border-radius:4px; background:var(--studio-primary); }
        .preview-bar b,.preview-bar em { display:block; height:3px; border-radius:9px; background:#94a3b8; }
        .preview-bar b { width:18px; }.preview-bar em { width:11px; }.preview-bar strong { width:16px; height:8px; margin-left:2px; border-radius:8px; background:var(--studio-primary); }
        .preview-body { position:absolute; inset:25px 0 0; padding:17px 12px; }
        .preview-body i,.preview-body b,.preview-body em { display:block; border-radius:9px; }
        .preview-body i { width:48%; height:7px; background:#334155; }.preview-body b { width:70%; height:4px; margin-top:7px; background:#94a3b8; }.preview-body em { width:30%; height:12px; margin-top:10px; background:var(--studio-primary); }
        .preview-floating .preview-bar { top:7px; right:7px; left:7px; border-radius:9px; box-shadow:0 5px 10px rgb(15 23 42 / .25); }.preview-floating .preview-body { inset:32px 0 0; }
        .preview-centered .preview-bar i { margin-right:8px; }.preview-centered .preview-bar b { margin-left:auto; }.preview-centered .preview-bar strong { margin-left:auto; }
        .preview-pill .preview-bar b,.preview-pill .preview-bar em { height:10px; padding:2px; background:#334155; }.preview-pill .preview-bar { background:#0f172a; }
        .preview-accent .preview-bar { background:linear-gradient(90deg,var(--studio-primary),var(--studio-secondary)); }.preview-accent .preview-bar i,.preview-accent .preview-bar strong { background:color-mix(in srgb,var(--studio-secondary) 34%,#fff); }.preview-accent .preview-bar b,.preview-accent .preview-bar em { background:#fff; opacity:.75; }
        .navigation-item { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:start; gap:.75rem; border:1px solid #e5e7eb; border-radius:1rem; background:#fff; padding:.85rem; transition:border-color .15s,box-shadow .15s,transform .15s,opacity .15s; }
        .navigation-item-fields { display:grid; grid-template-columns:minmax(8rem,.8fr) minmax(12rem,1.4fr) minmax(9rem,.55fr); gap:.75rem; }
        .navigation-submenu { grid-column:2 / -1; border-top:1px solid #eef2f7; padding-top:.8rem; }
        .navigation-submenu-heading { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.65rem; }.navigation-submenu-heading strong { display:block; color:#334155; font-size:.75rem; }.navigation-submenu-heading span { display:block; margin-top:.2rem; color:#94a3b8; font-size:.7rem; }.navigation-add-child { width:auto; padding:.5rem .7rem; font-size:.68rem; }
        .navigation-submenu-list { display:grid; gap:.55rem; }.navigation-child { display:grid; grid-template-columns:auto minmax(8rem,.8fr) minmax(10rem,1.1fr) minmax(8rem,1fr) 4.5rem auto auto; align-items:end; gap:.55rem; border:1px solid #eef2f7; border-radius:.75rem; background:#f8fafc; padding:.6rem; }.navigation-child-grip { display:grid; width:1.5rem; height:2.65rem; place-items:center; color:#94a3b8; }.navigation-child .field-input { min-height:2.45rem; font-size:.75rem; }
        .navigation-item:hover { border-color:color-mix(in srgb,var(--studio-primary) 38%,#fff); box-shadow:0 8px 22px rgb(15 23 42 / .05); }
        .navigation-item.is-dragging { border-color:var(--studio-primary); opacity:.55; box-shadow:0 16px 35px color-mix(in srgb,var(--studio-primary) 20%,transparent); transform:scale(.985); }
        .navigation-item.is-drag-over { border-color:var(--studio-primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--studio-primary) 14%,transparent); }
        .navigation-handle { display:grid; width:2rem; height:2.65rem; cursor:grab; touch-action:none; place-items:center; border:0; border-radius:.55rem; background:transparent; padding:0; color:#94a3b8; transition:.15s; }
        .navigation-handle:hover,.navigation-handle:focus-visible { background:color-mix(in srgb,var(--studio-primary) 9%,#fff); color:var(--studio-primary); outline:none; }
        .navigation-handle:active { cursor:grabbing; }
        .navigation-actions { display:flex; min-height:2.65rem; align-items:center; gap:.35rem; }
        .visibility-toggle input { position:absolute; opacity:0; pointer-events:none; }.visibility-toggle span { display:inline-flex; height:2.35rem; align-items:center; gap:.35rem; border-radius:.65rem; background:#f1f5f9; padding:0 .65rem; color:#64748b; font-size:.7rem; font-weight:800; }.visibility-toggle input:checked + span { background:#ecfdf5; color:#047857; }
        .navigation-icon-button { display:grid; width:2.35rem; height:2.35rem; place-items:center; border:1px solid #e2e8f0; border-radius:.65rem; background:#fff; color:#64748b; transition:.15s; }.navigation-icon-button:hover { border-color:color-mix(in srgb,var(--studio-primary) 38%,#fff); color:var(--studio-primary); }.navigation-icon-button.is-danger:hover { border-color:#fecdd3; background:#fff1f2; color:#e11d48; }
        .navigation-add { display:flex; width:100%; align-items:center; justify-content:center; gap:.5rem; border:1px dashed color-mix(in srgb,var(--studio-primary) 38%,#fff); border-radius:.85rem; background:color-mix(in srgb,var(--studio-primary) 5%,#fff); padding:.8rem; color:var(--studio-primary); font-size:.78rem; font-weight:800; }.navigation-add:hover { background:color-mix(in srgb,var(--studio-primary) 9%,#fff); }
        @media (max-width:1100px) { .navigation-item-fields { grid-template-columns:1fr 1fr; }.navigation-item-fields label:first-child { grid-column:1 / -1; }.navigation-child { grid-template-columns:auto 1fr 1fr; }.navigation-child label:nth-of-type(3) { grid-column:2 / -1; }.navigation-child .visibility-toggle,.navigation-child > .navigation-icon-button { grid-row:auto; } }
        @media (max-width:900px) { .navigation-item { grid-template-columns:auto 1fr; }.navigation-actions { grid-column:2; flex-wrap:wrap; }.navigation-submenu { grid-column:1 / -1; } }
        @media (max-width:520px) { .navigation-item { grid-template-columns:1fr; }.navigation-handle { display:none; }.navigation-item-fields,.navigation-actions,.navigation-submenu { grid-column:1; }.navigation-item-fields,.navigation-child { grid-template-columns:1fr; }.navigation-item-fields label:first-child,.navigation-child label:nth-of-type(3) { grid-column:1; }.navigation-child-grip { display:none; }.navigation-actions { justify-content:flex-end; }.visibility-toggle { margin-right:auto; }.navigation-submenu-heading { align-items:flex-start; flex-direction:column; } }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const builder = document.querySelector('[data-navigation-builder]');
            const list = builder?.querySelector('[data-navigation-list]');
            const template = builder?.querySelector('[data-navigation-template]');
            const childTemplate = builder?.querySelector('[data-submenu-template]');
            const counter = builder?.querySelector('[data-navigation-count]');
            const liveLinks = builder?.querySelector('[data-live-links]');
            let nextIndex = list?.querySelectorAll('[data-navigation-row]').length || 0;
            let draggedRow = null;
            let pointerId = null;

            function updatePreview() {
                const rows = Array.from(list?.querySelectorAll('[data-navigation-row]') || []);
                if (counter) counter.textContent = rows.length;
                if (!liveLinks) return;
                liveLinks.innerHTML = '';
                rows.forEach(function (row) {
                    const label = row.querySelector('[data-menu-label]')?.value.trim();
                    const visible = row.querySelector('[data-menu-visible]')?.checked;
                    if (!label || !visible) return;
                    const item = document.createElement('span');
                    const type = row.querySelector('[data-menu-type]')?.value || 'link';
                    const children = row.querySelectorAll('[data-submenu-row]').length;
                    item.className = 'rounded-full bg-white/10 px-2.5 py-1.5 text-[10px] font-bold text-white';
                    item.textContent = `${label}${type !== 'link' ? ` · ${type === 'mega' ? 'Mega' : 'Dropdown'}${children ? ` (${children})` : ''}` : ''}`;
                    liveLinks.appendChild(item);
                });
                if (!liveLinks.children.length) liveLinks.innerHTML = '<span class="text-xs text-slate-400">{{ __('No visible menu links') }}</span>';
            }

            function refreshIcons() {
                if (window.lucide) window.lucide.createIcons();
            }

            function rowAtPoint(x, y) {
                return document.elementFromPoint(x, y)?.closest('[data-navigation-row]');
            }

            function placeDraggedRow(target, clientY) {
                if (!draggedRow || !target || target === draggedRow || !list?.contains(target)) return;
                const bounds = target.getBoundingClientRect();
                const insertAfter = clientY > bounds.top + bounds.height / 2;
                list.insertBefore(draggedRow, insertAfter ? target.nextElementSibling : target);
                list.querySelectorAll('.is-drag-over').forEach(row => row.classList.remove('is-drag-over'));
                target.classList.add('is-drag-over');
                updatePreview();
            }

            function finishDragging() {
                draggedRow?.classList.remove('is-dragging');
                list?.querySelectorAll('.is-drag-over').forEach(row => row.classList.remove('is-drag-over'));
                draggedRow = null;
                pointerId = null;
                document.body.style.removeProperty('user-select');
                document.body.style.removeProperty('cursor');
                updatePreview();
            }

            builder?.addEventListener('pointerdown', function (event) {
                const handle = event.target.closest('.navigation-handle');
                if (!handle || (event.pointerType === 'mouse' && event.button !== 0)) return;
                draggedRow = handle.closest('[data-navigation-row]');
                pointerId = event.pointerId;
                handle.setPointerCapture(pointerId);
                draggedRow?.classList.add('is-dragging');
                document.body.style.userSelect = 'none';
                document.body.style.cursor = 'grabbing';
                event.preventDefault();
            });
            builder?.addEventListener('pointermove', function (event) {
                if (!draggedRow || pointerId !== event.pointerId) return;
                event.preventDefault();
                if (event.clientY < 90) window.scrollBy({ top: -18, behavior: 'auto' });
                if (event.clientY > window.innerHeight - 90) window.scrollBy({ top: 18, behavior: 'auto' });
                placeDraggedRow(rowAtPoint(event.clientX, event.clientY), event.clientY);
            });
            builder?.addEventListener('pointerup', function (event) {
                if (pointerId === event.pointerId) finishDragging();
            });
            builder?.addEventListener('pointercancel', finishDragging);
            builder?.addEventListener('lostpointercapture', function () {
                if (draggedRow) finishDragging();
            });
            window.addEventListener('blur', finishDragging);

            builder?.addEventListener('change', function (event) {
                const style = event.target.closest('[data-menu-style]');
                if (style) {
                    builder.querySelectorAll('[data-menu-style-card]').forEach(card => card.classList.toggle('is-selected', card.contains(style)));
                }
                const type = event.target.closest('[data-menu-type]');
                if (type) type.closest('[data-navigation-row]')?.querySelector('[data-submenu-editor]')?.toggleAttribute('hidden', type.value === 'link');
                updatePreview();
            });
            builder?.addEventListener('input', updatePreview);
            builder?.addEventListener('click', function (event) {
                const row = event.target.closest('[data-navigation-row]');
                const childRow = event.target.closest('[data-submenu-row]');
                if (event.target.closest('[data-navigation-remove]')) row?.remove();
                if (event.target.closest('[data-move-up]') && row?.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
                if (event.target.closest('[data-move-down]') && row?.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
                if (event.target.closest('[data-submenu-remove]')) childRow?.remove();
                if (event.target.closest('[data-submenu-add]') && row && childTemplate) {
                    const submenuList = row.querySelector('[data-submenu-list]');
                    const parentIndex = Array.from(list.querySelectorAll('[data-navigation-row]')).indexOf(row);
                    const childIndex = submenuList?.querySelectorAll('[data-submenu-row]').length || 0;
                    submenuList?.insertAdjacentHTML('beforeend', childTemplate.innerHTML.replaceAll('__PARENT__', parentIndex).replaceAll('__CHILD__', childIndex));
                    refreshIcons();
                }
                updatePreview();
            });
            builder?.querySelector('[data-navigation-add]')?.addEventListener('click', function () {
                if (!list || !template || list.querySelectorAll('[data-navigation-row]').length >= 8) return;
                list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', nextIndex++));
                refreshIcons();
                list.lastElementChild?.querySelector('[data-menu-label]')?.focus();
                updatePreview();
            });
            updatePreview();
        });
    </script>
</x-app-layout>
