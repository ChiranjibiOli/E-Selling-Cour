(() => {
  'use strict';

  const nav = document.querySelector('[data-public-site-nav], [data-landing-nav]');
  if (!(nav instanceof HTMLElement)) return;

  const menuButton = nav.querySelector('[data-public-site-menu], [data-landing-menu]');
  const navLinks = Array.from(nav.querySelectorAll('.coursehub-public-links a, .public-site-links a, .landing-links a'));
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const destinations = {
    Home: '/#top',
    Courses: '/courses',
    Categories: '/#categories',
    About: '/#promise',
    Contact: '/contact'
  };

  navLinks.forEach((link) => {
    const label = link.textContent?.trim() || '';
    if (destinations[label]) link.setAttribute('href', destinations[label]);
  });

  const normalPath = (path) => {
    const cleaned = path.replace(/\/+$/, '');
    return cleaned === '' ? '/' : cleaned;
  };

  const currentPath = normalPath(window.location.pathname);
  const trackedSections = Array.from(document.querySelectorAll('[data-public-section]'));

  const sectionForScroll = () => {
    if (currentPath !== '/' || trackedSections.length === 0) return '';
    const marker = window.scrollY + Math.min(window.innerHeight * 0.34, 310);
    let current = 'top';
    trackedSections.forEach((section) => {
      if (!(section instanceof HTMLElement)) return;
      if (section.offsetTop <= marker) current = section.getAttribute('data-public-section') || current;
    });
    return current;
  };

  const destinationForPage = () => {
    if (currentPath === '/') return sectionForScroll() || 'top';
    if (currentPath === '/courses' || currentPath === '/course' || currentPath === '/search') return 'courses';
    if (currentPath === '/contact') return 'contact';
    if (currentPath === '/learn/sign-in' || currentPath === '/forgot-password' || currentPath === '/reset-password' || currentPath === '/verify-otp') return 'login';
    if (currentPath === '/register/student') return 'register';
    return '';
  };

  const keyForLink = (link) => {
    const label = link.textContent?.trim() || '';
    if (label === 'Home') return 'top';
    if (label === 'Courses') return 'courses';
    if (label === 'Categories') return 'categories';
    if (label === 'About') return 'promise';
    if (label === 'Contact') return 'contact';
    if (label === 'Log in') return 'login';
    if (label === 'Create account') return 'register';
    return '';
  };

  const setActive = (destination) => {
    let changed = false;
    const allInteractive = Array.from(nav.querySelectorAll('a'));
    allInteractive.forEach((link) => {
      const active = keyForLink(link) === destination;
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

  const updateNavigation = () => {
    nav.classList.toggle('is-compact', window.scrollY > 42);
    setActive(destinationForPage());
  };

  let frame = 0;
  const queueUpdate = () => {
    if (frame) return;
    frame = window.requestAnimationFrame(() => {
      frame = 0;
      updateNavigation();
    });
  };

  updateNavigation();
  window.addEventListener('scroll', queueUpdate, { passive: true });
  window.addEventListener('resize', queueUpdate, { passive: true });
  window.addEventListener('hashchange', queueUpdate);

  if (menuButton instanceof HTMLButtonElement) {
    menuButton.addEventListener('click', () => {
      const open = nav.classList.toggle('menu-open');
      menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    nav.addEventListener('click', (event) => {
      if (!(event.target instanceof Element) || !event.target.closest('.coursehub-public-links a, .public-site-links a, .landing-links a')) return;
      nav.classList.remove('menu-open');
      menuButton.setAttribute('aria-expanded', 'false');
    });
  }
})();
