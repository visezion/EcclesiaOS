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
    document.querySelectorAll('[data-gallery][data-gallery-style="slider"]').forEach((gallery) => {
        const track = gallery.querySelector('.content-gallery-grid');
        const slides = [...gallery.querySelectorAll('.content-gallery-item')];
        const dots = gallery.querySelector('[data-gallery-dots]');
        if (!track || slides.length < 2) return;
        let current = 0;
        const render = () => {
            track.style.transform = 'translateX(-' + current * 100 + '%)';
            dots?.querySelectorAll('button').forEach((dot, index) =>
                dot.classList.toggle('is-active', index === current),
            );
        };
        slides.forEach((_, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', 'Show gallery image ' + (index + 1));
            dot.addEventListener('click', () => {
                current = index;
                render();
            });
            dots?.appendChild(dot);
        });
        render();
        window.setInterval(() => {
            current = (current + 1) % slides.length;
            render();
        }, 4500);
    });
    const animatedWidgets = document.querySelectorAll('.public-widget:not(.widget-animation-none)');
    if ('IntersectionObserver' in window) {
        const animationObserver = new IntersectionObserver(
            (entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.18, rootMargin: '0px 0px -8% 0px' },
        );
        animatedWidgets.forEach((widget) => animationObserver.observe(widget));
    } else {
        animatedWidgets.forEach((widget) => widget.classList.add('is-visible'));
    }
});
