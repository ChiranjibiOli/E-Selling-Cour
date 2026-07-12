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
});
