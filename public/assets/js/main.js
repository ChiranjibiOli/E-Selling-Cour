document.addEventListener("DOMContentLoaded", function () {
    const toggles = document.querySelectorAll(".nav-toggle[aria-controls]");

    function closeNavigation(exceptTarget) {
        toggles.forEach(function (toggle) {
            const target = document.getElementById(toggle.getAttribute("aria-controls"));

            if (!target || target === exceptTarget) {
                return;
            }

            target.classList.remove("is-open");
            toggle.setAttribute("aria-expanded", "false");
        });

        document.querySelectorAll(".role-navbar.menu-open").forEach(function (navbar) {
            navbar.classList.remove("menu-open");
        });
    }

    toggles.forEach(function (toggle) {
        const target = document.getElementById(toggle.getAttribute("aria-controls"));

        if (!target) {
            return;
        }

        toggle.addEventListener("click", function () {
            const willOpen = !target.classList.contains("is-open");
            closeNavigation(target);
            target.classList.toggle("is-open", willOpen);
            toggle.setAttribute("aria-expanded", String(willOpen));

            const navbar = toggle.closest(".role-navbar");
            if (navbar) {
                navbar.classList.toggle("menu-open", willOpen);
            }
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeNavigation();
            closeLogoutModal();
        }
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".navbar, .role-navbar, .public-navbar")) {
            closeNavigation();
        }
    });

    const openLogoutModal = document.getElementById("openLogoutModal");
    const logoutModal = document.getElementById("logoutModal");
    const cancelLogout = document.getElementById("cancelLogout");

    function openModal() {
        if (!logoutModal) {
            return;
        }

        logoutModal.classList.add("show");
        document.body.classList.add("modal-open");
        logoutModal.setAttribute("aria-hidden", "false");

        if (cancelLogout) {
            cancelLogout.focus();
        }
    }

    function closeLogoutModal() {
        if (!logoutModal) {
            return;
        }

        logoutModal.classList.remove("show");
        document.body.classList.remove("modal-open");
        logoutModal.setAttribute("aria-hidden", "true");
    }

    if (openLogoutModal) {
        openLogoutModal.addEventListener("click", openModal);
    }

    if (cancelLogout) {
        cancelLogout.addEventListener("click", closeLogoutModal);
    }

    if (logoutModal) {
        logoutModal.addEventListener("click", function (event) {
            if (event.target === logoutModal) {
                closeLogoutModal();
            }
        });
    }

    document.querySelectorAll("[data-confirm]").forEach(function (element) {
        element.addEventListener("click", function (event) {
            const message = element.getAttribute("data-confirm") || "Continue with this action?";
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener("click", function () {
            closeNavigation();
        });
    });

    const currentPath = window.location.pathname.replace(/\/+$/, "") || "/";
    document.querySelectorAll(".nav-links a, .public-nav-menu a, .role-nav-menu a").forEach(function (link) {
        try {
            const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, "") || "/";
            if (linkPath === currentPath) {
                link.classList.add("active");
                link.setAttribute("aria-current", "page");
            }
        } catch (error) {
            // Ignore malformed or JavaScript-only links.
        }
    });

    document.querySelectorAll("img:not([loading])").forEach(function (image) {
        if (!image.closest(".hero, .course-hero, .auth-hero")) {
            image.loading = "lazy";
        }
        image.decoding = "async";
    });

    document.querySelectorAll("table").forEach(function (table) {
        if (table.parentElement && table.parentElement.classList.contains("table-responsive")) {
            return;
        }

        const wrapper = document.createElement("div");
        wrapper.className = "table-responsive";
        wrapper.style.overflowX = "auto";
        wrapper.style.webkitOverflowScrolling = "touch";
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });

    document.querySelectorAll("form").forEach(function (form) {
        form.addEventListener("submit", function () {
            if (!form.checkValidity()) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitButton || submitButton.dataset.keepEnabled === "true") {
                return;
            }

            submitButton.disabled = true;
            submitButton.setAttribute("aria-busy", "true");
            submitButton.classList.add("is-loading");

            if (submitButton.tagName === "BUTTON" && !submitButton.dataset.originalText) {
                submitButton.dataset.originalText = submitButton.textContent.trim();
                submitButton.textContent = submitButton.dataset.loadingText || "Please wait…";
            }

            window.setTimeout(function () {
                submitButton.disabled = false;
                submitButton.removeAttribute("aria-busy");
                submitButton.classList.remove("is-loading");
                if (submitButton.dataset.originalText) {
                    submitButton.textContent = submitButton.dataset.originalText;
                }
            }, 8000);
        });
    });

    document.querySelectorAll('button:not([aria-label])').forEach(function (button) {
        const text = button.textContent.trim();
        if (!text && button.title) {
            button.setAttribute("aria-label", button.title);
        }
    });

    /* Instructor-led course card pipeline: one layout, page-specific features preserved. */
    const getText = function (root, selector, fallback) {
        const node = root.querySelector(selector);
        return node ? node.textContent.trim() : (fallback || "");
    };

    const getHtml = function (root, selector, fallback) {
        const node = root.querySelector(selector);
        return node ? node.innerHTML : (fallback || "");
    };

    const imageData = function (root, selector) {
        const image = root.querySelector(selector);
        return {
            src: image ? image.getAttribute("src") || "assets/images/course-placeholder.svg" : "assets/images/course-placeholder.svg",
            alt: image ? image.getAttribute("alt") || "Course thumbnail" : "Course thumbnail"
        };
    };

    const canonicalCardMarkup = function (data) {
        const meta = (data.meta || []).filter(Boolean).map(function (item) {
            return '<span class="course-pipeline-meta-item">' + item + '</span>';
        }).join("");

        return '' +
            '<div class="course-pipeline-cover">' +
                '<img src="' + data.image.src + '" alt="' + data.image.alt.replace(/"/g, "&quot;") + '">' +
                '<div class="course-pipeline-cover-shade"></div>' +
                (data.category ? '<span class="course-pipeline-category">' + data.category + '</span>' : '') +
                (data.badge ? '<span class="course-pipeline-badge">' + data.badge + '</span>' : '') +
            '</div>' +
            '<div class="course-pipeline-content">' +
                (data.eyebrow ? '<p class="course-pipeline-eyebrow">' + data.eyebrow + '</p>' : '') +
                '<h3 class="course-pipeline-title">' + data.title + '</h3>' +
                (data.description ? '<p class="course-pipeline-description">' + data.description + '</p>' : '') +
                (meta ? '<div class="course-pipeline-meta">' + meta + '</div>' : '') +
                (data.statsHtml ? '<div class="course-pipeline-stats">' + data.statsHtml + '</div>' : '') +
                '<div class="course-pipeline-footer">' +
                    '<strong class="course-pipeline-price">' + data.price + '</strong>' +
                    '<div class="course-pipeline-actions">' + data.actions + '</div>' +
                '</div>' +
            '</div>';
    };

    document.querySelectorAll('.landing-page .course-card').forEach(function (card) {
        if (card.dataset.pipelineReady === '1') return;
        const metaNodes = Array.from(card.querySelectorAll('.course-mini-meta span')).map(function (node) { return node.innerHTML; });
        const infoNodes = Array.from(card.querySelectorAll('.course-info-row span')).map(function (node) { return node.innerHTML; });
        const action = card.querySelector('.course-footer a');
        card.className = 'course-pipeline-card course-card';
        card.innerHTML = canonicalCardMarkup({
            image: imageData(card, '.course-thumb img'),
            category: '',
            badge: getText(card, '.course-badge', ''),
            eyebrow: metaNodes[0] || '',
            title: getHtml(card, 'h3', 'Course title'),
            description: getHtml(card, '.course-card-content > p', ''),
            meta: [metaNodes[1] || ''].concat(infoNodes),
            statsHtml: '',
            price: getHtml(card, '.course-footer strong', 'Rs. 0'),
            actions: action ? action.outerHTML : ''
        });
        card.dataset.pipelineReady = '1';
    });

    document.querySelectorAll('.student-courses-page .student-course-card').forEach(function (card) {
        if (card.dataset.pipelineReady === '1') return;
        const tags = Array.from(card.querySelectorAll('.course-tags span')).map(function (node) { return node.innerHTML; });
        const stats = Array.from(card.querySelectorAll('.course-info > div')).map(function (node) {
            const label = getText(node, 'span', '');
            const value = getText(node, 'strong', '');
            return label && value ? '<div><span>' + label + '</span><strong>' + value + '</strong></div>' : '';
        }).join('');
        const action = card.querySelector('.details-btn');
        const data = {
            image: imageData(card, '.course-image img'),
            category: tags[1] || '',
            badge: tags[0] || '',
            eyebrow: getText(card, '.course-info > div:first-child strong', ''),
            title: getHtml(card, 'h2', 'Course title'),
            description: getHtml(card, '.course-short', ''),
            meta: [],
            statsHtml: stats,
            price: getHtml(card, '.course-price', 'Rs. 0'),
            actions: action ? action.outerHTML : ''
        };
        card.className = 'course-pipeline-card student-course-card';
        card.innerHTML = canonicalCardMarkup(data);
        card.dataset.pipelineReady = '1';
    });

    document.querySelectorAll('.course-library-page .library-course-card').forEach(function (card) {
        if (card.dataset.pipelineReady === '1') return;
        const stats = Array.from(card.querySelectorAll('.course-meta > div')).map(function (node) {
            return '<div><span>' + getText(node, 'span', '') + '</span><strong>' + getText(node, 'strong', '') + '</strong></div>';
        }).join('');
        const actions = Array.from(card.querySelectorAll('.course-actions > *')).map(function (node) {
            return node.outerHTML;
        }).join('');
        const data = {
            image: imageData(card, '.course-cover img'),
            category: getText(card, '.course-id', ''),
            badge: getText(card, '.course-status', ''),
            eyebrow: 'Instructor course',
            title: getHtml(card, '.course-card-body h2', 'Course title'),
            description: getHtml(card, '.course-summary', ''),
            meta: [],
            statsHtml: stats,
            price: getText(card, '.course-meta > div:nth-child(3) strong', 'Rs. 0'),
            actions: actions
        };
        card.className = 'course-pipeline-card library-course-card';
        card.innerHTML = canonicalCardMarkup(data);
        card.dataset.pipelineReady = '1';
    });

    const builderCard = document.querySelector('.course-studio-page .marketplace-card');
    if (builderCard) {
        builderCard.classList.add('course-pipeline-card', 'course-pipeline-live');
        const cover = builderCard.querySelector('.marketplace-cover');
        const content = builderCard.querySelector('.marketplace-content');
        const title = builderCard.querySelector('#previewTitle');
        const description = builderCard.querySelector('#previewDescription');
        const price = builderCard.querySelector('#previewPrice');
        const footer = builderCard.querySelector('.preview-price-row');
        if (cover) cover.classList.add('course-pipeline-cover');
        if (content) content.classList.add('course-pipeline-content');
        if (title) title.classList.add('course-pipeline-title');
        if (description) description.classList.add('course-pipeline-description');
        if (price) price.classList.add('course-pipeline-price');
        if (footer) footer.classList.add('course-pipeline-footer');
        builderCard.dataset.pipelineReady = '1';
    }
});
