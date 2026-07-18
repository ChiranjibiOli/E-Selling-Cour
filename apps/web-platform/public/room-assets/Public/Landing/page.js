(() => {
  'use strict';

  const body = document.body;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealItems = document.querySelectorAll('.reveal');

  if (reducedMotion) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  } else {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.13, rootMargin: '0px 0px -45px' });

    revealItems.forEach((item) => revealObserver.observe(item));
  }

  const header = document.querySelector('.public-header');
  const sky = document.querySelector('.hero-sky');
  const backMountain = document.querySelector('.mountain-back');
  const frontMountain = document.querySelector('.mountain-front');
  const orbit = document.querySelector('.hero-orbit');

  const updateScrollEffects = () => {
    const scrollY = window.scrollY;
    if (header) {
      header.classList.toggle('is-scrolled', scrollY > 24);
    }

    if (!reducedMotion) {
      if (sky) sky.style.transform = `translate3d(0, ${scrollY * 0.08}px, 0)`;
      if (backMountain) backMountain.style.transform = `translate3d(0, ${scrollY * 0.04}px, 0)`;
      if (frontMountain) frontMountain.style.transform = `translate3d(0, ${scrollY * 0.075}px, 0)`;
      if (orbit) orbit.style.translate = `0 ${scrollY * 0.035}px`;
    }
  };

  let scrollTicking = false;
  window.addEventListener('scroll', () => {
    if (!scrollTicking) {
      window.requestAnimationFrame(() => {
        updateScrollEffects();
        scrollTicking = false;
      });
      scrollTicking = true;
    }
  }, { passive: true });
  updateScrollEffects();

  const finder = document.querySelector('.course-finder');
  const finderGlow = document.querySelector('.finder-glow');

  if (finder && finderGlow && !reducedMotion) {
    finder.addEventListener('pointermove', (event) => {
      const bounds = finder.getBoundingClientRect();
      const x = event.clientX - bounds.left - 110;
      const y = event.clientY - bounds.top - 110;
      finderGlow.style.transform = `translate3d(${x}px, ${y}px, 0)`;
    });

    finder.addEventListener('pointerleave', () => {
      finderGlow.style.transform = 'translate3d(0, 0, 0)';
    });
  }

  const searchInput = document.querySelector('#course-query');
  const suggestionLinks = document.querySelectorAll('.intent-options a');

  suggestionLinks.forEach((link) => {
    link.addEventListener('mouseenter', () => {
      if (!searchInput || reducedMotion) return;
      searchInput.closest('.finder-field')?.classList.add('is-suggested');
    });
    link.addEventListener('mouseleave', () => {
      searchInput?.closest('.finder-field')?.classList.remove('is-suggested');
    });
  });

  const rail = document.querySelector('[data-drag-scroll]');
  const previousButton = document.querySelector('.rail-prev');
  const nextButton = document.querySelector('.rail-next');

  const scrollRail = (direction) => {
    if (!rail) return;
    rail.scrollBy({ left: direction * Math.min(620, rail.clientWidth * 0.76), behavior: 'smooth' });
  };

  previousButton?.addEventListener('click', () => scrollRail(-1));
  nextButton?.addEventListener('click', () => scrollRail(1));

  if (rail) {
    let dragging = false;
    let startX = 0;
    let startScroll = 0;

    rail.addEventListener('pointerdown', (event) => {
      dragging = true;
      startX = event.clientX;
      startScroll = rail.scrollLeft;
      rail.setPointerCapture(event.pointerId);
    });

    rail.addEventListener('pointermove', (event) => {
      if (!dragging) return;
      rail.scrollLeft = startScroll - (event.clientX - startX);
    });

    const stopDragging = (event) => {
      if (!dragging) return;
      dragging = false;
      if (rail.hasPointerCapture(event.pointerId)) {
        rail.releasePointerCapture(event.pointerId);
      }
    };

    rail.addEventListener('pointerup', stopDragging);
    rail.addEventListener('pointercancel', stopDragging);
  }

  const menuButton = document.querySelector('.menu-button');
  const glassNav = document.querySelector('.glass-nav');

  menuButton?.addEventListener('click', () => {
    const open = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!open));
    glassNav?.classList.toggle('is-open', !open);
    body.classList.toggle('nav-open', !open);
  });

  glassNav?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      menuButton?.setAttribute('aria-expanded', 'false');
      glassNav.classList.remove('is-open');
      body.classList.remove('nav-open');
    });
  });
})();
