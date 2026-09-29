document.querySelectorAll('[data-inline-church-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.submitting === 'true' || form.querySelector('fieldset').disabled) return;
        const result = form.closest('[data-church-form-widget]').querySelector('[data-form-result]');
        const button = form.querySelector('button[type="submit"]');
        const buttonLabel = button.querySelector('span');
        const originalLabel = buttonLabel?.textContent;
        form.dataset.submitting = 'true';
        button.disabled = true;
        if (buttonLabel) buttonLabel.textContent = 'Sending…';
        form.setAttribute('aria-busy', 'true');
        result.hidden = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            result.className = response.ok ? 'church-form-success' : 'church-form-errors';
            if (response.ok) {
                result.textContent = data.message || 'Thank you. Your message has been received.';
                form.reset();
                form.hidden = true;
            } else {
                result.textContent = response.status === 422
                    ? Object.values(data.errors || {}).flat().join(' ') || 'Please check your entries.'
                    : response.status === 429 ? 'Too many submissions. Please wait a minute and try again.'
                    : response.status === 419 ? 'Your session has expired. Refresh this page before submitting again.'
                    : response.status === 404 ? 'This form is no longer available.'
                    : 'Unable to send your message. Please try again later.';
            }
        } catch {
            result.className = 'church-form-errors';
            result.textContent = 'Connection interrupted. Your submission may have been received; please check your connection before retrying.';
        } finally {
            result.hidden = false;
            result.focus();
            button.disabled = false;
            if (buttonLabel) buttonLabel.textContent = originalLabel;
            form.dataset.submitting = 'false';
            form.removeAttribute('aria-busy');
        }
    });
});
