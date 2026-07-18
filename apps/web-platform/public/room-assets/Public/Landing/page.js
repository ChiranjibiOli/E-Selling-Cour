(() => {
  'use strict';

  const root = document.documentElement;
  const body = document.body;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const revealItems = document.querySelectorAll('.reveal');
  if (reducedMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  } else {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -45px' });
    revealItems.forEach((item) => revealObserver.observe(item));
  }

  const header = document.querySelector('.public-header');
  const hero = document.querySelector('.hero');
  const heroMedia = document.querySelector('.hero-media img');
  let scrollQueued = false;

  const updateScrollState = () => {
    const y = window.scrollY;
    root.style.setProperty('--scroll-y', String(y));
    header?.classList.toggle('is-scrolled', y > 24);

    if (!reducedMotion && hero && heroMedia) {
      const bounds = hero.getBoundingClientRect();
      const progress = Math.max(-1, Math.min(1, -bounds.top / Math.max(bounds.height, 1)));
      root.style.setProperty('--hero-shift', `${progress * 34}px`);
    }
    scrollQueued = false;
  };

  window.addEventListener('scroll', () => {
    if (scrollQueued) return;
    scrollQueued = true;
    window.requestAnimationFrame(updateScrollState);
  }, { passive: true });
  updateScrollState();

  const menuButton = document.querySelector('.menu-button');
  const nav = document.querySelector('.primary-nav');
  menuButton?.addEventListener('click', () => {
    const open = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!open));
    nav?.classList.toggle('is-open', !open);
    body.classList.toggle('nav-open', !open);
  });
  nav?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      menuButton?.setAttribute('aria-expanded', 'false');
      nav.classList.remove('is-open');
      body.classList.remove('nav-open');
    });
  });

  const finder = document.querySelector('.course-finder');
  const finderFields = finder?.querySelectorAll('input, select') ?? [];
  const updateFinderProgress = () => {
    if (!finder || finderFields.length === 0) return;
    const completed = Array.from(finderFields).filter((field) => String(field.value).trim() !== '').length;
    const percentage = Math.max(8, Math.round((completed / finderFields.length) * 100));
    finder.style.setProperty('--finder-progress', `${percentage}%`);
  };

  finderFields.forEach((field) => {
    field.addEventListener('focus', () => finder?.classList.add('is-focused'));
    field.addEventListener('blur', () => finder?.classList.remove('is-focused'));
    field.addEventListener('input', updateFinderProgress);
    field.addEventListener('change', updateFinderProgress);
  });
  updateFinderProgress();

  const enableDragScroll = (rail) => {
    if (!rail) return;
    let dragging = false;
    let startX = 0;
    let startScroll = 0;
    let moved = false;

    rail.addEventListener('pointerdown', (event) => {
      dragging = true;
      moved = false;
      startX = event.clientX;
      startScroll = rail.scrollLeft;
      rail.setPointerCapture(event.pointerId);
    });

    rail.addEventListener('pointermove', (event) => {
      if (!dragging) return;
      const delta = event.clientX - startX;
      if (Math.abs(delta) > 5) moved = true;
      rail.scrollLeft = startScroll - delta;
    });

    const stop = (event) => {
      if (!dragging) return;
      dragging = false;
      if (rail.hasPointerCapture(event.pointerId)) rail.releasePointerCapture(event.pointerId);
    };

    rail.addEventListener('pointerup', stop);
    rail.addEventListener('pointercancel', stop);
    rail.addEventListener('click', (event) => {
      if (moved) event.preventDefault();
    }, true);
  };

  const categoryRail = document.querySelector('[data-drag-scroll]');
  const categoryPrev = document.querySelector('.rail-prev');
  const categoryNext = document.querySelector('.rail-next');
  const moveRail = (rail, direction, factor = 0.78) => {
    if (!rail) return;
    rail.scrollBy({ left: direction * Math.max(260, rail.clientWidth * factor), behavior: 'smooth' });
  };
  categoryPrev?.addEventListener('click', () => moveRail(categoryRail, -1));
  categoryNext?.addEventListener('click', () => moveRail(categoryRail, 1));
  enableDragScroll(categoryRail);

  const courseRail = document.querySelector('[data-course-rail]');
  const coursePrev = document.querySelector('[data-course-prev]');
  const courseNext = document.querySelector('[data-course-next]');
  const courseProgress = document.querySelector('.course-rail-controls > span');

  const updateCourseProgress = () => {
    if (!courseRail || !courseProgress) return;
    const maxScroll = Math.max(1, courseRail.scrollWidth - courseRail.clientWidth);
    const ratio = Math.max(0, Math.min(1, courseRail.scrollLeft / maxScroll));
    const width = 25 + ratio * 75;
    courseProgress.style.setProperty('--course-progress', `${width}%`);
  };

  coursePrev?.addEventListener('click', () => moveRail(courseRail, -1, 0.72));
  courseNext?.addEventListener('click', () => moveRail(courseRail, 1, 0.72));
  courseRail?.addEventListener('scroll', updateCourseProgress, { passive: true });
  enableDragScroll(courseRail);
  updateCourseProgress();

  window.addEventListener('resize', updateCourseProgress, { passive: true });
})();
