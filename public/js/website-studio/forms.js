document.querySelectorAll('[data-form-designer]').forEach((designer) => {
    const form = designer.querySelector('form');
    const preview = designer.querySelector('[data-form-preview]');
    const refresh = () => {
        const data = new FormData(form);
        const accent = data.get('accent_color');
        preview.style.setProperty('--form-accent', accent);
        preview.style.setProperty('--form-background', data.get('background_color'));
        preview.style.setProperty('--form-radius', data.get('style') === 'square' ? '0' : '16px');
        preview.style.setProperty('--form-gap', data.get('spacing') === 'compact' ? '12px' : '24px');
        const rgb = accent.match(/[a-f\d]{2}/gi).map(v => parseInt(v, 16) / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
        preview.style.setProperty('--form-button-text', rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722 > .179 ? '#000' : '#fff');
        preview.querySelector('[data-preview-title]').textContent = data.get('title');
        preview.querySelector('[data-preview-description]').textContent = data.get('description');
        preview.querySelector('[data-preview-message]').textContent = data.get('message_label') + ' *';
        preview.querySelector('[data-preview-button]').textContent = data.get('submit_label');
        preview.querySelector('[data-preview-email]').textContent = form.querySelector('[type="checkbox"][name="require_email"]').checked ? 'Email *' : 'Email (optional)';
        form.elements.assigned_to.required = data.get('action') === 'assign';
    };
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh();
});
