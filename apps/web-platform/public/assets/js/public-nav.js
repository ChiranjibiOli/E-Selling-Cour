(() => {
  'use strict';

  const nav = document.querySelector('[data-public-nav]');
  const button = document.querySelector('[data-public-menu]');
  const links = document.querySelector('[data-public-links]');

  if (!nav) return;

  let frame = 0;
  const update = () => {
    frame = 0;
    nav.classList.toggle('is-compact', window.scrollY > 52);
  };

  const queueUpdate = () => {
    if (frame) return;
    frame = window.requestAnimationFrame(update);
  };

  window.addEventListener('scroll', queueUpdate, { passive: true });
  window.addEventListener('resize', queueUpdate, { passive: true });
  update();

  button?.addEventListener('click', () => {
    const opening = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(opening));
    links?.classList.toggle('is-open', opening);
  });

  links?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      button?.setAttribute('aria-expanded', 'false');
      links.classList.remove('is-open');
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    button?.setAttribute('aria-expanded', 'false');
    links?.classList.remove('is-open');
  });
})();
