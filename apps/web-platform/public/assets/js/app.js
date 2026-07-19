'use strict';

document.documentElement.classList.add('js-ready');

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.portal-shell');
    const toggle = document.querySelector('[data-portal-toggle]');
    const overlay = document.querySelector('[data-portal-overlay]');
    const toast = document.querySelector('[data-portal-toast]');

    const closeNavigation = () => {
        if (!shell) return;
        shell.classList.remove('nav-open');
        toggle?.setAttribute('aria-expanded', 'false');
    };

    toggle?.addEventListener('click', () => {
        if (!shell) return;
        const open = shell.classList.toggle('nav-open');
        toggle.setAttribute('aria-expanded', String(open));
    });
    overlay?.addEventListener('click', closeNavigation);
    document.querySelectorAll('.portal-nav-link').forEach((link) => link.addEventListener('click', closeNavigation));

    let toastTimer = 0;
    const showToast = (message) => {
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.add('visible');
        toastTimer = window.setTimeout(() => toast.classList.remove('visible'), 3200);
    };

    document.querySelectorAll('[data-demo-action]').forEach((button) => {
        button.addEventListener('click', () => showToast(button.dataset.demoAction || 'This action is ready for service integration.'));
    });

    document.querySelectorAll('.payment-method').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.payment-method').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
        });
    });

    document.querySelectorAll('.filter-tabs').forEach((tabs) => {
        tabs.querySelectorAll('button').forEach((button) => {
            button.addEventListener('click', () => {
                tabs.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
            });
        });
    });
});
