document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.querySelector('[data-theme-toggle]');
    const themeStorageKey = document.body.dataset.themeKey || 'ecclesia-site-theme';
    const defaultTheme = document.body.dataset.defaultTheme === 'light' ? 'light' : 'dark';
    let savedTheme = null;
    try {
        savedTheme = window.localStorage.getItem(themeStorageKey);
    } catch {
        savedTheme = null;
    }
    const applyTheme = (theme) => {
        const selectedTheme = theme === 'light' ? 'light' : 'dark';
        document.body.classList.toggle('theme-light', selectedTheme === 'light');
        document.body.classList.toggle('theme-dark', selectedTheme === 'dark');
        document.documentElement.style.colorScheme = selectedTheme;
        if (themeToggle) {
            themeToggle.setAttribute('aria-label', `Switch to ${selectedTheme === 'light' ? 'dark' : 'light'} mode`);
            themeToggle.setAttribute('aria-pressed', String(selectedTheme === 'light'));
        }
    };
    applyTheme(['light', 'dark'].includes(savedTheme) ? savedTheme : defaultTheme);
    themeToggle?.addEventListener('click', () => {
        const nextTheme = document.body.classList.contains('theme-light') ? 'dark' : 'light';
        applyTheme(nextTheme);
        try {
            window.localStorage.setItem(themeStorageKey, nextTheme);
        } catch {
            // The theme still changes for this page when browser storage is unavailable.
        }
    });

    const pageSectionFlow = document.querySelector('[data-page-section-flow]');
    if (pageSectionFlow) {
        let pageSectionOrder = [];
        try {
            pageSectionOrder = JSON.parse(pageSectionFlow.dataset.pageSectionOrder || '[]');
        } catch {
            pageSectionOrder = [];
        }

        const pageSectionNodes = [...pageSectionFlow.children].filter((node) => node.dataset.pageSection);
        const pageSectionByKey = new Map(pageSectionNodes.map((node) => [node.dataset.pageSection, node]));
        const orderedPageSections = [
            ...pageSectionOrder.map((key) => pageSectionByKey.get(String(key))).filter(Boolean),
            ...pageSectionNodes.filter((node) => !pageSectionOrder.includes(node.dataset.pageSection)),
        ];

        if (orderedPageSections.length === pageSectionNodes.length && pageSectionNodes.some((node, index) => node !== orderedPageSections[index])) {
            const placeholders = pageSectionNodes.map(() => document.createComment('page-section-slot'));
            pageSectionNodes.forEach((node, index) => node.replaceWith(placeholders[index]));
            orderedPageSections.forEach((node, index) => placeholders[index].replaceWith(node));
        }
    }

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
        if (!track || slides.length < 1) return;
        const dots = carousel.querySelector('[data-carousel-dots]');
        let current = 0;
        const syncBackgroundVideos = () => {
            slides.forEach((slide, index) => {
                const video = slide.querySelector('video');
                if (!video) return;
                if (index === current) {
                    video.play().catch(() => {});
                } else {
                    video.pause();
                    video.currentTime = 0;
                }
            });
        };
        const render = () => {
            track.style.transform = `translateX(-${current * 100}%)`;
            dots?.querySelectorAll('button').forEach((dot, index) =>
                dot.classList.toggle('is-active', index === current),
            );
            syncBackgroundVideos();
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
    document.querySelectorAll('[data-video-slider]').forEach((slider) => {
        const track = slider.querySelector('.video-slider-track');
        const slides = [...slider.querySelectorAll('.video-slider-slide')];
        if (!track || slides.length < 1) return;
        const dots = slider.querySelector('[data-video-slider-dots]');
        let current = 0;
        const pauseVideos = () => slider.querySelectorAll('video').forEach((video) => video.pause());
        const render = () => {
            pauseVideos();
            track.style.transform = `translateX(-${current * 100}%)`;
            dots?.querySelectorAll('button').forEach((dot, index) =>
                dot.classList.toggle('is-active', index === current),
            );
            const activeVideo = slides[current]?.querySelector('video');
            if (activeVideo && slider.dataset.autoplay === 'true') {
                activeVideo.muted = true;
                activeVideo.defaultMuted = true;
                if (activeVideo.readyState === 0) activeVideo.load();
                activeVideo.play().catch(() => {});
            }
        };
        slides.forEach((_, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', `Go to video ${index + 1}`);
            dot.addEventListener('click', () => {
                current = index;
                render();
            });
            dots?.appendChild(dot);
        });
        slider.querySelector('[data-video-slider-prev]')?.addEventListener('click', () => {
            current = (current - 1 + slides.length) % slides.length;
            render();
        });
        slider.querySelector('[data-video-slider-next]')?.addEventListener('click', () => {
            current = (current + 1) % slides.length;
            render();
        });
        render();
        if (slider.dataset.autoplay === 'true')
            window.setInterval(() => {
                current = (current + 1) % slides.length;
                render();
            }, 7000);
    });
    document.querySelectorAll('[data-background-video]').forEach((video) => {
        video.muted = true;
        video.defaultMuted = true;
        video.autoplay = true;
        video.loop = true;
        video.playsInline = true;
        const start = () => {
            if (typeof video.play === 'function') video.play().catch(() => {});
        };
        video.addEventListener('loadedmetadata', start, { once: true });
        video.addEventListener('loadeddata', start, { once: true });
        video.addEventListener('canplay', start, { once: true });
        if (video.readyState === 0) video.load();
        start();
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
    document.querySelectorAll('[data-gallery][data-gallery-style="art-wall"]').forEach((gallery) => {
        const grid = gallery.querySelector('.content-gallery-grid');
        const allItems = [...gallery.querySelectorAll('.content-gallery-item')];
        const mosaicLayout = [
            [4, 1, 4],
            [9, 1, 4],
            [15, 1, 4],
            [20, 1, 4],
            [2, 5, 4],
            [6, 5, 4],
            [10, 5, 4],
            [14, 5, 4],
            [18, 5, 4],
            [4, 9, 3],
            [7, 9, 3],
            [10, 9, 3],
            [13, 9, 3],
            [16, 9, 3],
            [19, 9, 3],
            [6, 12, 2],
            [8, 12, 2],
            [10, 12, 2],
            [12, 12, 2],
            [14, 12, 2],
            [16, 12, 2],
            [18, 12, 2],
            [8, 14, 2],
            [10, 14, 2],
            [12, 14, 2],
            [14, 14, 2],
            [16, 14, 2],
            [10, 16, 2],
            [12, 16, 2],
            [14, 16, 2],
            [11, 18, 2],
            [13, 18, 2],
            [12, 20, 2],
        ];
        const items = allItems.slice(0, mosaicLayout.length);
        if (!grid || !items.length) return;

        allItems.slice(mosaicLayout.length).forEach((item) => {
            item.hidden = true;
        });
        grid.style.gridTemplateColumns = 'repeat(24, minmax(0, 1fr))';
        items.forEach((item, index) => {
            const [x, y, size] = mosaicLayout[index];
            item.style.gridColumn = x + ' / span ' + size;
            item.style.gridRow = y + ' / span ' + size;
            item.style.animationDelay = index * 25 + 'ms';
        });
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
