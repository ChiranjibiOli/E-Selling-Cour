(() => {
  'use strict';

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const heroVisual = document.querySelector('[data-hero-visual]');
  const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));

  if (!reducedMotion && heroVisual instanceof HTMLElement) {
    let frame = 0;
    const updateHero = () => {
      frame = 0;
      const movement = Math.min(22, window.scrollY * 0.035);
      heroVisual.style.transform = `translate3d(0, ${movement}px, 0)`;
    };
    const queueHero = () => {
      if (frame) return;
      frame = window.requestAnimationFrame(updateHero);
    };
    updateHero();
    window.addEventListener('scroll', queueHero, { passive: true });
    window.addEventListener('resize', queueHero, { passive: true });
  }

  if (reducedMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

  revealItems.forEach((item, index) => {
    if (item instanceof HTMLElement) {
      item.style.transitionDelay = `${Math.min(index % 4, 3) * 70}ms`;
    }
    observer.observe(item);
  });
})();
