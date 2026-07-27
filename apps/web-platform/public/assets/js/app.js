'use strict';

document.documentElement.classList.add('js-ready');

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.portal-shell');
    const toggle = document.querySelector('[data-portal-toggle]');
    const overlay = document.querySelector('[data-portal-overlay]');
    const toast = document.querySelector('[data-portal-toast]');
    const sidebarNav = document.querySelector('[data-portal-nav]');
    const activeNavigation = sidebarNav?.querySelector('.portal-nav-link.active');
    const role = shell?.dataset.portalRole || 'portal';
    const navigationScrollKey = `coursehub:${role}:navigation-scroll`;

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

    if (sidebarNav) {
        const savedPosition = Number.parseInt(sessionStorage.getItem(navigationScrollKey) || '', 10);
        window.requestAnimationFrame(() => {
            if (Number.isFinite(savedPosition)) {
                sidebarNav.scrollTop = savedPosition;
            } else {
                activeNavigation?.scrollIntoView({ block: 'nearest' });
            }
        });
        sidebarNav.addEventListener('scroll', () => {
            sessionStorage.setItem(navigationScrollKey, String(Math.round(sidebarNav.scrollTop)));
        }, { passive: true });
        sidebarNav.querySelectorAll('.portal-nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                sessionStorage.setItem(navigationScrollKey, String(Math.round(sidebarNav.scrollTop)));
                closeNavigation();
            });
        });
    }

    const logoutForm = document.querySelector('[data-logout-form]');
    const logoutDialog = document.querySelector('[data-logout-dialog]');
    const logoutCancel = document.querySelector('[data-logout-cancel]');
    const logoutConfirm = document.querySelector('[data-logout-confirm]');
    let logoutApproved = false;

    logoutForm?.addEventListener('submit', (event) => {
        if (logoutApproved || !logoutDialog || typeof logoutDialog.showModal !== 'function') return;
        event.preventDefault();
        logoutDialog.showModal();
        logoutCancel?.focus();
    });
    logoutCancel?.addEventListener('click', () => logoutDialog?.close());
    logoutConfirm?.addEventListener('click', () => {
        logoutApproved = true;
        logoutDialog?.close();
        logoutForm?.requestSubmit();
    });
    logoutDialog?.addEventListener('click', (event) => {
        if (event.target === logoutDialog) logoutDialog.close();
    });

    let toastTimer = 0;
    const showToast = (message) => {
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.add('visible');
        toastTimer = window.setTimeout(() => toast.classList.remove('visible'), 3200);
    };

    if (shell) {
        let checkingSession = false;
        let sessionEnded = false;
        const checkSession = async () => {
            if (checkingSession || sessionEnded || document.visibilityState === 'hidden') return;
            checkingSession = true;
            try {
                const response = await fetch('/session-status', {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                if (response.status !== 401) return;

                const payload = await response.json().catch(() => ({}));
                const loginUrl = typeof payload.login_url === 'string' && payload.login_url.startsWith('/')
                    ? payload.login_url
                    : '/login';
                sessionEnded = true;
                showToast('This session was revoked or expired. Redirecting to sign in.');
                window.setTimeout(() => {
                    const separator = loginUrl.includes('?') ? '&' : '?';
                    window.location.replace(`${loginUrl}${separator}session=ended`);
                }, 500);
            } catch (error) {
                // A temporary network or service outage must not destroy a valid local browser session.
            } finally {
                checkingSession = false;
            }
        };

        window.setTimeout(checkSession, 1500);
        window.setInterval(checkSession, 12000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') checkSession();
        });
    }

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

    const pageSearch = document.querySelector('.portal-search input');
    pageSearch?.addEventListener('input', () => {
        const term = pageSearch.value.trim().toLowerCase();
        document.querySelectorAll('.portal-main tbody tr:not(.empty-row), .portal-main details.portal-card').forEach((item) => {
            const matches = term === '' || (item.textContent || '').toLowerCase().includes(term);
            item.hidden = !matches;
        });
    });
});
