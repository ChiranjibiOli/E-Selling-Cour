(() => {
  'use strict';

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const nav = document.querySelector('[data-landing-nav]');
  const menuButton = document.querySelector('[data-landing-menu]');
  const links = document.querySelector('[data-landing-links]');
  const heroVisual = document.querySelector('[data-hero-visual]');
  const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));

  const updateNavigation = () => {
    nav?.classList.toggle('is-compact', window.scrollY > 52);
    if (!reducedMotion && heroVisual instanceof HTMLElement) {
      const movement = Math.min(22, window.scrollY * 0.035);
      heroVisual.style.transform = `translate3d(0, ${movement}px, 0)`;
    }
  };

  let scrollFrame = 0;
  const queueNavigationUpdate = () => {
    if (scrollFrame) return;
    scrollFrame = window.requestAnimationFrame(() => {
      scrollFrame = 0;
      updateNavigation();
    });
  };

  window.addEventListener('scroll', queueNavigationUpdate, { passive: true });
  window.addEventListener('resize', queueNavigationUpdate, { passive: true });
  updateNavigation();

  menuButton?.addEventListener('click', () => {
    const open = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!open));
    links?.classList.toggle('is-open', !open);
  });

  links?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      menuButton?.setAttribute('aria-expanded', 'false');
      links.classList.remove('is-open');
    });
  });

  if (reducedMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  } else {
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
  }

  const sections = Array.from(document.querySelectorAll('section[id]'));
  const navLinks = Array.from(document.querySelectorAll('.landing-links a[href^="#"]'));
  if ('IntersectionObserver' in window && navLinks.length > 0) {
    const sectionObserver = new IntersectionObserver((entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (!visible) return;
      navLinks.forEach((link) => {
        link.classList.toggle('active', link.getAttribute('href') === `#${visible.target.id}`);
      });
    }, { threshold: [0.25, 0.45, 0.65], rootMargin: '-20% 0px -55% 0px' });
    sections.forEach((section) => sectionObserver.observe(section));
  }
})();
