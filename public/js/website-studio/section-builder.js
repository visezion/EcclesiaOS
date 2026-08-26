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
        'video-slider': 'Slider',
        gallery: 'Gallery',
        card: 'Card',
        icon: 'Icon',
        divider: 'Divider',
        events: 'Events',
        sermons: 'Sermons',
    };
    const widgetIcons = {
        subcolumns: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="6" height="14" rx="1"/><rect x="14" y="5" width="6" height="14" rx="1"/></svg>',
        heading: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5v14M19 5v14M5 12h14M5 5h4M15 5h4M5 19h4M15 19h4"/></svg>',
        text: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h14M5 12h14M5 18h9"/></svg>',
        quote: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8h4v4H7l-2 4M15 8h4v4h-4l-2 4"/></svg>',
        image: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m5 17 4-4 3 3 2-2 5 4"/></svg>',
        video: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/></svg>',
        button: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 4 6 15 2-6 6-2z"/></svg>',
        spacer: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4"/></svg>',
        carousel: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7H4v3M4 10a8 8 0 0 1 14-3M17 17h3v-3M20 14a8 8 0 0 1-14 3"/></svg>',
        'video-slider': '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>',
        gallery: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>',
        card: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
        icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 4 2.5 5 5.5.8-4 3.9.9 5.5-4.9-2.6-4.9 2.6.9-5.5-4-3.9 5.5-.8z"/></svg>',
        divider: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16"/></svg>',
        events: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>',
        sermons: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V7a3 3 0 0 0-3-3zM6 11a6 6 0 0 0 12 0M12 17v4M9 21h6"/></svg>',
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
        'video-slider': '',
        gallery: '',
        card: 'A welcoming card',
        icon: '✦',
        divider: '',
        events: '',
        sermons: '',
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
        if (item.type === 'divider')
            return `<div class="widget-field-grid"><label>Line style<select data-field="divider_style"><option value="solid" ${!item.divider_style || item.divider_style === 'solid' ? 'selected' : ''}>Solid</option><option value="dashed" ${item.divider_style === 'dashed' ? 'selected' : ''}>Dashed</option><option value="dotted" ${item.divider_style === 'dotted' ? 'selected' : ''}>Dotted</option></select></label><label>Color<input type="color" data-field="divider_color" value="${esc(item.divider_color || '#e2e8f0')}"></label><label>Width (%)<input type="number" min="10" max="100" step="1" data-field="divider_width" value="${Math.max(10, Math.min(100, Number(item.divider_width) || 100))}"></label><label>Thickness (px)<input type="number" min="1" max="8" step="1" data-field="divider_thickness" value="${Math.max(1, Math.min(8, Number(item.divider_thickness) || 1))}"></label><label>Spacing (px)<input type="number" min="0" max="120" step="1" data-field="divider_spacing" value="${Math.max(0, Math.min(120, Number(item.divider_spacing) || 24))}"></label></div>`;
        if (item.type === 'events')
            return `<div class="widget-field-grid"><label>Show events<select data-field="event_limit"><option value="all" ${!item.event_limit || item.event_limit === 'all' ? 'selected' : ''}>Show all upcoming</option>${[3, 4, 6, 8].map((limit) => `<option value="${limit}" ${Number(item.event_limit) === limit ? 'selected' : ''}>Show upcoming ${limit}</option>`).join('')}</select></label><label>Design<select data-field="event_style"><option value="list" ${!item.event_style || item.event_style === 'list' ? 'selected' : ''}>List</option><option value="gallery" ${item.event_style === 'gallery' ? 'selected' : ''}>Gallery</option></select></label></div><div class="widget-field-grid"><label>Button color<input type="color" data-field="event_button_color" value="${esc(item.event_button_color || '#6d4aff')}"></label><label>Button text color<input type="color" data-field="event_button_text_color" value="${esc(item.event_button_text_color || '#ffffff')}"></label></div><span class="widget-hint">Events are pulled automatically from your upcoming church calendar.</span>`;
        if (item.type === 'sermons')
            return `<label>Show sermons<select data-field="sermon_limit"><option value="all" ${!item.sermon_limit || item.sermon_limit === 'all' ? 'selected' : ''}>Show all</option>${[3, 4, 5, 6, 8, 9].map((limit) => `<option value="${limit}" ${Number(item.sermon_limit) === limit ? 'selected' : ''}>Show recent ${limit}</option>`).join('')}</select></label><span class="widget-hint">Published sermons are loaded automatically from the sermon library.</span>`;
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
                video: slide.video || '',
                title: slide.title || '',
                text: slide.text || '',
                link: slide.link || '',
            }));
            item.slides = slides;
            return `<div class="carousel-editor"><div class="carousel-editor-head"><strong>Slides</strong><button type="button" data-add-slide>+ Add slide</button></div>${slides
                .map(
                    (slide, index) =>
                        `<div class="carousel-slide" data-slide-index="${index}"><div class="carousel-slide-head"><b>Slide ${index + 1}</b><button type="button" data-remove-slide>Remove</button></div><label>Image URL<input data-slide-field="image" data-slide-index="${index}" value="${esc(slide.image)}" placeholder="https://..."></label><label>Upload image<input type="file" name="component_image_files[${slide.id || id()}]" accept="image/*"></label><label>Video background URL <span class="optional">(optional)</span><input data-slide-field="video" data-slide-index="${index}" value="${esc(slide.video)}" placeholder="https://..."></label><label>Upload video background<input type="file" name="component_video_files[${slide.id || id()}]" accept="video/mp4,video/webm,video/ogg"></label><span class="widget-hint">When a video is provided, it plays muted and loops behind this slide.</span><label>Heading<input data-slide-field="title" data-slide-index="${index}" value="${esc(slide.title)}" placeholder="Slide heading"></label><label>Text<textarea data-slide-field="text" data-slide-index="${index}" rows="2" placeholder="Short message">${esc(slide.text)}</textarea></label><label>Link <span class="optional">(optional)</span><input data-slide-field="link" data-slide-index="${index}" value="${esc(slide.link)}" placeholder="/about or https://..."></label></div>`,
                )
                .join(
                    '',
                )}</div><label class="carousel-option"><input type="checkbox" data-field="autoplay" ${item.autoplay !== false ? 'checked' : ''}> Auto-play slides</label>`;
        }
        if (item.type === 'video-slider') {
            const slides = (
                Array.isArray(item.slides) && item.slides.length
                    ? item.slides
                    : [{ id: id(), video: '', image: '', title: 'New video', text: '', link: '' }]
            ).map((slide) => ({
                id: slide.id || id(),
                video: slide.video || '',
                image: slide.image || '',
                title: slide.title || '',
                text: slide.text || '',
                link: slide.link || '',
            }));
            item.slides = slides;
            return `<div class="carousel-editor"><div class="carousel-editor-head"><strong>Slider slides</strong><button type="button" data-add-slide>+ Add video</button></div><label>Slider height<select data-field="video_slider_height"><option value="300" ${Number(item.video_slider_height) === 300 ? 'selected' : ''}>Compact</option><option value="420" ${Number(item.video_slider_height) === 420 || !item.video_slider_height ? 'selected' : ''}>Standard</option><option value="560" ${Number(item.video_slider_height) === 560 ? 'selected' : ''}>Tall</option><option value="700" ${Number(item.video_slider_height) === 700 ? 'selected' : ''}>Extra tall</option></select></label>${slides
                .map(
                    (slide, index) =>
                        `<div class="carousel-slide" data-slide-index="${index}"><div class="carousel-slide-head"><b>Video ${index + 1}</b><button type="button" data-remove-slide>Remove</button></div><label>Video URL<input data-slide-field="video" data-slide-index="${index}" value="${esc(slide.video)}" placeholder="https://..."></label><label>Upload video<input type="file" name="component_video_files[${slide.id || id()}]" accept="video/mp4,video/webm,video/ogg"></label><label>Image URL <span class="optional">(optional)</span><input data-slide-field="image" data-slide-index="${index}" value="${esc(slide.image)}" placeholder="Fallback image URL"></label><label>Upload image <span class="optional">(optional)</span><input type="file" name="component_image_files[${slide.id || id()}]" accept="image/*"></label><span class="widget-hint">The image is used as the video poster and is shown when no video is provided.</span><label>Heading<input data-slide-field="title" data-slide-index="${index}" value="${esc(slide.title)}" placeholder="Video heading"></label><label>Text<textarea data-slide-field="text" data-slide-index="${index}" rows="2" placeholder="Short message">${esc(slide.text)}</textarea></label><label>Link <span class="optional">(optional)</span><input data-slide-field="link" data-slide-index="${index}" value="${esc(slide.link)}" placeholder="/about or https://..."></label></div>`,
                )
                .join('')}</div><label class="carousel-option"><input type="checkbox" data-field="autoplay" ${item.autoplay !== false ? 'checked' : ''}> Auto-play videos</label>`;
        }
        if (item.type === 'gallery') {
            const images =
                Array.isArray(item.images) && item.images.length ? item.images : [{ id: id(), url: '', alt: '' }];
            item.images = images.map((image) => ({
                id: image.id || id(),
                url: image.url || '',
                alt: image.alt || '',
                position: image.position || 'center',
            }));
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
                            '" placeholder="Describe this image"></label><label>Crop focus<select data-gallery-field="position" data-gallery-index="' +
                            index +
                            '"><option value="center" ' +
                            (image.position === 'center' || !image.position ? 'selected' : '') +
                            '>Center</option><option value="top" ' +
                            (image.position === 'top' ? 'selected' : '') +
                            '>Top</option><option value="bottom" ' +
                            (image.position === 'bottom' ? 'selected' : '') +
                            '>Bottom</option><option value="left" ' +
                            (image.position === 'left' ? 'selected' : '') +
                            '>Left</option><option value="right" ' +
                            (image.position === 'right' ? 'selected' : '') +
                            '>Right</option></select></label></div><button type="button" data-remove-gallery-image aria-label="Remove image">×</button></div>',
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
            return `<div class="card-editor"><label>Card title<input data-card-field="title" value="${esc(item.title)}" placeholder="Card title"></label><label>Description<textarea data-card-field="body" rows="3" placeholder="Card description">${esc(item.body)}</textarea></label><label>Background image URL <span class="optional">(optional)</span><input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload background image<input type="file" name="component_image_files[${item.id}]" accept="image/*"></label><label>Background video URL <span class="optional">(optional)</span><input data-field="background_video" value="${esc(item.background_video)}" placeholder="https://..."></label><label>Upload background video<input type="file" name="component_video_files[${item.id}]" accept="video/mp4,video/webm,video/ogg"></label><span class="widget-hint">Video backgrounds play muted and loop automatically.</span><div class="card-style-fields"><label>Background color<input type="color" data-field="background_color" value="${esc(item.background_color || '#6d4aff')}"></label><label>Border color<input type="color" data-field="card_border_color" value="${esc(item.card_border_color || '#ffffff')}"></label><label>Border size (px)<input type="number" min="0" max="12" data-field="card_border_width" value="${Math.max(0, Math.min(12, Number(item.card_border_width) || 0))}"></label><label>Shadow<select data-field="card_shadow"><option value="none" ${!item.card_shadow || item.card_shadow === 'none' ? 'selected' : ''}>None</option><option value="small" ${item.card_shadow === 'small' ? 'selected' : ''}>Small</option><option value="medium" ${item.card_shadow === 'medium' ? 'selected' : ''}>Medium</option><option value="large" ${item.card_shadow === 'large' ? 'selected' : ''}>Large</option></select></label><label class="card-link-field">Card link <span class="optional">(optional)</span><input data-card-field="link" value="${esc(item.link)}" placeholder="/about or https://..."></label></div></div>`;
        if (item.type === 'icon')
            return `<div class="icon-editor"><label>Icon symbol<input data-icon-field="icon" value="${esc(item.icon || '✦')}" maxlength="8" placeholder="✦"><button type="button" class="icon-library-button" data-open-icon-library>Choose from icon library</button></label><div class="icon-style-fields"><label>Icon color<input type="color" data-field="icon_color" value="${esc(item.icon_color || '#6d4aff')}"></label><label>Background<input type="color" data-field="background_color" value="${esc(item.background_color || '#ede9fe')}"></label><label>Size (px)<input type="number" min="24" max="160" data-field="icon_size" value="${Number(item.icon_size) || 56}"></label></div><label>Alignment<select data-field="align"><option value="left" ${!item.align || item.align === 'left' ? 'selected' : ''}>Left</option><option value="center" ${item.align === 'center' ? 'selected' : ''}>Center</option><option value="right" ${item.align === 'right' ? 'selected' : ''}>Right</option></select></label><label>Link <span class="optional">(optional)</span><input data-field="link" value="${esc(item.link)}" placeholder="/about or https://..."></label></div>`;
        if (item.type === 'image')
            return `<label>Image URL <a href="${mediaLibraryUrl()}" target="_blank" class="media-library-link">Choose from media library ↗</a><input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload image<input type="file" name="component_image_files[${item.id}]" accept="image/*"></label><label>Alt text<input data-field="alt" value="${esc(item.alt)}"></label>`;
        if (item.type === 'video')
            return `<label>Video URL<input data-field="url" value="${esc(item.url)}" placeholder="https://..."></label><label>Upload video<input type="file" name="component_video_files[${item.id}]" accept="video/mp4,video/webm,video/ogg"></label>`;
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
            id: column.id || id(),
            width: Number(widths[index] || column.width) || 1,
            background_color: column.background_color || 'transparent',
            background_transparent: column.background_color === 'transparent' || Boolean(column.background_transparent),
            background_image: column.background_image || '',
            background_video: column.background_video || '',
            height: column.height || 'auto',
            column_width: column.column_width || column.content_width || 'default',
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
                container.columns.push({ id: id(), width: 1, background_color: 'transparent', background_transparent: true, background_image: '', background_video: '', height: 'auto', column_width: 'default', components: [] });
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
                columnEl.innerHTML = `<div class="nested-column-heading"><strong><span class="column-drag-handle" title="Drag column">⠿</span> Column ${columnIndex + 1}</strong><label><span>Width <b data-width-value>—</b></span><input type="number" min="1" max="95" value="${column.width}" data-width aria-label="Column ${columnIndex + 1} width"></label></div><div class="nested-column-actions"><button type="button" data-add-subcolumns><span class="widget-action-icon">${widgetIcons.subcolumns}</span><span>+ Sub-columns</span></button>${Object.keys(
                    labels,
                )
                    .map((type) => `<button type="button" data-add="${type}"><span class="widget-action-icon">${widgetIcons[type]}</span><span>+ ${labels[type]}</span></button>`)
                    .join('')}</div><div class="nested-column-content"></div>`;
                columnEl.querySelector('.nested-column-heading').insertAdjacentHTML('afterend', `<div class="column-style-fields"><label>Background color<input type="color" data-column-field="background_color" value="${esc(column.background_color === 'transparent' ? '#ffffff' : (column.background_color || '#ffffff'))}"><span class="column-transparent-toggle"><input type="checkbox" data-column-field="background_transparent" ${column.background_transparent ? 'checked' : ''}> Transparent</span></label><label>Background image URL<input data-column-field="background_image" value="${esc(column.background_image || '')}" placeholder="https://..."></label><label>Upload background image<input type="file" name="component_image_files[${column.id || id()}]" accept="image/*"></label><label>Background video URL<input data-column-field="background_video" value="${esc(column.background_video || '')}" placeholder="https://..."></label><label>Upload background video<input type="file" name="component_video_files[${column.id || id()}]" accept="video/mp4,video/webm,video/ogg"></label><label>Column height<select data-column-field="height"><option value="auto" ${!column.height || column.height === 'auto' ? 'selected' : ''}>Fit content</option><option value="compact" ${column.height === 'compact' ? 'selected' : ''}>Compact</option><option value="tall" ${column.height === 'tall' ? 'selected' : ''}>Tall</option><option value="full" ${column.height === 'full' ? 'selected' : ''}>Full height</option></select></label><label>Column width<select data-column-field="column_width"><option value="default" ${!column.column_width || column.column_width === 'default' ? 'selected' : ''}>Default width</option><option value="wide" ${column.column_width === 'wide' ? 'selected' : ''}>Wide</option><option value="full" ${column.column_width === 'full' ? 'selected' : ''}>Full width</option></select></label></div>`);
                columnEl.querySelector('.column-style-fields').insertAdjacentHTML('afterbegin', '<div class="column-style-header"><span class="column-style-icon">▧</span><div><strong>Background</strong><small>Set the background style and add content blocks to build your layout.</small></div></div>');
                ['image', 'video'].forEach((mediaType) => {
                    const fieldName = mediaType === 'image' ? 'background_image' : 'background_video';
                    const field = columnEl.querySelector(`[data-column-field="${fieldName}"]`);
                    const uploadField = columnEl.querySelectorAll('.column-style-fields input[type="file"]')[mediaType === 'image' ? 0 : 1];
                    if (!field) return;
                    const uploadLabel = uploadField?.closest('label');
                    if (uploadLabel) {
                        uploadLabel.classList.add('column-upload-label');
                        const labelText = [...uploadLabel.childNodes].find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
                        if (labelText) {
                            const title = document.createElement('span');
                            title.className = 'column-upload-title';
                            title.textContent = mediaType === 'image' ? 'Background image' : 'Background video';
                            labelText.replaceWith(title);
                        }
                        const uploadIcon = document.createElement('span');
                        uploadIcon.className = `column-upload-icon ${mediaType}`;
                        uploadIcon.innerHTML = mediaType === 'image'
                            ? '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m5 17 4-4 3 3 2-2 5 4"/></svg>'
                            : '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/></svg>';
                        uploadLabel.prepend(uploadIcon);
                    }
                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'column-remove-background';
                    removeButton.textContent = 'Remove';
                    removeButton.hidden = !column[fieldName];
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = `remove_column_background_${mediaType}s[${column.id}]`;
                    hidden.value = '0';
                    if (uploadLabel) {
                        const mediaControl = document.createElement('div');
                        mediaControl.className = 'column-media-control';
                        uploadLabel.parentNode.insertBefore(mediaControl, uploadLabel);
                        mediaControl.append(uploadLabel, removeButton);
                    } else {
                        field.closest('label').after(removeButton);
                    }
                    field.closest('label').after(hidden);
                    removeButton.addEventListener('click', () => {
                        hidden.value = '1';
                        field.value = '';
                        removeButton.textContent = 'Removed on save';
                        removeButton.classList.add('is-removed');
                    });
                });
                columnEl.querySelectorAll('[data-column-field]').forEach((field) => {
                    const updateColumnField = () => {
                        column[field.dataset.columnField] = field.type === 'checkbox' ? field.checked : field.value;
                        if (field.dataset.columnField === 'background_color') {
                            column.background_transparent = false;
                            const transparencyToggle = columnEl.querySelector('[data-column-field="background_transparent"]');
                            if (transparencyToggle) transparencyToggle.checked = false;
                        }
                        if (field.dataset.columnField === 'background_transparent') {
                            column.background_transparent = field.checked;
                        }
                        sync();
                    };
                    field.addEventListener('input', updateColumnField);
                    field.addEventListener('change', updateColumnField);
                });
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
                            { id: id(), width: 1, background_color: 'transparent', background_transparent: true, background_image: '', background_video: '', height: 'auto', column_width: 'default', components: [] },
                            { id: id(), width: 1, background_color: 'transparent', background_transparent: true, background_image: '', background_video: '', height: 'auto', column_width: 'default', components: [] },
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
                                    : type === 'video-slider'
                                      ? [{ id: id(), video: '', image: '', title: 'New video', text: '', link: '' }]
                                    : type === 'gallery'
                                      ? []
                                      : [],
                            autoplay: true,
                            title: type === 'card' ? 'Card title' : '',
                            body: type === 'card' ? defaults.card : '',
                            background_color: type === 'card' ? '#6d4aff' : '',
                            background_video: type === 'card' ? '' : '',
                            card_border_width: type === 'card' ? 0 : 0,
                            card_border_color: type === 'card' ? '#ffffff' : '',
                            card_shadow: type === 'card' ? 'none' : '',
                            link: '',
                            align: 'left',
                            button_color: type === 'button' ? '#6d4aff' : '',
                            button_size: type === 'button' ? 'medium' : '',
                            icon: type === 'icon' ? '✦' : '',
                            icon_color: type === 'icon' ? '#6d4aff' : '',
                            icon_size: type === 'icon' ? 56 : 0,
                            images: type === 'gallery' ? [{ id: id(), url: '', alt: '', position: 'center' }] : [],
                            style: type === 'gallery' ? 'grid' : '',
                            columns: type === 'gallery' ? 3 : 0,
                            video_slider_height: type === 'video-slider' ? 420 : 0,
                            divider_style: type === 'divider' ? 'solid' : '',
                            divider_color: type === 'divider' ? '#e2e8f0' : '',
                            divider_width: type === 'divider' ? 100 : 0,
                            divider_thickness: type === 'divider' ? 1 : 0,
                            divider_spacing: type === 'divider' ? 24 : 0,
                            event_limit: type === 'events' ? 3 : 0,
                            event_button_color: type === 'events' ? '#6d4aff' : '',
                            event_button_text_color: type === 'events' ? '#ffffff' : '',
                            event_style: type === 'events' ? 'list' : '',
                            sermon_limit: type === 'sermons' ? 'all' : 'all',
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
                        item.images.push({ id: id(), url: '', alt: '', position: 'center' });
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
                        item.slides.push(
                            item.type === 'video-slider'
                                ? { id: id(), video: '', title: 'New video', text: '', link: '' }
                                : { id: id(), image: '', title: 'New slide', text: '', link: '' },
                        );
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
