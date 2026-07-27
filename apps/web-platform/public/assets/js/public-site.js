(() => {
  const nav = document.querySelector('[data-public-site-nav]');
  if (!nav) return;

  const menuButton = nav.querySelector('[data-public-site-menu]');
  const updateCompactState = () => {
    nav.classList.toggle('is-compact', window.scrollY > 42);
  };

  updateCompactState();
  window.addEventListener('scroll', updateCompactState, { passive: true });

  if (menuButton) {
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
