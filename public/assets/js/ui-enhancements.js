document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    document.querySelectorAll('.course-card, .dashboard-card, .stat-card, .metric-card, .summary-card, .profile-card, .settings-card, .table-card, .auth-card').forEach(function (element) {
        if (!element.hasAttribute('data-reveal')) {
            element.setAttribute('data-reveal', '');
        }
    });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

        document.querySelectorAll('[data-reveal]').forEach(function (element) {
            observer.observe(element);
        });
    } else {
        document.querySelectorAll('[data-reveal]').forEach(function (element) {
            element.classList.add('is-visible');
        });
    }

    document.querySelectorAll('table').forEach(function (table) {
        if (table.parentElement && !table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });

    document.querySelectorAll('button, .btn, a').forEach(function (element) {
        if (!element.getAttribute('aria-label') && !element.textContent.trim() && element.querySelector('svg, img, i')) {
            element.setAttribute('aria-label', element.getAttribute('title') || 'Action');
        }
    });

    document.querySelectorAll('img').forEach(function (image) {
        if (!image.hasAttribute('loading') && !image.closest('.hero, .landing-hero')) {
            image.loading = 'lazy';
        }
        image.addEventListener('error', function () {
            if (!image.dataset.fallbackApplied) {
                image.dataset.fallbackApplied = 'true';
                image.classList.add('image-load-failed');
                image.alt = image.alt || 'Course image unavailable';
            }
        });
    });

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const submit = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submit || submit.dataset.preventLoading === 'true') {
                return;
            }

            submit.classList.add('is-loading');
            submit.setAttribute('aria-busy', 'true');

            if (submit.tagName === 'BUTTON' && !submit.dataset.originalText) {
                submit.dataset.originalText = submit.textContent;
                submit.textContent = 'Please wait…';
            }

            window.setTimeout(function () {
                submit.classList.remove('is-loading');
                submit.removeAttribute('aria-busy');
                if (submit.dataset.originalText) {
                    submit.textContent = submit.dataset.originalText;
                    delete submit.dataset.originalText;
                }
            }, 8000);
        });
    });

    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
    document.querySelectorAll('.navbar a, .public-navbar a, .role-nav-menu a').forEach(function (link) {
        try {
            const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
            if (linkPath === currentPath) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'page');
            }
        } catch (error) {
            // Ignore malformed or non-http navigation links.
        }
    });

    let lastScroll = window.scrollY;
    const navigation = document.querySelector('.navbar, .public-navbar, .role-navbar');
    if (navigation) {
        window.addEventListener('scroll', function () {
            const currentScroll = window.scrollY;
            navigation.classList.toggle('is-scrolled', currentScroll > 12);
            navigation.classList.toggle('is-hidden', currentScroll > lastScroll && currentScroll > 180 && !navigation.querySelector('.is-open'));
            lastScroll = currentScroll;
        }, { passive: true });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Tab') {
            body.classList.add('keyboard-navigation');
        }
    });

    document.addEventListener('mousedown', function () {
        body.classList.remove('keyboard-navigation');
    });
});
