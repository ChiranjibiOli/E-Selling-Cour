(() => {
    'use strict';

    const root = document.documentElement;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const revealItems = [...document.querySelectorAll('.reveal-on-scroll')];
    const parallaxItems = [...document.querySelectorAll('[data-parallax]')];
    const stackCards = [...document.querySelectorAll('[data-stack-card]')];

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

        revealItems.forEach((item) => revealObserver.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    if (reduceMotion || (!parallaxItems.length && !stackCards.length)) {
        root.classList.add('reduced-motion');
        return;
    }

    let ticking = false;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const updateMotion = () => {
        const viewportHeight = window.innerHeight || 1;

        parallaxItems.forEach((item) => {
            const rect = item.getBoundingClientRect();
            if (rect.bottom < -120 || rect.top > viewportHeight + 120) return;
            const speed = Number.parseFloat(item.dataset.parallaxSpeed || '0.04');
            const distance = (rect.top + rect.height / 2) - viewportHeight / 2;
            item.style.setProperty('--parallax-y', `${distance * speed}px`);
        });

        stackCards.forEach((card) => {
            const rect = card.getBoundingClientRect();
            const stickyTop = 106 + (Number.parseInt(card.style.getPropertyValue('--stack-index'), 10) || 0) * 11;
            const progress = clamp((stickyTop - rect.top) / Math.max(rect.height, 1), 0, 1);
            card.style.setProperty('--stack-scale', String(1 - progress * 0.025));
            card.style.setProperty('--stack-brightness', String(1 - progress * 0.08));
        });

        ticking = false;
    };

    const requestMotionUpdate = () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(updateMotion);
    };

    window.addEventListener('scroll', requestMotionUpdate, { passive: true });
    window.addEventListener('resize', requestMotionUpdate, { passive: true });
    requestMotionUpdate();
})();
