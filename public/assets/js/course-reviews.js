(function () {
    'use strict';

    const page = window.location.pathname.split('/').pop() || '';
    const isDetailsPage = page === 'course-details.php';
    const isLearningPage = page === 'student-course-view.php';

    if (!isDetailsPage && !isLearningPage) {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const query = new URLSearchParams();

    if (isDetailsPage && params.get('slug')) {
        query.set('slug', params.get('slug'));
    } else if (isLearningPage && params.get('course_id')) {
        query.set('course_id', params.get('course_id'));
    } else {
        return;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function starMarkup(rating) {
        const rounded = Math.max(0, Math.min(5, Math.round(Number(rating) || 0)));
        let stars = '';

        for (let index = 1; index <= 5; index += 1) {
            stars += `<span class="${index <= rounded ? 'is-filled' : ''}">★</span>`;
        }

        return stars;
    }

    function renderReviewList(data) {
        const reviews = Array.isArray(data.reviews) ? data.reviews : [];
        const countLabel = `${data.review_count} review${data.review_count === 1 ? '' : 's'}`;
        const target = document.querySelector('.course-main-content') || document.querySelector('.course-details-wrapper');

        if (!target) {
            return;
        }

        const panel = document.createElement('section');
        panel.className = 'course-reviews-panel';
        panel.innerHTML = `
            <div class="course-reviews-heading">
                <div>
                    <h2>Student ratings and feedback</h2>
                    <p>Reviews come only from students with active course access.</p>
                </div>
                <div class="course-rating-summary" aria-label="${escapeHtml(data.average_rating)} out of 5 from ${escapeHtml(countLabel)}">
                    <strong>${data.review_count > 0 ? Number(data.average_rating).toFixed(1) : '—'}</strong>
                    <span>${escapeHtml(countLabel)}</span>
                </div>
            </div>
        `;

        const list = document.createElement('div');
        list.className = 'course-review-list';

        if (reviews.length === 0) {
            list.innerHTML = '<div class="course-review-empty">No student reviews yet. The first verified learner can add one after studying the course.</div>';
        } else {
            reviews.forEach(function (review) {
                const item = document.createElement('article');
                item.className = 'course-review-item';
                item.innerHTML = `
                    <header>
                        <div>
                            <strong>${escapeHtml(review.name)}</strong>
                            <span class="verified-reviewer">Verified learner</span>
                        </div>
                        <div>
                            <span class="course-review-stars" aria-label="${escapeHtml(review.rating)} out of 5 stars">${starMarkup(review.rating)}</span>
                            <small>${escapeHtml(review.updated_at)}</small>
                        </div>
                    </header>
                    <p>${escapeHtml(review.feedback)}</p>
                `;
                list.appendChild(item);
            });
        }

        panel.appendChild(list);
        target.appendChild(panel);
    }

    function renderReviewForm(data) {
        if (!data.can_review) {
            return;
        }

        const target = document.querySelector('.student-learning-wrapper');
        if (!target) {
            return;
        }

        const current = data.current_review || {};
        const currentRating = Number(current.rating) || 0;
        const currentFeedback = String(current.feedback || '');
        const panel = document.createElement('section');
        panel.className = 'course-review-form-panel';
        panel.innerHTML = `
            <h2>${data.current_review ? 'Update your course review' : 'Rate this course'}</h2>
            <p>After studying the course, share a star rating and useful feedback for future students.</p>
            <form class="course-review-form" id="courseReviewForm">
                <input type="hidden" name="_csrf_token" value="${escapeHtml(data.csrf_token)}">
                <input type="hidden" name="course_id" value="${escapeHtml(data.course_id)}">
                <fieldset>
                    <legend>Your rating</legend>
                    <div class="course-star-picker">
                        ${[5, 4, 3, 2, 1].map(function (value) {
                            return `<input type="radio" id="courseRating${value}" name="rating" value="${value}" ${currentRating === value ? 'checked' : ''} required><label for="courseRating${value}" aria-label="${value} stars">★</label>`;
                        }).join('')}
                    </div>
                </fieldset>
                <label for="courseReviewFeedback">Your feedback</label>
                <textarea id="courseReviewFeedback" name="feedback" minlength="10" maxlength="2000" required placeholder="What was useful? What could be clearer?">${escapeHtml(currentFeedback)}</textarea>
                <button class="course-review-submit" type="submit">${data.current_review ? 'Update review' : 'Submit review'}</button>
                <p class="course-review-message" id="courseReviewMessage" aria-live="polite"></p>
            </form>
        `;

        target.appendChild(panel);

        const form = panel.querySelector('#courseReviewForm');
        const message = panel.querySelector('#courseReviewMessage');
        const button = panel.querySelector('.course-review-submit');

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            message.textContent = '';
            message.classList.remove('is-error');
            button.disabled = true;

            try {
                const response = await fetch('submit-course-review.php', {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();

                if (!response.ok || !result.ok) {
                    throw new Error(result.message || 'The review could not be saved.');
                }

                message.textContent = result.message;
                button.textContent = 'Update review';
            } catch (error) {
                message.textContent = error.message || 'The review could not be saved.';
                message.classList.add('is-error');
            } finally {
                button.disabled = false;
            }
        });
    }

    fetch(`course-reviews-feed.php?${query.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Course reviews are unavailable.');
            }
            return response.json();
        })
        .then(function (data) {
            if (!data.ok) {
                return;
            }

            if (isDetailsPage) {
                renderReviewList(data);
            }

            if (isLearningPage) {
                renderReviewForm(data);
            }
        })
        .catch(function () {
            // The course page remains usable when the review feed is unavailable.
        });
})();
