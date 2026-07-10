document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.role-navbar').forEach((navbar) => {
        const toggle = navbar.querySelector('.nav-toggle');
        const menu = navbar.querySelector('.role-nav-menu');

        if (!toggle || !menu) return;

        toggle.addEventListener('click', () => {
            const open = navbar.classList.toggle('menu-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        menu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                navbar.classList.remove('menu-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    });

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.dataset.confirm || 'Continue with this action?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });
});
