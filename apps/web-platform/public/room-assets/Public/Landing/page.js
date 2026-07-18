(() => {
  'use strict';

  const root = document.documentElement;
  const body = document.body;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));
  const lerp = (from, to, progress) => from + (to - from) * progress;

  const revealItems = document.querySelectorAll('.reveal');
  if (reducedMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  } else {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        entry.target.classList.toggle('is-visible', entry.isIntersecting);
      });
    }, { threshold: 0.08, rootMargin: '-4% 0px -8%' });
    revealItems.forEach((item) => revealObserver.observe(item));
  }

  const header = document.querySelector('.public-header');
  const hero = document.querySelector('[data-hero-scrub]');
  const heroStage = document.querySelector('[data-hero-stage]');
  const heroImage = heroStage?.querySelector('.hero-media img');
  const finder = hero?.querySelector('.course-finder');

  const chapter = document.querySelector('[data-chapter-scroll]');
  const chapterSticky = chapter?.querySelector('.chapter-sticky');

  const story = document.querySelector('[data-story-scroll]');
  const storySticky = story?.querySelector('.story-sticky');
  const storyCards = Array.from(document.querySelectorAll('[data-story-card]'));
  const storyIndex = document.querySelector('[data-story-index]');

  const scrubSections = Array.from(document.querySelectorAll('[data-scrub-section]'));
  let scrollQueued = false;

  const sectionProgress = (section) => {
    if (!section) return 0;
    const rect = section.getBoundingClientRect();
    const distance = Math.max(1, rect.height - window.innerHeight);
    return clamp(-rect.top / distance);
  };

  const viewportProgress = (section) => {
    if (!section) return 0;
    const rect = section.getBoundingClientRect();
    return clamp((window.innerHeight - rect.top) / (window.innerHeight + rect.height));
  };

  const updateHero = () => {
    if (!hero || reducedMotion) return;
    const progress = viewportProgress(hero);
    const stageProgress = clamp(progress * 1.35);
    hero.style.setProperty('--hero-copy-y', `${lerp(0, -92, progress).toFixed(2)}px`);
    hero.style.setProperty('--hero-copy-opacity', String(lerp(1, .34, progress)));
    heroStage?.style.setProperty('--hero-stage-y', `${lerp(28, -44, stageProgress).toFixed(2)}px`);
    heroStage?.style.setProperty('--hero-stage-scale', String(lerp(.94, 1, stageProgress)));
    heroStage?.style.setProperty('--hero-radius', `${lerp(44, 18, stageProgress).toFixed(2)}px`);
    heroStage?.style.setProperty('--hero-image-scale', String(lerp(1.12, 1.02, stageProgress)));
    heroStage?.style.setProperty('--hero-image-y', `${lerp(-18, 34, progress).toFixed(2)}px`);
    finder?.style.setProperty('--finder-y', `${lerp(24, -10, stageProgress).toFixed(2)}px`);
  };

  const updateChapter = () => {
    if (!chapter || !chapterSticky || reducedMotion) return;
    const progress = sectionProgress(chapter);
    const enter = clamp(progress / .45);
    const leave = clamp((progress - .58) / .42);
    const opacity = Math.min(enter, 1 - leave * .82);
    chapterSticky.style.setProperty('--chapter-progress', `${(progress * 100).toFixed(2)}%`);
    chapterSticky.style.setProperty('--chapter-y', `${lerp(70, -36, progress).toFixed(2)}px`);
    chapterSticky.style.setProperty('--chapter-scale', String(lerp(.78, 1.08, enter) - leave * .08));
    chapterSticky.style.setProperty('--chapter-opacity', String(clamp(opacity, .12, 1)));
    chapterSticky.style.setProperty('--chapter-ring-rotate', `${lerp(-18, 42, progress).toFixed(2)}deg`);
    chapterSticky.style.setProperty('--chapter-ring-scale', String(lerp(.82, 1.18, progress)));
    chapterSticky.style.setProperty('--chapter-light-x', `${lerp(24, 78, progress).toFixed(1)}%`);
    chapterSticky.style.setProperty('--chapter-light-y', `${lerp(68, 26, progress).toFixed(1)}%`);
  };

  const updateStory = () => {
    if (!story || !storySticky || storyCards.length === 0) return;

    if (reducedMotion || window.innerWidth <= 820) {
      storyCards.forEach((card) => {
        card.style.transform = '';
        card.style.opacity = '';
        card.style.filter = '';
        card.removeAttribute('aria-hidden');
      });
      return;
    }

    const progress = sectionProgress(story);
    const journey = progress * (storyCards.length - 1);
    const activeIndex = Math.round(journey);

    storySticky.style.setProperty('--story-progress-width', `${(progress * 100).toFixed(2)}%`);
    storySticky.style.setProperty('--story-light-x', `${lerp(22, 82, progress).toFixed(1)}%`);
    storySticky.style.setProperty('--story-heading-y', `${lerp(0, -72, progress).toFixed(2)}px`);
    storySticky.style.setProperty('--story-heading-opacity', String(lerp(1, .38, progress)));
    if (storyIndex) storyIndex.textContent = String(activeIndex + 1).padStart(2, '0');

    storyCards.forEach((card, index) => {
      const offset = index - journey;
      const distance = Math.abs(offset);
      const x = offset * 67;
      const y = Math.min(distance * 5.5, 12);
      const rotation = offset * 4.5;
      const scale = 1 - Math.min(distance * .11, .2);
      const opacity = clamp(1 - distance * .62, .12, 1);
      const blur = Math.min(distance * 2.2, 4.5);
      card.style.transform = `translate3d(${x}vw, ${y}vh, 0) rotate(${rotation}deg) scale(${scale})`;
      card.style.opacity = String(opacity);
      card.style.filter = `blur(${blur}px)`;
      card.style.zIndex = String(20 - Math.round(distance * 5));
      card.setAttribute('aria-hidden', distance > .62 ? 'true' : 'false');
    });
  };

  const updateScrubSections = () => {
    if (reducedMotion) return;
    scrubSections.forEach((section) => {
      const progress = viewportProgress(section);
      const local = clamp((progress - .18) / .62);
      section.style.setProperty('--section-x', `${lerp(42, -14, local).toFixed(2)}px`);
      section.style.setProperty('--scrub-card-y', `${lerp(38, -12, local).toFixed(2)}px`);
      section.style.setProperty('--scrub-card-rotate', `${lerp(4, -1, local).toFixed(2)}deg`);

      const device = section.querySelector('.closing-device');
      if (device) {
        device.style.setProperty('--device-y', `${lerp(86, -8, local).toFixed(2)}px`);
        device.style.setProperty('--device-rotate', `${lerp(-8, 2, local).toFixed(2)}deg`);
      }
    });
  };

  const updateScrollState = () => {
    const y = window.scrollY;
    header?.classList.toggle('is-scrolled', y > 24);
    root.style.setProperty('--word-one-x', `${(-y * .04).toFixed(2)}px`);
    root.style.setProperty('--word-two-x', `${(y * .05).toFixed(2)}px`);
    updateHero();
    updateChapter();
    updateStory();
    updateScrubSections();
    scrollQueued = false;
  };

  const queueScrollUpdate = () => {
    if (scrollQueued) return;
    scrollQueued = true;
    window.requestAnimationFrame(updateScrollState);
  };

  window.addEventListener('scroll', queueScrollUpdate, { passive: true });
  window.addEventListener('resize', queueScrollUpdate, { passive: true });
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
  const moveRail = (rail, direction, factor = .78) => {
    if (!rail) return;
    rail.scrollBy({ left: direction * Math.max(260, rail.clientWidth * factor), behavior: reducedMotion ? 'auto' : 'smooth' });
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
    const ratio = clamp(courseRail.scrollLeft / maxScroll);
    const width = 25 + ratio * 75;
    courseProgress.style.setProperty('--course-progress', `${width}%`);
  };

  coursePrev?.addEventListener('click', () => moveRail(courseRail, -1, .72));
  courseNext?.addEventListener('click', () => moveRail(courseRail, 1, .72));
  courseRail?.addEventListener('scroll', updateCourseProgress, { passive: true });
  enableDragScroll(courseRail);
  updateCourseProgress();
})();
