document.addEventListener('DOMContentLoaded', () => {
    const labels = {
        heading: 'Heading',
        text: 'Text',
        quote: 'Quote',
        image: 'Image',
        video: 'Video',
        button: 'Button',
        spacer: 'Spacer',
        carousel: 'Loop carousel',
    };
    const defaults = {
        heading: 'Section heading',
        text: 'Write a short message for your visitors.',
        quote: 'A meaningful quote from your church.',
        image: '',
        video: '',
        button: 'Learn more',
        spacer: '',
        carousel: '',
    };
    const id = () => window.crypto?.randomUUID?.() || `widget-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    const esc = (value) =>
        String(value || '').replace(
            /[&<>'"]/g,
            (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char],
        );
    const appRoot = window.location.pathname.split('/public/')[0];
    const mediaBase = `${window.location.origin}${appRoot}/public/storage/`;
    const mediaUrl = (value) =>
        value && (value.startsWith('http') || value.startsWith('//'))
            ? value
            : value
              ? `${mediaBase}${value.replace(/^\/+/, '')}`
              : '';
    const mediaLibraryUrl = () => `${window.location.origin}${appRoot}/public/website-studio/media`;
    const widgetFields = (item) => {
        if (item.type === 'spacer')
            return `<label>Spacer height (px)<input type="number" min="0" max="600" step="1" data-field="height" value="${Math.max(0, Math.min(600, Number(item.height) || 36))}"></label><span class="widget-hint">Choose how much vertical space this widget adds.</span>`;
        if (item.type === 'carousel') {
            const slides = (
                Array.isArray(item.slides) && item.slides.length
                    ? item.slides
                    : [{ id: id(), image: '', title: 'New slide', text: '', link: '' }]
            ).map((slide) => ({
                id: slide.id || id(),
                image: slide.image || '',
                title: slide.title || '',
                text: slide.text || '',
                link: slide.link || '',
            }));
            item.slides = slides;
            return `<div class="carousel-editor"><div class="carousel-editor-head"><strong>Slides</strong><button type="button" data-add-slide>+ Add slide</button></div>${slides
                .map(
                    (slide, index) =>
                        `<div class="carousel-slide" data-slide-index="${index}"><div class="carousel-slide-head"><b>Slide ${index + 1}</b><button type="button" data-remove-slide>Remove</button></div><label>Image URL<input data-slide-field="image" data-slide-index="${index}" value="${esc(slide.image)}" placeholder="https://..."></label><label>Upload image<input type="file" name="component_files[${slide.id || id()}]" accept="image/*"></label><label>Heading<input data-slide-field="title" data-slide-index="${index}" value="${esc(slide.title)}" placeholder="Slide heading"></label><label>Text<textarea data-slide-field="text" data-slide-index="${index}" rows="2" placeholder="Short message">${esc(slide.text)}</textarea></label><label>Link <span class="optional">(optional)</span><input data-slide-field="link" data-slide-index="${index}" value="${esc(slide.link)}" placeholder="/about or https://..."></label></div>`,
                )
                .join(
                    '',
                )}</div><label class="carousel-option"><input type="checkbox" data-field="autoplay" ${item.autoplay !== false ? 'checked' : ''}> Auto-play slides</label>`;
        }
        if (item.type === 'image')
            return `<label>Image URL <a href="${mediaLibraryUrl()}" target="_blank" class="media-library-link">Choose from media library ↗</a><input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload image<input type="file" name="component_files[${item.id}]" accept="image/*"></label><label>Alt text<input data-field="alt" value="${esc(item.alt)}"></label>`;
        if (item.type === 'video')
            return `<label>Video URL<input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload video<input type="file" name="component_files[${item.id}]" accept="video/mp4,video/webm,video/ogg"></label>`;
        if (item.type === 'button')
            return `<label>Button label<input data-field="text" value="${esc(item.text)}"></label><label>Button link<input data-field="url" value="${esc(item.url)}"></label>`;
        return `<label>${item.type === 'heading' ? 'Heading' : item.type === 'quote' ? 'Quote' : 'Text'}<textarea data-field="text" rows="3">${esc(item.text)}</textarea></label>`;
    };
    const makeColumnGroup = (columns = [], widths = []) => ({
        type: 'columns',
        id: id(),
        columns: (columns.length ? columns : [{ components: [] }]).map((column, index) => ({
            width: Number(widths[index] || column.width) || 1,
            components: column.components || [],
        })),
    });
    const normalise = (value, initialWidths = []) => {
        if (Array.isArray(value)) {
            const count = Math.max(1, Math.min(4, Math.max(0, ...value.map((item) => Number(item.column || 0))) + 1));
            return {
                type: 'columns',
                id: id(),
                groups: [
                    makeColumnGroup(
                        Array.from({ length: count }, (_, column) => ({
                            components: value
                                .filter((item) => Number(item.column || 0) === column)
                                .map((item) => ({ ...item, id: item.id || id() })),
                        })),
                        initialWidths,
                    ),
                ],
            };
        }
        if (value?.type === 'columns') {
            const groups = Array.isArray(value.groups) && value.groups.length ? value.groups : [value];
            return {
                type: 'columns',
                id: value.id || id(),
                groups: groups.map((group) => makeColumnGroup(group.columns || [])),
            };
        }
        return { type: 'columns', id: id(), groups: [makeColumnGroup()] };
    };
    const initBuilder = (builder, initial, initialWidths = []) => {
        let tree = normalise(initial, initialWidths);
        let draggedComponent = null;
        let draggedColumn = null;
        const output = builder.querySelector('[data-components-output]');
        builder.querySelector('.widget-toolbar')?.remove();
        builder.querySelector('[data-widget-list]')?.remove();
        let canvas = builder.querySelector('[data-builder-canvas]');
        if (!canvas) {
            canvas = document.createElement('div');
            canvas.dataset.builderCanvas = '';
            builder.appendChild(canvas);
        }
        const renderContainer = (container, host) => {
            const row = document.createElement('div');
            row.className = 'nested-column-group';
            row.innerHTML = `<div class="nested-group-toolbar"><strong>Column group</strong><button type="button" data-add-column>+ Add column</button><button type="button" data-remove-column>- Remove column</button></div><div class="nested-column-list"></div>`;
            const list = row.querySelector('.nested-column-list');
            const refreshColumnVisuals = () => {
                const total = container.columns.reduce((sum, item) => sum + Math.max(1, Number(item.width) || 1), 0);
                list.style.gridTemplateColumns = container.columns
                    .map((item) => `${Math.max(1, Number(item.width) || 1)}fr`)
                    .join(' ');
                list.querySelectorAll('[data-width-value]').forEach((badge, index) => {
                    const width = Math.max(1, Number(container.columns[index]?.width) || 1);
                    badge.textContent = `${Math.round((width / total) * 100)}%`;
                });
            };
            list.addEventListener('dragover', (event) => event.preventDefault());
            row.querySelector('[data-add-column]').addEventListener('click', () => {
                container.columns.push({ width: 1, components: [] });
                render();
            });
            row.querySelector('[data-remove-column]').addEventListener('click', () => {
                if (container.columns.length > 0) {
                    container.columns.pop();
                    render();
                }
            });
            container.columns.forEach((column, columnIndex) => {
                const columnEl = document.createElement('div');
                columnEl.className = 'nested-column';
                columnEl.draggable = true;
                columnEl.dataset.columnIndex = columnIndex;
                columnEl.innerHTML = `<div class="nested-column-heading"><strong><span class="column-drag-handle" title="Drag column">⠿</span> Column ${columnIndex + 1}</strong><label><span>Width <b data-width-value>—</b></span><input type="number" min="1" max="95" value="${column.width}" data-width aria-label="Column ${columnIndex + 1} width"></label></div><div class="nested-column-actions"><button type="button" data-add-subcolumns>+ Sub-columns</button>${Object.keys(
                    labels,
                )
                    .map((type) => `<button type="button" data-add="${type}">+ ${labels[type]}</button>`)
                    .join('')}</div><div class="nested-column-content"></div>`;
                columnEl.addEventListener('dragstart', (event) => {
                    if (event.target.closest('.nested-column') !== columnEl) return;
                    if (event.target.closest('.widget-block')) return;
                    if (event.target.closest('input, textarea, button, a, select')) {
                        event.preventDefault();
                        return;
                    }
                    draggedColumn = { column, group: container };
                    draggedComponent = null;
                    columnEl.classList.add('is-dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', column.id || `column-${columnIndex}`);
                });
                columnEl.addEventListener('dragend', () => {
                    draggedColumn = null;
                    columnEl.classList.remove('is-dragging');
                });
                columnEl.addEventListener('dragover', (event) => {
                    if (draggedColumn) {
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                    }
                });
                columnEl.addEventListener('drop', (event) => {
                    if (!draggedColumn || draggedColumn.column === column) return;
                    event.preventDefault();
                    event.stopPropagation();
                    const sourceGroup = draggedColumn.group;
                    const sourceIndex = sourceGroup.columns.indexOf(draggedColumn.column);
                    if (sourceIndex < 0) return;
                    sourceGroup.columns.splice(sourceIndex, 1);
                    let targetIndex = container.columns.indexOf(column);
                    if (sourceGroup === container && sourceIndex < targetIndex) targetIndex -= 1;
                    container.columns.splice(Math.max(0, targetIndex), 0, draggedColumn.column);
                    draggedColumn = null;
                    render();
                });
                columnEl.querySelector('[data-width]').addEventListener('input', (event) => {
                    column.width = Math.max(1, Math.min(95, Number(event.target.value) || 1));
                    refreshColumnVisuals();
                    sync();
                });
                columnEl.querySelector('[data-add-subcolumns]').addEventListener('click', () => {
                    column.components.push({
                        id: id(),
                        type: 'columns',
                        columns: [
                            { width: 1, components: [] },
                            { width: 1, components: [] },
                        ],
                    });
                    render();
                });
                columnEl.querySelectorAll('[data-add]').forEach((button) =>
                    button.addEventListener('click', () => {
                        const type = button.dataset.add;
                        column.components.push({
                            id: id(),
                            type,
                            text: defaults[type],
                            url: '',
                            alt: '',
                            height: type === 'spacer' ? 36 : 0,
                            slides:
                                type === 'carousel'
                                    ? [{ id: id(), image: '', title: 'New slide', text: '', link: '' }]
                                    : [],
                            autoplay: true,
                        });
                        render();
                    }),
                );
                const content = columnEl.querySelector('.nested-column-content');
                content.addEventListener('dragover', (event) => {
                    if (draggedComponent) {
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                    }
                });
                content.addEventListener('drop', (event) => {
                    if (!draggedComponent) return;
                    event.preventDefault();
                    event.stopPropagation();
                    const source = draggedComponent.source;
                    const sourceIndex = source.indexOf(draggedComponent.item);
                    if (sourceIndex < 0) return;
                    source.splice(sourceIndex, 1);
                    const targetBlock = event.target.closest('.widget-block');
                    let targetIndex = targetBlock
                        ? Number(targetBlock.dataset.componentIndex)
                        : column.components.length;
                    if (source === column.components && sourceIndex < targetIndex) targetIndex -= 1;
                    column.components.splice(Math.max(0, targetIndex), 0, draggedComponent.item);
                    draggedComponent = null;
                    render();
                });
                column.components.forEach((item, itemIndex) => {
                    if (item.type === 'columns') {
                        renderContainer(item, content);
                        return;
                    }
                    const block = document.createElement('article');
                    block.className = 'widget-block';
                    block.draggable = true;
                    block.dataset.componentIndex = itemIndex;
                    block.addEventListener('dragstart', (event) => {
                        if (event.target.closest('input, textarea, button, a, select')) {
                            event.preventDefault();
                            return;
                        }
                        draggedComponent = { item, source: column.components };
                        draggedColumn = null;
                        block.classList.add('is-dragging');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', item.id || `widget-${itemIndex}`);
                    });
                    block.addEventListener('dragend', () => {
                        draggedComponent = null;
                        block.classList.remove('is-dragging');
                    });
                    block.innerHTML = `<div class="widget-top"><strong>${labels[item.type] || 'Text'}</strong><button type="button" data-remove>×</button></div><div class="widget-fields">${item.type === 'image' && item.url ? `<div class="widget-media-preview"><img src="${esc(mediaUrl(item.url))}" alt="${esc(item.alt)}"></div>` : ''}${item.type === 'video' && item.url ? `<div class="widget-media-preview"><video src="${esc(mediaUrl(item.url))}" controls></video></div>` : ''}${widgetFields(item)}</div>`;
                    block.querySelectorAll('[data-slide-field]').forEach((field) =>
                        field.addEventListener('input', () => {
                            const slide = item.slides?.[Number(field.dataset.slideIndex)];
                            if (slide) slide[field.dataset.slideField] = field.value;
                            sync();
                        }),
                    );
                    block.querySelector('[data-add-slide]')?.addEventListener('click', () => {
                        item.slides.push({ id: id(), image: '', title: 'New slide', text: '', link: '' });
                        render();
                    });
                    block.querySelectorAll('[data-remove-slide]').forEach((button, slideIndex) =>
                        button.addEventListener('click', () => {
                            if (item.slides.length > 1) item.slides.splice(slideIndex, 1);
                            render();
                        }),
                    );
                    block.querySelector('[data-remove]').addEventListener('click', () => {
                        column.components.splice(itemIndex, 1);
                        render();
                    });
                    block.querySelectorAll('[data-field]').forEach((field) =>
                        field.addEventListener('input', () => {
                            item[field.dataset.field] = field.type === 'checkbox' ? field.checked : field.value;
                            sync();
                        }),
                    );
                    content.appendChild(block);
                });
                list.appendChild(columnEl);
            });
            refreshColumnVisuals();
            host.appendChild(row);
        };
        const sync = () => {
            output.value = JSON.stringify(tree);
        };
        const render = () => {
            canvas.innerHTML = '';
            const stack = document.createElement('div');
            stack.className = 'column-group-stack';
            const stackToolbar = document.createElement('div');
            stackToolbar.className = 'column-stack-toolbar';
            stackToolbar.innerHTML =
                '<strong>Stacked column groups</strong><button type="button" data-add-group>+ Add column group below</button>';
            stackToolbar.querySelector('[data-add-group]').addEventListener('click', () => {
                tree.groups.push(makeColumnGroup());
                render();
            });
            stack.appendChild(stackToolbar);
            (tree.groups || [tree]).forEach((group) => renderContainer(group, stack));
            canvas.appendChild(stack);
            sync();
        };
        render();
    };
    document
        .querySelectorAll('[data-builder]')
        .forEach((builder) =>
            initBuilder(builder, JSON.parse(builder.querySelector('[data-components-seed]')?.textContent || '[]')),
        );
    const map = JSON.parse(document.querySelector('#section-components-map')?.textContent || '{}');
    document.querySelectorAll('form[action*="/website-studio/sections/"]').forEach((form) => {
        if (form.querySelector('[data-builder],input[name="_method"][value="DELETE"]')) return;
        const idValue = form.action.split('/').pop();
        const builder = document.createElement('div');
        builder.className = 'builder-shell my-4';
        builder.dataset.builder = '';
        builder.innerHTML =
            '<div class="mb-2 text-xs font-bold text-slate-700">Build your layout. Add columns inside any column for unlimited nesting.</div><div data-builder-canvas></div><input type="hidden" name="components" data-components-output>';
        form.prepend(builder);
        initBuilder(builder, map[idValue]?.components || map[idValue] || [], map[idValue]?.column_widths || []);
    });
});
