(() => {
  'use strict';

  const root = document.documentElement;
  const body = document.body;
  const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));
  const lerp = (from, to, progress) => from + (to - from) * progress;
  const motionMode = new URLSearchParams(window.location.search).get('motion');
  const reducedMotion = motionMode === 'reduce';

  if (!document.querySelector('link[data-course-card-theme]')) {
    const themeStyles = document.createElement('link');
    themeStyles.rel = 'stylesheet';
    themeStyles.href = '/assets/css/course-card-theme.css';
    themeStyles.dataset.courseCardTheme = '1';
    document.head.appendChild(themeStyles);
  }
  if (!document.querySelector('script[data-course-card-theme]')) {
    const themeScript = document.createElement('script');
    themeScript.src = '/assets/js/course-card-theme.js';
    themeScript.defer = true;
    themeScript.dataset.courseCardTheme = '1';
    document.head.appendChild(themeScript);
  }

  body.classList.add('motion-v2');

  const revealItems = document.querySelectorAll('.reveal');
  if (reducedMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  } else {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => entry.target.classList.toggle('is-visible', entry.isIntersecting));
    }, { threshold: 0.08, rootMargin: '-4% 0px -8%' });
    revealItems.forEach((item) => revealObserver.observe(item));
  }

  const header = document.querySelector('.public-header');
  const hero = document.querySelector('[data-hero-scrub]');
  const heroStage = document.querySelector('[data-hero-stage]');
  const finder = hero?.querySelector('.course-finder');
  const chapter = document.querySelector('[data-chapter-scroll]');
  const chapterSticky = chapter?.querySelector('.chapter-sticky');
  const story = document.querySelector('[data-story-scroll]');
  const storySticky = story?.querySelector('.story-sticky');
  const storyCards = Array.from(document.querySelectorAll('[data-story-card]'));
  const storyIndex = document.querySelector('[data-story-index]');
  const scrubSections = Array.from(document.querySelectorAll('[data-scrub-section]'));

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

  const target = { hero: 0, chapter: 0, story: 0, scrollY: window.scrollY };
  const current = { hero: 0, chapter: 0, story: 0, scrollY: window.scrollY };
  let animationFrame = 0;

  const updateTargets = () => {
    target.scrollY = window.scrollY;
    target.hero = viewportProgress(hero);
    target.chapter = sectionProgress(chapter);
    target.story = sectionProgress(story);
    header?.classList.toggle('is-scrolled', window.scrollY > 24);
    requestMotionFrame();
  };

  const renderHero = (progress) => {
    if (!hero || reducedMotion) return;
    const stageProgress = clamp(progress * 1.35);
    hero.style.setProperty('--hero-copy-y', `${lerp(0, -110, progress).toFixed(2)}px`);
    hero.style.setProperty('--hero-copy-opacity', String(lerp(1, .28, progress)));
    heroStage?.style.setProperty('--hero-stage-y', `${lerp(46, -54, stageProgress).toFixed(2)}px`);
    heroStage?.style.setProperty('--hero-stage-scale', String(lerp(.90, 1, stageProgress)));
    heroStage?.style.setProperty('--hero-radius', `${lerp(52, 14, stageProgress).toFixed(2)}px`);
    heroStage?.style.setProperty('--hero-image-scale', String(lerp(1.16, 1.01, stageProgress)));
    heroStage?.style.setProperty('--hero-image-y', `${lerp(-28, 48, progress).toFixed(2)}px`);
    finder?.style.setProperty('--finder-y', `${lerp(34, -16, stageProgress).toFixed(2)}px`);
  };

  const renderChapter = (progress) => {
    if (!chapterSticky || reducedMotion) return;
    const enter = clamp(progress / .38);
    const leave = clamp((progress - .64) / .36);
    const opacity = clamp(Math.min(enter, 1 - leave * .88), .08, 1);
    chapterSticky.style.setProperty('--chapter-progress', `${(progress * 100).toFixed(2)}%`);
    chapterSticky.style.setProperty('--chapter-y', `${lerp(120, -74, progress).toFixed(2)}px`);
    chapterSticky.style.setProperty('--chapter-scale', String(lerp(.62, 1.18, enter) - leave * .12));
    chapterSticky.style.setProperty('--chapter-opacity', String(opacity));
    chapterSticky.style.setProperty('--chapter-ring-rotate', `${lerp(-42, 76, progress).toFixed(2)}deg`);
    chapterSticky.style.setProperty('--chapter-ring-scale', String(lerp(.68, 1.34, progress)));
    chapterSticky.style.setProperty('--chapter-light-x', `${lerp(18, 84, progress).toFixed(1)}%`);
    chapterSticky.style.setProperty('--chapter-light-y', `${lerp(78, 18, progress).toFixed(1)}%`);
  };

  const renderStory = (progress) => {
    if (!storySticky || storyCards.length === 0) return;

    if (reducedMotion || window.innerWidth <= 820) {
      storyCards.forEach((card, index) => {
        card.style.transform = '';
        card.style.opacity = index === 0 ? '1' : '';
        card.style.filter = '';
        card.removeAttribute('aria-hidden');
      });
      return;
    }

    const eased = progress * progress * (3 - 2 * progress);
    const journey = eased * (storyCards.length - 1);
    const activeIndex = Math.max(0, Math.min(storyCards.length - 1, Math.round(journey)));

    storySticky.style.setProperty('--story-progress-width', `${(progress * 100).toFixed(2)}%`);
    storySticky.style.setProperty('--story-light-x', `${lerp(14, 88, progress).toFixed(1)}%`);
    storySticky.style.setProperty('--story-heading-y', `${lerp(24, -110, progress).toFixed(2)}px`);
    storySticky.style.setProperty('--story-heading-opacity', String(lerp(1, .22, progress)));
    if (storyIndex) storyIndex.textContent = String(activeIndex + 1).padStart(2, '0');

    storyCards.forEach((card, index) => {
      const offset = index - journey;
      const distance = Math.abs(offset);
      const x = offset * 82;
      const y = Math.min(distance * 7, 14);
      const rotation = offset * 7;
      const scale = 1 - Math.min(distance * .13, .24);
      const opacity = distance > 1.35 ? 0 : clamp(1 - distance * .72, .06, 1);
      const blur = Math.min(distance * 2.8, 6);

      card.style.transform = `translate3d(calc(-50% + ${x}vw), ${y}vh, 0) rotate(${rotation}deg) scale(${scale})`;
      card.style.opacity = String(opacity);
      card.style.filter = `blur(${blur}px)`;
      card.style.zIndex = String(40 - Math.round(distance * 10));
      card.setAttribute('aria-hidden', distance > .72 ? 'true' : 'false');
    });
  };

  const renderScrubSections = () => {
    if (reducedMotion) return;
    scrubSections.forEach((section) => {
      const progress = viewportProgress(section);
      const local = clamp((progress - .16) / .68);
      section.style.setProperty('--section-x', `${lerp(70, -18, local).toFixed(2)}px`);
      section.style.setProperty('--scrub-card-y', `${lerp(72, -18, local).toFixed(2)}px`);
      section.style.setProperty('--scrub-card-rotate', `${lerp(7, -1.5, local).toFixed(2)}deg`);

      const device = section.querySelector('.closing-device');
      if (device) {
        device.style.setProperty('--device-y', `${lerp(120, -16, local).toFixed(2)}px`);
        device.style.setProperty('--device-rotate', `${lerp(-12, 3, local).toFixed(2)}deg`);
      }
    });
  };

  const renderMotionFrame = () => {
    animationFrame = 0;
    const smoothing = .105;
    current.hero = lerp(current.hero, target.hero, smoothing);
    current.chapter = lerp(current.chapter, target.chapter, smoothing);
    current.story = lerp(current.story, target.story, smoothing);
    current.scrollY = lerp(current.scrollY, target.scrollY, smoothing);

    root.style.setProperty('--word-one-x', `${(-current.scrollY * .055).toFixed(2)}px`);
    root.style.setProperty('--word-two-x', `${(current.scrollY * .065).toFixed(2)}px`);
    renderHero(current.hero);
    renderChapter(current.chapter);
    renderStory(current.story);
    renderScrubSections();

    const unsettled = Math.abs(current.hero - target.hero) > .0005
      || Math.abs(current.chapter - target.chapter) > .0005
      || Math.abs(current.story - target.story) > .0005
      || Math.abs(current.scrollY - target.scrollY) > .3;

    if (unsettled) animationFrame = window.requestAnimationFrame(renderMotionFrame);
  };

  function requestMotionFrame() {
    if (!animationFrame) animationFrame = window.requestAnimationFrame(renderMotionFrame);
  }

  window.addEventListener('scroll', updateTargets, { passive: true });
  window.addEventListener('resize', updateTargets, { passive: true });
  updateTargets();

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
    finder.style.setProperty('--finder-progress', `${Math.max(8, Math.round((completed / finderFields.length) * 100))}%`);
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

  const moveRail = (rail, direction, factor = .78) => {
    if (!rail) return;
    rail.scrollBy({ left: direction * Math.max(260, rail.clientWidth * factor), behavior: reducedMotion ? 'auto' : 'smooth' });
  };

  const categoryRail = document.querySelector('[data-drag-scroll]');
  document.querySelector('.rail-prev')?.addEventListener('click', () => moveRail(categoryRail, -1));
  document.querySelector('.rail-next')?.addEventListener('click', () => moveRail(categoryRail, 1));
  enableDragScroll(categoryRail);

  const courseRail = document.querySelector('[data-course-rail]');
  const courseProgress = document.querySelector('.course-rail-controls > span');
  const updateCourseProgress = () => {
    if (!courseRail || !courseProgress) return;
    const maxScroll = Math.max(1, courseRail.scrollWidth - courseRail.clientWidth);
    courseProgress.style.setProperty('--course-progress', `${25 + clamp(courseRail.scrollLeft / maxScroll) * 75}%`);
  };
  document.querySelector('[data-course-prev]')?.addEventListener('click', () => moveRail(courseRail, -1, .72));
  document.querySelector('[data-course-next]')?.addEventListener('click', () => moveRail(courseRail, 1, .72));
  courseRail?.addEventListener('scroll', updateCourseProgress, { passive: true });
  enableDragScroll(courseRail);
  updateCourseProgress();

  console.info('CourseHub motion controller v2 active');
})();
