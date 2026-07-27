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

    const photoDialog = document.querySelector('[data-photo-dialog]');
    const photoImage = photoDialog?.querySelector('[data-photo-image]');
    let photoScale = 1;
    const applyPhotoScale = () => {
        if (photoImage) photoImage.style.transform = `scale(${photoScale})`;
    };
    const closePhoto = () => {
        if (!photoDialog) return;
        photoScale = 1;
        applyPhotoScale();
        photoDialog.close();
    };
    document.querySelectorAll('[data-photo-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!photoDialog || typeof photoDialog.showModal !== 'function') return;
            photoScale = 1;
            applyPhotoScale();
            photoDialog.showModal();
        });
    });
    photoDialog?.querySelectorAll('[data-photo-close]').forEach((button) => button.addEventListener('click', closePhoto));
    photoDialog?.querySelector('[data-photo-zoom-in]')?.addEventListener('click', () => {
        photoScale = Math.min(3, photoScale + 0.25);
        applyPhotoScale();
    });
    photoDialog?.querySelector('[data-photo-zoom-out]')?.addEventListener('click', () => {
        photoScale = Math.max(0.75, photoScale - 0.25);
        applyPhotoScale();
    });
    photoDialog?.querySelector('[data-photo-reset]')?.addEventListener('click', () => {
        photoScale = 1;
        applyPhotoScale();
    });
    photoDialog?.addEventListener('click', (event) => {
        if (event.target === photoDialog) closePhoto();
    });

    const removeDialog = document.querySelector('[data-photo-remove-dialog]');
    const removeCancel = document.querySelector('[data-photo-remove-cancel]');
    const removeConfirm = document.querySelector('[data-photo-remove-confirm]');
    let pendingRemoveForm = null;
    let removeApproved = false;

    document.querySelectorAll('[data-profile-photo-remove]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (removeApproved) return;
            if (!removeDialog || typeof removeDialog.showModal !== 'function') {
                if (!window.confirm('Remove this profile photo and return to the initials avatar?')) event.preventDefault();
                return;
            }
            event.preventDefault();
            pendingRemoveForm = form;
            removeDialog.showModal();
            removeCancel?.focus();
        });
    });
    removeCancel?.addEventListener('click', () => {
        pendingRemoveForm = null;
        removeDialog?.close();
    });
    removeConfirm?.addEventListener('click', () => {
        if (!pendingRemoveForm) return;
        removeApproved = true;
        removeDialog?.close();
        pendingRemoveForm.requestSubmit();
    });
    removeDialog?.addEventListener('click', (event) => {
        if (event.target === removeDialog) {
            pendingRemoveForm = null;
            removeDialog.close();
        }
    });

    document.querySelectorAll('input[type="number"]').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (['e', 'E', '+', '-'].includes(event.key)) event.preventDefault();
        });
    });

    const courseAuthoring = document.querySelector('[data-course-authoring]');
    if (courseAuthoring) {
        const titleInput = courseAuthoring.querySelector('[name="title"]');
        const descriptionInput = courseAuthoring.querySelector('[name="short_description"]');
        const categoryInput = courseAuthoring.querySelector('[name="category_id"]');
        const priceInput = courseAuthoring.querySelector('[name="price"]');
        const discountInput = courseAuthoring.querySelector('[name="discount_price"]');
        const thumbnailInput = courseAuthoring.querySelector('[name="thumbnail"]');
        const previewTitle = courseAuthoring.querySelector('[data-preview-title]');
        const previewDescription = courseAuthoring.querySelector('[data-preview-description]');
        const previewCategory = courseAuthoring.querySelector('[data-preview-category]');
        const previewPrice = courseAuthoring.querySelector('[data-preview-price]');
        const previewMedia = courseAuthoring.querySelector('[data-preview-media]');
        let thumbnailObjectUrl = '';

        const cleanPreviewText = (value, fallback) => {
            const text = String(value || '').replace(/\s+/g, ' ').trim();
            return text || fallback;
        };
        const updateCoursePreview = () => {
            if (previewTitle) previewTitle.textContent = cleanPreviewText(titleInput?.value, 'Your course title');
            if (previewDescription) previewDescription.textContent = cleanPreviewText(
                descriptionInput?.value,
                'Your short description will appear here and stay contained inside the course card.',
            );
            if (previewCategory && categoryInput instanceof HTMLSelectElement) {
                previewCategory.textContent = cleanPreviewText(categoryInput.selectedOptions[0]?.textContent, 'Choose a category');
            }
            const discount = Number.parseFloat(discountInput?.value || '');
            const standard = Number.parseFloat(priceInput?.value || '0');
            const amount = Number.isFinite(discount) ? discount : standard;
            if (previewPrice) {
                previewPrice.textContent = Number.isFinite(amount) && amount > 0
                    ? `NPR ${new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 }).format(amount)}`
                    : 'Free';
            }
        };

        [titleInput, descriptionInput, categoryInput, priceInput, discountInput].forEach((field) => {
            field?.addEventListener('input', updateCoursePreview);
            field?.addEventListener('change', updateCoursePreview);
        });
        thumbnailInput?.addEventListener('change', () => {
            const file = thumbnailInput.files?.[0];
            if (thumbnailObjectUrl) URL.revokeObjectURL(thumbnailObjectUrl);
            thumbnailObjectUrl = '';
            if (!previewMedia) return;
            previewMedia.replaceChildren();
            if (!file) {
                const placeholder = document.createElement('span');
                placeholder.textContent = 'CourseHub';
                previewMedia.appendChild(placeholder);
                return;
            }
            thumbnailObjectUrl = URL.createObjectURL(file);
            const image = document.createElement('img');
            image.src = thumbnailObjectUrl;
            image.alt = 'Selected course thumbnail preview';
            previewMedia.appendChild(image);
        });
        updateCoursePreview();
    }

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
