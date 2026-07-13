(() => {
    'use strict';

    const studentNavbar = document.querySelector('.student-navbar');
    if (!studentNavbar) {
        return;
    }

    const page = window.location.pathname.split('/').pop() || '';
    const pageStep = {
        'student-browse-courses.php': 1,
        'course-details.php': 1,
        'cart.php': 2,
        'checkout.php': 3,
        'checkout-success.php': 4,
        'student-my-courses.php': 5,
    };

    const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';

    function createHidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        return input;
    }

    function createPostForm({ action, label, courseId = '', slug = '', buttonClass = '' }) {
        if (!csrfToken || (!courseId && !slug)) {
            return null;
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = action;
        form.className = action === 'enroll-free-course.php'
            ? 'purchase-free-enroll-form'
            : 'purchase-buy-now-form';
        form.append(createHidden('_csrf_token', csrfToken));

        if (courseId) {
            form.append(createHidden('course_id', courseId));
        } else {
            form.append(createHidden('slug', slug));
        }

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = buttonClass;
        button.textContent = label;
        form.append(button);
        return form;
    }

    function courseSlugFromCard(card) {
        const link = card.querySelector('a[href*="course-details.php?slug="]');
        if (!link) {
            return '';
        }

        try {
            return new URL(link.href, window.location.href).searchParams.get('slug') || '';
        } catch (error) {
            return '';
        }
    }

    function isFreePriceText(value) {
        const text = String(value || '').trim().toLowerCase();
        if (text === 'free') {
            return true;
        }

        const numeric = Number(text.replace(/[^0-9.-]/g, ''));
        return Number.isFinite(numeric) && numeric === 0;
    }

    function cardIsFree(card) {
        return isFreePriceText(card.querySelector('.course-unit-price')?.textContent || '');
    }

    function addCardBuyButtons() {
        document.querySelectorAll('.course-unit-card').forEach((card) => {
            const actions = card.querySelector('.course-unit-actions');
            if (!actions || actions.querySelector('.purchase-buy-now-form')) {
                return;
            }

            const labels = Array.from(actions.querySelectorAll('a, button, span'))
                .map((control) => (control.textContent || '').trim().toLowerCase());

            if (cardIsFree(card) || labels.includes('enroll free')) {
                return;
            }

            if (labels.some((label) => [
                'continue learning',
                'go to course',
                'payment pending',
                'pending verification',
            ].includes(label))) {
                return;
            }

            const slug = courseSlugFromCard(card);
            if (!slug) {
                return;
            }

            actions.querySelectorAll('.course-unit-action--primary').forEach((control) => {
                control.classList.remove('course-unit-action--primary');
                control.classList.add('course-unit-action--secondary');
            });

            const form = createPostForm({
                action: 'buy-now.php',
                label: 'Buy Now',
                slug,
                buttonClass: 'course-unit-action course-unit-action--primary',
            });

            if (form) {
                actions.append(form);
            }
        });
    }

    function addDetailsPurchaseButton() {
        if (page !== 'course-details.php') {
            return;
        }

        const body = document.querySelector('.course-buy-card .buy-body');
        if (!body) {
            return;
        }

        const labels = Array.from(body.querySelectorAll('a, button'))
            .map((control) => (control.textContent || '').trim().toLowerCase());

        if (labels.some((label) => [
            'go to course',
            'manage lessons',
            'back to course review',
            'payment pending',
        ].includes(label))) {
            return;
        }

        const slug = new URLSearchParams(window.location.search).get('slug') || '';
        if (!slug) {
            return;
        }

        const freeCourse = isFreePriceText(body.querySelector('.buy-price')?.textContent || '');
        const existingControl = Array.from(body.querySelectorAll(':scope > a.buy-btn, :scope > form'))
            .find((control) => {
                const label = (control.textContent || '').trim().toLowerCase();
                return label === 'add to cart' || label === 'go to cart';
            });

        if (!existingControl) {
            return;
        }

        if (freeCourse) {
            const freeForm = createPostForm({
                action: 'enroll-free-course.php',
                label: 'Enroll Free',
                slug,
                buttonClass: 'buy-btn purchase-free-enroll',
            });

            if (freeForm) {
                existingControl.replaceWith(freeForm);
                const note = document.createElement('p');
                note.className = 'purchase-flow-note';
                note.textContent = 'No payment, transaction ID, proof upload, or admin verification is required.';
                freeForm.after(note);
            }
            return;
        }

        if (body.querySelector('.purchase-buy-now-form')) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'purchase-detail-actions';
        existingControl.before(wrapper);
        wrapper.append(existingControl);

        const buyForm = createPostForm({
            action: 'buy-now.php',
            label: 'Buy Now',
            slug,
            buttonClass: 'buy-btn purchase-buy-now',
        });

        if (buyForm) {
            wrapper.append(buyForm);
        }
    }

    function renderPurchaseSteps() {
        const currentStep = pageStep[page];
        if (!currentStep || document.querySelector('.purchase-flow')) {
            return;
        }

        const target = document.querySelector(
            '.student-section .container, .course-details-wrapper, .cart-wrapper, .checkout-wrapper, .checkout-success-wrapper, .student-learning-wrapper, main'
        );

        if (!target) {
            return;
        }

        const detailIsFree = page === 'course-details.php'
            && isFreePriceText(document.querySelector('.course-buy-card .buy-price')?.textContent || '');

        const steps = detailIsFree
            ? [
                ['Choose', 'Review the free course'],
                ['Enroll', 'Activate lifetime access'],
                ['Access', 'Start learning immediately'],
            ]
            : [
                ['Choose', 'Select a course'],
                ['Cart', 'Review your selection'],
                ['Checkout', 'Submit payment'],
                ['Verification', 'Admin verifies proof'],
                ['Access', 'Start learning'],
            ];

        const nav = document.createElement('nav');
        nav.className = 'purchase-flow';
        nav.setAttribute('aria-label', detailIsFree ? 'Free course enrollment progress' : 'Course purchase progress');

        const title = document.createElement('p');
        title.className = 'purchase-flow__title';
        title.textContent = detailIsFree ? 'Free course enrollment' : 'Course purchase steps';
        nav.append(title);

        const list = document.createElement('ol');
        list.className = 'purchase-flow__steps';

        steps.forEach(([label, description], index) => {
            const number = index + 1;
            const item = document.createElement('li');
            item.className = 'purchase-flow__step';

            if (number < currentStep) {
                item.classList.add('is-complete');
            } else if (number === currentStep) {
                item.classList.add('is-current');
                item.setAttribute('aria-current', 'step');
            }

            const strong = document.createElement('strong');
            strong.textContent = `${number}. ${label}`;
            const span = document.createElement('span');
            span.textContent = description;
            item.append(strong, span);
            list.append(item);
        });

        nav.append(list);

        if (detailIsFree) {
            const note = document.createElement('p');
            note.className = 'purchase-flow-note';
            note.textContent = 'Free courses skip cart, payment, proof upload, and verification.';
            nav.append(note);
        } else if (page === 'checkout.php' && new URLSearchParams(window.location.search).has('buy_now')) {
            const note = document.createElement('p');
            note.className = 'purchase-flow-note';
            note.textContent = 'Buy Now opened checkout immediately. The order includes the paid courses currently in your cart.';
            nav.append(note);
        }

        target.prepend(nav);
    }

    renderPurchaseSteps();
    addCardBuyButtons();
    addDetailsPurchaseButton();
})();
