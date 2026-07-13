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

    function createBuyNowForm({ courseId = '', slug = '', buttonClass = '' }) {
        if (!csrfToken || (!courseId && !slug)) {
            return null;
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'buy-now.php';
        form.className = 'purchase-buy-now-form';
        form.append(createHidden('_csrf_token', csrfToken));

        if (courseId) {
            form.append(createHidden('course_id', courseId));
        } else {
            form.append(createHidden('slug', slug));
        }

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = buttonClass;
        button.textContent = 'Buy Now';
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

    function addCardBuyButtons() {
        document.querySelectorAll('.course-unit-card').forEach((card) => {
            const actions = card.querySelector('.course-unit-actions');
            if (!actions || actions.querySelector('.purchase-buy-now-form')) {
                return;
            }

            const labels = Array.from(actions.querySelectorAll('a, button, span'))
                .map((control) => (control.textContent || '').trim().toLowerCase());

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

            const form = createBuyNowForm({
                slug,
                buttonClass: 'course-unit-action course-unit-action--primary',
            });

            if (form) {
                actions.append(form);
            }
        });
    }

    function addDetailsBuyButton() {
        if (page !== 'course-details.php') {
            return;
        }

        const body = document.querySelector('.course-buy-card .buy-body');
        if (!body || body.querySelector('.purchase-buy-now-form')) {
            return;
        }

        const existingControl = Array.from(body.querySelectorAll(':scope > a.buy-btn, :scope > form'))
            .find((control) => {
                const label = (control.textContent || '').trim().toLowerCase();
                return label === 'add to cart' || label === 'go to cart';
            });

        if (!existingControl) {
            return;
        }

        const slug = new URLSearchParams(window.location.search).get('slug') || '';
        if (!slug) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'purchase-detail-actions';
        existingControl.before(wrapper);
        wrapper.append(existingControl);

        const form = createBuyNowForm({
            slug,
            buttonClass: 'buy-btn purchase-buy-now',
        });

        if (form) {
            wrapper.append(form);
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

        const steps = [
            ['Choose', 'Select a course'],
            ['Cart', 'Review your selection'],
            ['Checkout', 'Submit payment'],
            ['Verification', 'Admin verifies proof'],
            ['Access', 'Start learning'],
        ];

        const nav = document.createElement('nav');
        nav.className = 'purchase-flow';
        nav.setAttribute('aria-label', 'Course purchase progress');

        const title = document.createElement('p');
        title.className = 'purchase-flow__title';
        title.textContent = 'Course purchase steps';
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

        if (page === 'checkout.php' && new URLSearchParams(window.location.search).has('buy_now')) {
            const note = document.createElement('p');
            note.className = 'purchase-flow-note';
            note.textContent = 'Buy Now opened checkout immediately. The order includes the purchasable courses currently in your cart.';
            nav.append(note);
        }

        target.prepend(nav);
    }

    renderPurchaseSteps();
    addCardBuyButtons();
    addDetailsBuyButton();
})();
