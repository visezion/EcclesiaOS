document.addEventListener('DOMContentLoaded', () => {
    const seed = document.querySelector('#website-media-library');
    let media = JSON.parse(seed?.textContent || '[]');
    const appRoot = window.location.pathname.split('/public/')[0];
    const base = `${window.location.origin}${appRoot}/public/storage/`;
    const urlFor = (path) =>
        path?.startsWith('http') || path?.startsWith('//') ? path : `${base}${String(path || '').replace(/^\/+/, '')}`;
    const libraryUrl = `${window.location.origin}${appRoot}/public/website-studio/media`;
    const uploadUrl = libraryUrl;
    const modal = document.createElement('div');
    modal.className = 'media-picker-modal';
    modal.innerHTML = `<div class="media-picker-backdrop" data-close-media></div><div class="media-picker-dialog"><div class="media-picker-head"><div><p>Website Studio <span>/</span> Media</p><h2>Choose an image</h2><small>Select an existing image or upload a new one.</small></div><button type="button" class="media-picker-close" data-close-media>×</button></div><div class="media-picker-toolbar"><button type="button" class="media-upload-button" data-upload-new>＋&nbsp; Upload new image</button><label class="media-search"><span>⌕</span><input type="search" placeholder="Search media..." data-media-search></label><a class="media-manage" href="${libraryUrl}" target="_blank">▱&nbsp; Manage media library ↗</a></div><div class="media-picker-tabs"><button type="button" class="is-active" data-media-tab="all">All media</button><button type="button" data-media-tab="images">Images</button><button type="button" data-media-tab="recent">Recently uploaded</button></div><div class="media-picker-grid"></div><div class="media-picker-footer"><button type="button" class="media-cancel" data-close-media>Cancel</button><button type="button" class="media-use" data-use-media disabled>✓&nbsp; Use selected image</button></div></div>`;
    document.body.appendChild(modal);
    const grid = modal.querySelector('.media-picker-grid');
    let target = null;
    let selected = null;
    let currentTab = 'all';
    let allowNative = false;
    let nativeUpload = false;
    const render = () => {
        const multiple = target?.dataset.galleryMulti === 'true';
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
        if (multiple) {
            grid.querySelectorAll('[data-media-id]').forEach((button) => {
                button.classList.toggle(
                    'is-selected',
                    selected.some((entry) => entry.id === button.dataset.mediaId),
                );
            });
        }
        modal.querySelector('[data-use-media]').disabled = multiple ? !selected.length : !selected;
        modal.querySelector('[data-use-media]').textContent = multiple
            ? '✓  Use selected images'
            : '✓  Use selected image';
    };
    const close = () => {
        modal.classList.remove('is-open');
        target = null;
        selected = null;
        render();
    };
    const open = (input) => {
        target = input;
        selected = input.dataset.galleryMulti === 'true' ? [] : null;
        modal.querySelector('.media-picker-head small').textContent =
            input.dataset.galleryMulti === 'true'
                ? 'Select one or more images for this gallery.'
                : 'Select an existing image or upload a new one.';
        modal.classList.add('is-open');
        render();
    };
    const populateTarget = () => {
        const multiple = target?.dataset.galleryMulti === 'true';
        if (!target || (multiple ? !selected.length : !selected)) return;
        const block = target.closest('.widget-block');
        if (multiple) {
            const start = Number(target.dataset.galleryImageIndex || 0);
            selected.forEach((entry, offset) => {
                if (offset > 0) {
                    document
                        .querySelector('.widget-block[data-widget-id="' + target.dataset.galleryWidgetId + '"]')
                        ?.querySelector('[data-add-gallery-image]')
                        ?.click();
                }
                const galleryBlock =
                    document.querySelector('.widget-block[data-widget-id="' + target.dataset.galleryWidgetId + '"]') ||
                    block;
                const input = galleryBlock?.querySelector(
                    '[data-gallery-field="url"][data-gallery-index="' + (start + offset) + '"]',
                );
                if (input) {
                    input.value = urlFor(entry.path);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            target.value = '';
            close();
            return;
        }
        const slide = target.closest('.carousel-slide');
        const urlField = {
            image_file: 'image_url',
            logo_file: 'logo_url',
            hero_image_file: 'hero_image_url',
            page_hero_image_file: 'page_hero_image_url',
        }[target.name];
        const urlInput =
            slide?.querySelector('[data-slide-field="image"]') ||
            (target.dataset.galleryImageIndex
                ? block?.querySelector(
                      '[data-gallery-field="url"][data-gallery-index="' + target.dataset.galleryImageIndex + '"]',
                  )
                : null) ||
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
        if (input) {
            modal.classList.remove('is-open');
            nativeUpload = true;
            allowNative = true;
            input.click();
        }
    });
    document.addEventListener('change', (event) => {
        const input = event.target.closest?.('input[type="file"][accept*="image"]');
        if (!input || !nativeUpload || !input.files?.[0]) return;

        nativeUpload = false;
        const form = new FormData();
        form.append('media', input.files[0]);
        modal.classList.add('is-open');
        grid.innerHTML = '<div class="media-picker-empty">Uploading image…</div>';

        fetch(uploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: form,
        })
            .then((response) => {
                if (!response.ok) throw new Error('Upload failed');
                return response.json();
            })
            .then((payload) => {
                if (!payload.media) throw new Error('Uploaded media was not returned');
                media.unshift(payload.media);
                selected = input.dataset.galleryMulti === 'true' ? [payload.media] : payload.media;
                currentTab = 'all';
                render();
            })
            .catch(() => {
                grid.innerHTML = '<div class="media-picker-empty">Upload failed. Please try again.</div>';
            })
            .finally(() => {
                input.value = '';
            });
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
            if (target?.dataset.galleryMulti === 'true') {
                const index = selected.findIndex((entry) => entry.id === item.id);
                if (index >= 0) selected.splice(index, 1);
                else selected.push(item);
            } else {
                selected = item;
            }
            render();
        }
    });
    render();
});
