document.addEventListener('DOMContentLoaded', () => {
    const seed = document.querySelector('#website-media-library');
    const media = JSON.parse(seed?.textContent || '[]');
    const appRoot = window.location.pathname.split('/public/')[0];
    const base = `${window.location.origin}${appRoot}/public/storage/`;
    const urlFor = (path) =>
        path?.startsWith('http') || path?.startsWith('//') ? path : `${base}${String(path || '').replace(/^\/+/, '')}`;
    const libraryUrl = `${window.location.origin}${appRoot}/public/website-studio/media`;
    const modal = document.createElement('div');
    modal.className = 'media-picker-modal';
    modal.innerHTML = `<div class="media-picker-backdrop" data-close-media></div><div class="media-picker-dialog"><div class="media-picker-head"><div><p>Website Studio <span>/</span> Media</p><h2>Choose an image</h2><small>Select an existing image or upload a new one.</small></div><button type="button" class="media-picker-close" data-close-media>×</button></div><div class="media-picker-toolbar"><button type="button" class="media-upload-button" data-upload-new>＋&nbsp; Upload new image</button><label class="media-search"><span>⌕</span><input type="search" placeholder="Search media..." data-media-search></label><a class="media-manage" href="${libraryUrl}" target="_blank">▱&nbsp; Manage media library ↗</a></div><div class="media-picker-tabs"><button type="button" class="is-active" data-media-tab="all">All media</button><button type="button" data-media-tab="images">Images</button><button type="button" data-media-tab="recent">Recently uploaded</button></div><div class="media-picker-grid"></div><div class="media-picker-footer"><button type="button" class="media-cancel" data-close-media>Cancel</button><button type="button" class="media-use" data-use-media disabled>✓&nbsp; Use selected image</button></div></div>`;
    document.body.appendChild(modal);
    const grid = modal.querySelector('.media-picker-grid');
    let target = null;
    let selected = null;
    let currentTab = 'all';
    let allowNative = false;
    const render = () => {
        const query = (modal.querySelector('[data-media-search]').value || '').toLowerCase();
        const filtered = media.filter((item) => {
            const matchesTab = currentTab !== 'recent' || media.indexOf(item) < 8;
            return (
                matchesTab &&
                (currentTab !== 'images' || (item.type || '').startsWith('image')) &&
                (!query ||
                    String(item.name || '')
                        .toLowerCase()
                        .includes(query))
            );
        });
        grid.innerHTML = filtered.length
            ? filtered
                  .map(
                      (item) =>
                          `<button type="button" class="media-picker-item${selected?.id === item.id ? ' is-selected' : ''}" data-media-id="${item.id}"><span class="media-picker-check">✓</span><img src="${urlFor(item.path)}" alt=""><span class="media-item-details"><strong>${item.name || 'Image'}</strong><small>${item.type || 'Image'} · Website media</small></span><b>•••</b></button>`,
                  )
                  .join('')
            : '<div class="media-picker-empty">No images match your search.</div>';
        modal.querySelector('[data-use-media]').disabled = !selected;
    };
    const close = () => {
        modal.classList.remove('is-open');
        target = null;
        selected = null;
        render();
    };
    const open = (input) => {
        target = input;
        selected = null;
        modal.classList.add('is-open');
        render();
    };
    const populateTarget = () => {
        if (!target || !selected) return;
        const block = target.closest('.widget-block');
        const slide = target.closest('.carousel-slide');
        const urlField = {
            image_file: 'image_url',
            logo_file: 'logo_url',
            hero_image_file: 'hero_image_url',
            page_hero_image_file: 'page_hero_image_url',
        }[target.name];
        const urlInput =
            slide?.querySelector('[data-slide-field="image"]') ||
            block?.querySelector('[data-field="url"]') ||
            (urlField ? target.form?.querySelector(`input[name="${urlField}"]`) : null);
        if (urlInput) {
            urlInput.value = urlFor(selected.path);
            urlInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        target.value = '';
        close();
    };
    // Upload fields can be created later by the section/column builder. Use a
    // delegated handler so every image field gets the central media picker.
    document.addEventListener(
        'click',
        (event) => {
            const input = event.target.closest?.('input[type="file"][accept*="image"]');
            if (!input) return;
            if (allowNative) {
                allowNative = false;
                return;
            }
            event.preventDefault();
            open(input);
        },
        true,
    );
    modal.querySelectorAll('[data-close-media]').forEach((button) => button.addEventListener('click', close));
    modal.querySelector('[data-use-media]').addEventListener('click', populateTarget);
    modal.querySelector('[data-upload-new]').addEventListener('click', () => {
        const input = target;
        close();
        if (input) {
            allowNative = true;
            input.click();
        }
    });
    modal.querySelector('[data-media-search]').addEventListener('input', render);
    modal.querySelectorAll('[data-media-tab]').forEach((tab) =>
        tab.addEventListener('click', () => {
            currentTab = tab.dataset.mediaTab;
            modal
                .querySelectorAll('[data-media-tab]')
                .forEach((item) => item.classList.toggle('is-active', item === tab));
            render();
        }),
    );
    grid.addEventListener('click', (event) => {
        const item = media.find((entry) => entry.id === event.target.closest('[data-media-id]')?.dataset.mediaId);
        if (item) {
            selected = item;
            render();
        }
    });
    render();
});
