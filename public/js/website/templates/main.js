document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');
    const header = document.querySelector('[data-header]');
    toggle?.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        menu?.classList.toggle('is-open', !open);
    });
    window.addEventListener('scroll', () => header?.classList.toggle('is-scrolled', window.scrollY > 12), {
        passive: true,
    });
    document
        .querySelectorAll('a[href^="#"]')
        .forEach((link) => link.addEventListener('click', () => menu?.classList.remove('is-open')));
});
