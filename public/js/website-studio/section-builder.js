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
        gallery: 'Gallery',
        card: 'Card',
        icon: 'Icon',
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
        gallery: '',
        card: 'A welcoming card',
        icon: '✦',
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
    const iconChoices = [
        ['Star', '★'],
        ['Spark', '✦'],
        ['Heart', '♥'],
        ['Check', '✓'],
        ['Arrow', '→'],
        ['Home', '⌂'],
        ['Church', '✝'],
        ['Cross', '✝'],
        ['Cross outline', '†'],
        ['Orthodox cross', '☦'],
        ['Latin cross', '✞'],
        ['Bible', '▤'],
        ['Open book', '▱'],
        ['Scripture', '❧'],
        ['Prayer', '🙏'],
        ['Praying hands', '🤲'],
        ['Worship', '🙌'],
        ['Dove', '🕊'],
        ['Fish', '🐟'],
        ['Bread', '🍞'],
        ['Chalice', '♜'],
        ['Candle', '🕯'],
        ['Church building', '⛪'],
        ['Bell tower', '🔔'],
        ['Angel', '♁'],
        ['Crown of life', '♛'],
        ['Holy heart', '♡'],
        ['Alpha', 'Α'],
        ['Omega', 'Ω'],
        ['Amen', 'A'],
        ['Hallelujah', 'H'],
        ['Faith', '☮'],
        ['Grace', '✦'],
        ['Hope', '⚓'],
        ['Love', '♥'],
        ['Mission', '🌍'],
        ['Evangelism', '📖'],
        ['Community', '♟'],
        ['Offering', '♧'],
        ['Church door', '▣'],
        ['Sanctuary', '⌂'],
        ['Pulpit', '▥'],
        ['Choir', '♫'],
        ['Worship music', '♪'],
        ['Microphone', '♩'],
        ['Sermon', '▤'],
        ['Ministry', '⚒'],
        ['Volunteer', '🤝'],
        ['Serve', '⚒'],
        ['Mission trip', '✈'],
        ['Outreach', '↗'],
        ['World mission', '🌐'],
        ['Bible study', '✎'],
        ['Devotional', '☼'],
        ['Prayer request', '☏'],
        ['Intercession', '☮'],
        ['Blessing', '☀'],
        ['Holy spirit', '♨'],
        ['Miracle', '✧'],
        ['Resurrection', '☀'],
        ['Easter', '✝'],
        ['Christmas', '☆'],
        ['Nativity', '★'],
        ['Palm branch', '♧'],
        ['Vine', '♧'],
        ['Grapes', '♢'],
        ['Shepherd', '♟'],
        ['Lamb', '♢'],
        ['Flock', '♟'],
        ['Kingdom', '♛'],
        ['Family ministry', '♧'],
        ['Youth ministry', '★'],
        ['Children ministry', '●'],
        ['Small group', '♟'],
        ['Marriage', '∞'],
        ['Care', '♥'],
        ['Food pantry', '♢'],
        ['Community meal', '♧'],
        ['Welcome', '☻'],
        ['Testimony', '❞'],
        ['Praise', '✦'],
        ['Joy', '☺'],
        ['Peace dove', '☮'],
        ['Hope anchor', '⚓'],
        ['Truth', '✓'],
        ['Hymnal', '▤'],
        ['Church bell', '♧'],
        ['Rosary', '◌'],
        ['Prayer beads', '◦'],
        ['Holy water', '♒'],
        ['Incense', '♨'],
        ['Candles', '♮'],
        ['Communion', '♜'],
        ['Altar', '▥'],
        ['Tabernacle', '▣'],
        ['Cathedral', '⛪'],
        ['Chapel', '⌂'],
        ['Steeple', '♰'],
        ['Stained glass', '◇'],
        ['Religious cross', '✠'],
        ['Lutheran cross', '✛'],
        ['Celtic cross', '☘'],
        ['Cross and crown', '♛'],
        ['Sacred heart', '♡'],
        ['Holy family', '♧'],
        ['Ten commandments', '▤'],
        ['Stone tablets', '▥'],
        ['Manger', '⌂'],
        ['Shepherd staff', '⚚'],
        ['Ark', '▱'],
        ['Noah dove', '🕊'],
        ['Manna', '✧'],
        ['Burning bush', '♨'],
        ['Mountains', '⌃'],
        ['River Jordan', '≋'],
        ['Prayer candle', '♮'],
        ['Offering plate', '♢'],
        ['Tithe', '♧'],
        ['Baptism', '♒'],
        ['Water baptism', '≋'],
        ['Confirmation', '✓'],
        ['Dedication', '✦'],
        ['Ordination', '♜'],
        ['Pastor', '♟'],
        ['Deacon', '♙'],
        ['Church service', '☼'],
        ['Sunday school', '▤'],
        ['Bible verse', '❞'],
        ['Gospel', '✝'],
        ['Good news', '✉'],
        ['Family', '♧'],
        ['Parent and child', '♟'],
        ['Children', '●'],
        ['Baby blessing', '♡'],
        ['Youth group', '★'],
        ['Women ministry', '♀'],
        ['Men ministry', '♂'],
        ['Seniors ministry', '♙'],
        ['Couples', '∞'],
        ['Friendship', '♥'],
        ['Helping hand', '☝'],
        ['Caring hands', '☷'],
        ['Giving', '♢'],
        ['Donation', '♧'],
        ['Food ministry', '♢'],
        ['Shelter', '⌂'],
        ['Clothing drive', '▱'],
        ['Hospital ministry', '✚'],
        ['Prison ministry', '▣'],
        ['Counseling', '☏'],
        ['Listening', '◉'],
        ['Comfort', '♡'],
        ['Healing', '✚'],
        ['Protection', '⬟'],
        ['Safety', '⌾'],
        ['Justice', '⚖'],
        ['Creation', '☼'],
        ['Garden', '♧'],
        ['Harvest', '♢'],
        ['Seed', '✧'],
        ['Tree of life', '♣'],
        ['Path', '↗'],
        ['Narrow way', '⌁'],
        ['Light of world', '☀'],
        ['Salt and light', '✦'],
        ['Living water', '≋'],
        ['Bread of life', '♢'],
        ['Watch night', '◷'],
        ['Retreat', '⌂'],
        ['Conference', '▣'],
        ['Event', '☆'],
        ['Registration', '✓'],
        ['Announcement', '⚑'],
        ['People', '♟'],
        ['Globe', '◎'],
        ['Calendar', '▣'],
        ['Message', '✉'],
        ['Phone', '☎'],
        ['Bell', '🔔'],
        ['Music', '♫'],
        ['Light', '☀'],
        ['Settings', '⚙'],
        ['Bolt', '⚡'],
        ['Flag', '⚑'],
        ['Book', '▤'],
        ['Gift', '♢'],
        ['Search', '⌕'],
        ['Lock', '▣'],
        ['Play', '▶'],
        ['Pause', 'Ⅱ'],
        ['Plus', '+'],
        ['Minus', '−'],
        ['Info', 'i'],
        ['Question', '?'],
        ['Peace', '☮'],
        ['Recycle', '♻'],
        ['Sun', '☼'],
        ['Moon', '☾'],
        ['Rain', '☂'],
        ['Fire', '♨'],
        ['Water', '♒'],
        ['Mountain', '♧'],
        ['Circle', '●'],
        ['Square', '■'],
        ['Diamond', '◆'],
        ['Triangle', '▲'],
        ['Target', '◎'],
        ['Eye', '◉'],
        ['Key', '⚿'],
        ['Shield', '⬟'],
        ['Crown', '♛'],
        ['Medal', '🏅'],
        ['Rocket', '🚀'],
        ['Car', '▰'],
        ['Location', '⌖'],
        ['Time', '◷'],
        ['Download', '⇩'],
        ['Upload', '⇧'],
        ['External link', '↗'],
        ['Menu', '☰'],
        ['Warning', '⚠'],
        ['Error', '✕'],
        ['Question circle', '？'],
        ['Smile', '☺'],
        ['Hand', '☝'],
        ['Flag', '⚐'],
    ];
    let iconModal = null;
    const openIconLibrary = (item, block, sync) => {
        iconModal?.remove();
        iconModal = document.createElement('div');
        iconModal.className = 'studio-icon-modal';
        iconModal.innerHTML =
            '<div class="studio-icon-backdrop" data-close-icon></div><div class="studio-icon-dialog"><div class="studio-icon-head"><div><p>Website Studio / Icons</p><h3>Choose an icon</h3><small>Search the icon library or select a symbol.</small></div><button type="button" data-close-icon>×</button></div><input class="studio-icon-search" type="search" placeholder="Search icons..." data-icon-search><div class="studio-icon-grid" data-icon-grid></div></div>';
        document.body.appendChild(iconModal);
        const grid = iconModal.querySelector('[data-icon-grid]');
        const renderIcons = () => {
            const query = iconModal.querySelector('[data-icon-search]').value.toLowerCase();
            grid.innerHTML =
                iconChoices
                    .filter(([name]) => !query || name.toLowerCase().includes(query))
                    .map(
                        ([name, symbol]) =>
                            `<button type="button" data-icon-value="${symbol}" title="${name}"><span>${symbol}</span><small>${name}</small></button>`,
                    )
                    .join('') || '<p class="studio-icon-empty">No icons found.</p>';
        };
        const close = () => {
            iconModal?.remove();
            iconModal = null;
        };
        iconModal.querySelectorAll('[data-close-icon]').forEach((button) => button.addEventListener('click', close));
        iconModal.querySelector('[data-icon-search]').addEventListener('input', renderIcons);
        grid.addEventListener('click', (event) => {
            const button = event.target.closest('[data-icon-value]');
            if (!button) return;
            item.icon = button.dataset.iconValue;
            const field = block.querySelector('[data-icon-field="icon"]');
            if (field) field.value = item.icon;
            sync();
            close();
        });
        renderIcons();
    };
    const animationField = (item) =>
        `<label class="widget-animation-field">Public animation<select data-field="animation"><option value="none" ${!item.animation || item.animation === 'none' ? 'selected' : ''}>None</option><option value="fade" ${item.animation === 'fade' ? 'selected' : ''}>Fade in</option><option value="slide-up" ${item.animation === 'slide-up' ? 'selected' : ''}>Slide up</option><option value="slide-left" ${item.animation === 'slide-left' ? 'selected' : ''}>Slide left</option><option value="zoom" ${item.animation === 'zoom' ? 'selected' : ''}>Zoom in</option><option value="bounce" ${item.animation === 'bounce' ? 'selected' : ''}>Bounce</option><option value="float" ${item.animation === 'float' ? 'selected' : ''}>Float</option></select></label>`;
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
        if (item.type === 'gallery') {
            const images =
                Array.isArray(item.images) && item.images.length ? item.images : [{ id: id(), url: '', alt: '' }];
            item.images = images.map((image) => ({ id: image.id || id(), url: image.url || '', alt: image.alt || '' }));
            return (
                '<div class="gallery-editor"><div class="gallery-editor-head"><strong>Gallery images</strong><button type="button" data-add-gallery-image>+ Add image</button></div><label>Gallery style<select data-field="style"><option value="grid" ' +
                (item.style === 'grid' || !item.style ? 'selected' : '') +
                '>Grid</option><option value="slider" ' +
                (item.style === 'slider' ? 'selected' : '') +
                '>Slider</option><option value="masonry" ' +
                (item.style === 'masonry' ? 'selected' : '') +
                '>Masonry</option><option value="featured" ' +
                (item.style === 'featured' ? 'selected' : '') +
                '>Featured collage</option><option value="art-wall" ' +
                (item.style === 'art-wall' ? 'selected' : '') +
                '>Heart mosaic</option></select></label><div class="gallery-images">' +
                item.images
                    .map(
                        (image, index) =>
                            '<div class="gallery-image-row"><div class="gallery-image-number">' +
                            (index + 1) +
                            '</div><div class="gallery-image-fields"><label>Image URL<input data-gallery-field="url" data-gallery-index="' +
                            index +
                            '" value="' +
                            esc(image.url) +
                            '" placeholder="https://..."></label><label>Upload image<input type="file" name="component_files[' +
                            image.id +
                            ']" data-gallery-widget-id="' +
                            item.id +
                            '" data-gallery-multi="true" data-gallery-image-index="' +
                            index +
                            '" accept="image/*"></label><label>Alt text<input data-gallery-field="alt" data-gallery-index="' +
                            index +
                            '" value="' +
                            esc(image.alt) +
                            '" placeholder="Describe this image"></label></div><button type="button" data-remove-gallery-image aria-label="Remove image">×</button></div>',
                    )
                    .join('') +
                '</div><div class="grid gap-3 sm:grid-cols-2"><label>Columns on desktop<input type="number" min="2" max="6" data-field="columns" value="' +
                Math.max(2, Math.min(6, Number(item.columns) || 3)) +
                '"></label><label>Gallery title <span class="optional">(optional)</span><input data-field="title" value="' +
                esc(item.title) +
                '" placeholder="Our community"></label></div></div>'
            );
        }
        if (item.type === 'card')
            return `<div class="card-editor"><label>Card title<input data-card-field="title" value="${esc(item.title)}" placeholder="Card title"></label><label>Description<textarea data-card-field="body" rows="3" placeholder="Card description">${esc(item.body)}</textarea></label><label>Background image URL<input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload background image<input type="file" name="component_files[${item.id}]" accept="image/*"></label><div class="card-style-fields"><label>Background color<input type="color" data-field="background_color" value="${esc(item.background_color || '#6d4aff')}"></label><label>Link <span class="optional">(optional)</span><input data-field="link" value="${esc(item.link)}" placeholder="/about or https://..."></label></div></div>`;
        if (item.type === 'icon')
            return `<div class="icon-editor"><label>Icon symbol<input data-icon-field="icon" value="${esc(item.icon || '✦')}" maxlength="8" placeholder="✦"><button type="button" class="icon-library-button" data-open-icon-library>Choose from icon library</button></label><div class="icon-style-fields"><label>Icon color<input type="color" data-field="icon_color" value="${esc(item.icon_color || '#6d4aff')}"></label><label>Background<input type="color" data-field="background_color" value="${esc(item.background_color || '#ede9fe')}"></label><label>Size (px)<input type="number" min="24" max="160" data-field="icon_size" value="${Number(item.icon_size) || 56}"></label></div><label>Alignment<select data-field="align"><option value="left" ${!item.align || item.align === 'left' ? 'selected' : ''}>Left</option><option value="center" ${item.align === 'center' ? 'selected' : ''}>Center</option><option value="right" ${item.align === 'right' ? 'selected' : ''}>Right</option></select></label><label>Link <span class="optional">(optional)</span><input data-field="link" value="${esc(item.link)}" placeholder="/about or https://..."></label></div>`;
        if (item.type === 'image')
            return `<label>Image URL <a href="${mediaLibraryUrl()}" target="_blank" class="media-library-link">Choose from media library ↗</a><input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload image<input type="file" name="component_files[${item.id}]" accept="image/*"></label><label>Alt text<input data-field="alt" value="${esc(item.alt)}"></label>`;
        if (item.type === 'video')
            return `<label>Video URL<input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload video<input type="file" name="component_files[${item.id}]" accept="video/mp4,video/webm,video/ogg"></label>`;
        if (item.type === 'button')
            return `<label>Button label<input data-field="text" value="${esc(item.text)}"></label><label>Button link<input data-field="url" value="${esc(item.url)}"></label><div class="button-style-fields"><label>Color<input type="color" data-field="button_color" value="${esc(item.button_color || '#6d4aff')}"></label><label>Size<select data-field="button_size"><option value="very-small" ${item.button_size === 'very-small' ? 'selected' : ''}>Very small</option><option value="small" ${item.button_size === 'small' ? 'selected' : ''}>Small</option><option value="medium" ${!item.button_size || item.button_size === 'medium' ? 'selected' : ''}>Medium</option><option value="big" ${item.button_size === 'big' ? 'selected' : ''}>Big</option><option value="very-big" ${item.button_size === 'very-big' ? 'selected' : ''}>Very big</option></select></label></div>`;
        const textLabel = item.type === 'heading' ? 'Heading' : item.type === 'quote' ? 'Quote' : 'Text';
        const textField = `<label>${textLabel}<textarea data-field="text" rows="3">${esc(item.text)}</textarea></label>`;
        if (item.type === 'heading' || item.type === 'text')
            return `${textField}<label>Text alignment<select data-field="align"><option value="left" ${item.align === 'left' || !item.align ? 'selected' : ''}>Left</option><option value="center" ${item.align === 'center' ? 'selected' : ''}>Center</option><option value="right" ${item.align === 'right' ? 'selected' : ''}>Right</option><option value="justify" ${item.align === 'justify' ? 'selected' : ''}>Justify</option></select></label>`;
        return textField;
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
        let draggedGroup = null;
        const groupContainsColumn = (group, target) =>
            (group.columns || []).some(
                (column) =>
                    column === target ||
                    (column.components || []).some(
                        (item) => item.type === 'columns' && groupContainsColumn(item, target),
                    ),
            );
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
                    columnEl.classList.remove('is-drop-target');
                });
                columnEl.addEventListener('dragover', (event) => {
                    if (draggedColumn) {
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        columnEl.classList.add('is-drop-target');
                    }
                });
                columnEl.addEventListener('dragleave', (event) => {
                    if (!columnEl.contains(event.relatedTarget)) columnEl.classList.remove('is-drop-target');
                });
                columnEl.addEventListener('drop', (event) => {
                    if (!draggedColumn || draggedColumn.column === column) return;
                    event.preventDefault();
                    event.stopPropagation();
                    columnEl.classList.remove('is-drop-target');
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
                                    : type === 'gallery'
                                      ? []
                                      : [],
                            autoplay: true,
                            title: type === 'card' ? 'Card title' : '',
                            body: type === 'card' ? defaults.card : '',
                            background_color: type === 'card' ? '#6d4aff' : '',
                            link: '',
                            align: 'left',
                            button_color: type === 'button' ? '#6d4aff' : '',
                            button_size: type === 'button' ? 'medium' : '',
                            icon: type === 'icon' ? '✦' : '',
                            icon_color: type === 'icon' ? '#6d4aff' : '',
                            icon_size: type === 'icon' ? 56 : 0,
                            images: type === 'gallery' ? [{ id: id(), url: '', alt: '' }] : [],
                            style: type === 'gallery' ? 'grid' : '',
                            columns: type === 'gallery' ? 3 : 0,
                        });
                        render();
                    }),
                );
                const content = columnEl.querySelector('.nested-column-content');
                content.addEventListener('dragover', (event) => {
                    if (draggedComponent || draggedGroup) {
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        content.classList.add('is-drop-target');
                    }
                });
                content.addEventListener('dragleave', (event) => {
                    if (!content.contains(event.relatedTarget)) content.classList.remove('is-drop-target');
                });
                content.addEventListener('drop', (event) => {
                    if (draggedGroup) {
                        event.preventDefault();
                        event.stopPropagation();
                        content.classList.remove('is-drop-target');
                        if (groupContainsColumn(draggedGroup.group, column)) {
                            draggedGroup = null;
                            return;
                        }
                        const sourceIndex = tree.groups.indexOf(draggedGroup.group);
                        if (sourceIndex >= 0) {
                            tree.groups.splice(sourceIndex, 1);
                            column.components.push(draggedGroup.group);
                            draggedGroup = null;
                            render();
                        }
                        return;
                    }
                    if (!draggedComponent) return;
                    event.preventDefault();
                    event.stopPropagation();
                    content.classList.remove('is-drop-target');
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
                    block.dataset.widgetId = item.id || '';
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
                        content.classList.remove('is-drop-target');
                    });
                    block.innerHTML = `<div class="widget-top"><strong>${labels[item.type] || 'Text'}</strong><button type="button" data-remove>×</button></div><div class="widget-fields">${item.type === 'image' && item.url ? `<div class="widget-media-preview"><img src="${esc(mediaUrl(item.url))}" alt="${esc(item.alt)}"></div>` : ''}${item.type === 'video' && item.url ? `<div class="widget-media-preview"><video src="${esc(mediaUrl(item.url))}" controls></video></div>` : ''}${widgetFields(item)}</div>`;
                    block.querySelector('.widget-fields')?.insertAdjacentHTML('beforeend', animationField(item));
                    block.querySelectorAll('[data-slide-field]').forEach((field) =>
                        field.addEventListener('input', () => {
                            const slide = item.slides?.[Number(field.dataset.slideIndex)];
                            if (slide) slide[field.dataset.slideField] = field.value;
                            sync();
                        }),
                    );
                    block.querySelectorAll('[data-card-field]').forEach((field) =>
                        field.addEventListener('input', () => {
                            item[field.dataset.cardField] = field.value;
                            sync();
                        }),
                    );
                    block.querySelectorAll('[data-icon-field]').forEach((field) =>
                        field.addEventListener('input', () => {
                            item[field.dataset.iconField] = field.value;
                            sync();
                        }),
                    );
                    block.querySelectorAll('[data-gallery-field]').forEach((field) =>
                        field.addEventListener('input', () => {
                            const image = item.images?.[Number(field.dataset.galleryIndex)];
                            if (image) image[field.dataset.galleryField] = field.value;
                            sync();
                        }),
                    );
                    block.querySelector('[data-add-gallery-image]')?.addEventListener('click', () => {
                        item.images.push({ id: id(), url: '', alt: '' });
                        render();
                    });
                    block.querySelectorAll('[data-remove-gallery-image]').forEach((button, imageIndex) =>
                        button.addEventListener('click', () => {
                            if (item.images.length > 1) item.images.splice(imageIndex, 1);
                            render();
                        }),
                    );
                    block
                        .querySelector('[data-open-icon-library]')
                        ?.addEventListener('click', () => openIconLibrary(item, block, sync));
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
                    block.querySelectorAll('[data-field]').forEach((field) => {
                        const updateField = () => {
                            item[field.dataset.field] = field.type === 'checkbox' ? field.checked : field.value;
                            sync();
                        };
                        field.addEventListener('input', updateField);
                        field.addEventListener('change', updateField);
                    });
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
            (tree.groups || [tree]).forEach((group, groupIndex) => {
                const groupShell = document.createElement('div');
                groupShell.className = 'column-group-shell';
                groupShell.draggable = true;
                groupShell.innerHTML = `<div class="column-group-order"><span class="group-drag-handle" title="Drag column group">⠿</span><strong>Column group ${groupIndex + 1}</strong></div>`;
                groupShell.addEventListener('dragstart', (event) => {
                    if (event.target.closest('.nested-column, .widget-block, input, textarea, button, a, select'))
                        return;
                    draggedGroup = { group, index: groupIndex };
                    draggedColumn = null;
                    draggedComponent = null;
                    groupShell.classList.add('is-dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', group.id || `group-${groupIndex}`);
                });
                groupShell.addEventListener('dragend', () => {
                    draggedGroup = null;
                    groupShell.classList.remove('is-dragging', 'is-drop-target');
                });
                groupShell.addEventListener('dragover', (event) => {
                    if (draggedGroup && draggedGroup.group !== group) {
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        groupShell.classList.add('is-drop-target');
                    }
                });
                groupShell.addEventListener('dragleave', (event) => {
                    if (!groupShell.contains(event.relatedTarget)) groupShell.classList.remove('is-drop-target');
                });
                groupShell.addEventListener('drop', (event) => {
                    if (!draggedGroup || draggedGroup.group === group) return;
                    event.preventDefault();
                    event.stopPropagation();
                    const sourceIndex = tree.groups.indexOf(draggedGroup.group);
                    let targetIndex = tree.groups.indexOf(group);
                    if (sourceIndex < 0 || targetIndex < 0) return;
                    tree.groups.splice(sourceIndex, 1);
                    if (sourceIndex < targetIndex) targetIndex -= 1;
                    tree.groups.splice(targetIndex, 0, draggedGroup.group);
                    draggedGroup = null;
                    render();
                });
                renderContainer(group, groupShell);
                stack.appendChild(groupShell);
            });
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
