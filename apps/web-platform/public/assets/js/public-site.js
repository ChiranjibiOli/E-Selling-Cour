(() => {
  'use strict';

  if (!document.querySelector('link[data-public-site-fixes]')) {
    const polish = document.createElement('link');
    polish.rel = 'stylesheet';
    polish.href = '/assets/css/public-site-fixes.css?v=20260728-2';
    polish.dataset.publicSiteFixes = 'true';
    document.head.appendChild(polish);
  }

  const nav = document.querySelector('[data-public-site-nav]');
  if (!(nav instanceof HTMLElement)) return;

  const menuButton = nav.querySelector('[data-public-site-menu]');
  const navLinks = Array.from(nav.querySelectorAll('.public-site-links a'));
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const normalPath = (path) => {
    const cleaned = path.replace(/\/+$/, '');
    return cleaned === '' ? '/' : cleaned;
  };

  const currentPath = normalPath(window.location.pathname);
  const activeDestination = (() => {
    if (currentPath === '/') return '/';
    if (currentPath === '/courses' || currentPath === '/course' || currentPath === '/search') return '/courses';
    if (['/about', '/pricing', '/faq', '/privacy', '/terms'].includes(currentPath)) return '/about';
    if (currentPath === '/contact') return '/contact';
    return '';
  })();

  const setActive = (destination) => {
    let changed = false;
    navLinks.forEach((link) => {
      const linkPath = normalPath(new URL(link.href, window.location.origin).pathname);
      const active = destination !== '' && linkPath === destination;
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

  setActive(activeDestination);

  const updateCompactState = () => {
    nav.classList.toggle('is-compact', window.scrollY > 42);
  };

  let scrollFrame = 0;
  const queueCompactUpdate = () => {
    if (scrollFrame) return;
    scrollFrame = window.requestAnimationFrame(() => {
      scrollFrame = 0;
      updateCompactState();
    });
  };

  updateCompactState();
  window.addEventListener('scroll', queueCompactUpdate, { passive: true });
  window.addEventListener('resize', queueCompactUpdate, { passive: true });

  if (menuButton instanceof HTMLButtonElement) {
    menuButton.addEventListener('click', () => {
      const open = nav.classList.toggle('menu-open');
      menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    nav.addEventListener('click', (event) => {
      if (!(event.target instanceof Element) || !event.target.closest('.public-site-links a')) return;
      nav.classList.remove('menu-open');
      menuButton.setAttribute('aria-expanded', 'false');
    });
  }
})();
