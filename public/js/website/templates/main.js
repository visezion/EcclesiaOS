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
    document.querySelectorAll('[data-loop-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('.loop-carousel-track');
        const slides = [...carousel.querySelectorAll('.loop-carousel-slide')];
        if (!track || slides.length < 2) return;
        const dots = carousel.querySelector('[data-carousel-dots]');
        let current = 0;
        const render = () => {
            track.style.transform = `translateX(-${current * 100}%)`;
            dots?.querySelectorAll('button').forEach((dot, index) =>
                dot.classList.toggle('is-active', index === current),
            );
        };
        slides.forEach((_, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', `Go to slide ${index + 1}`);
            dot.addEventListener('click', () => {
                current = index;
                render();
            });
            dots?.appendChild(dot);
        });
        carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => {
            current = (current - 1 + slides.length) % slides.length;
            render();
        });
        carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => {
            current = (current + 1) % slides.length;
            render();
        });
        render();
        if (carousel.dataset.autoplay === 'true')
            window.setInterval(() => {
                current = (current + 1) % slides.length;
                render();
            }, 5000);
    });
});
