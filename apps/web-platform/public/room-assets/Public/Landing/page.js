(() => {
  'use strict';

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const nav = document.querySelector('[data-landing-nav]');
  const menuButton = document.querySelector('[data-landing-menu]');
  const links = document.querySelector('[data-landing-links]');
  const heroVisual = document.querySelector('[data-hero-visual]');
  const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));
  const navLinks = Array.from(document.querySelectorAll('.landing-links a[data-nav-section]'));
  const trackedSections = Array.from(document.querySelectorAll('[data-public-section]'));

  const setActiveSection = (sectionId) => {
    if (!(nav instanceof HTMLElement)) return;

    let changed = false;
    navLinks.forEach((link) => {
      const active = link.getAttribute('data-nav-section') === sectionId;
      if (link.classList.contains('active') !== active) changed = true;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });

    if (changed && !reducedMotion) {
      nav.classList.remove('nav-active-shift');
      void nav.offsetWidth;
      nav.classList.add('nav-active-shift');
      window.setTimeout(() => nav.classList.remove('nav-active-shift'), 320);
    }
  };

  const findCurrentSection = () => {
    const marker = window.scrollY + Math.min(window.innerHeight * 0.34, 310);
    let current = 'top';

    trackedSections.forEach((section) => {
      if (!(section instanceof HTMLElement)) return;
      if (section.offsetTop <= marker) {
        current = section.getAttribute('data-public-section') || current;
      }
    });

    return current;
  };

  const updateNavigation = () => {
    if (nav instanceof HTMLElement) {
      nav.classList.toggle('is-compact', window.scrollY > 52);
      setActiveSection(findCurrentSection());
    }

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
})();
