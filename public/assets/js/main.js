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

    function initializeCatalogPagination() {
        const grids = document.querySelectorAll([
            ".student-course-grid",
            ".my-courses-grid",
            ".course-library-grid",
            ".review-page .review-grid",
            ".cart-page .cart-items-list",
            ".admin-orders-page .orders-grid"
        ].join(","));

        grids.forEach(function (grid, gridIndex) {
            if (grid.dataset.paginationReady === "true") {
                return;
            }

            const items = Array.from(grid.children).filter(function (item) {
                return item.matches([
                    ".course-unit-card",
                    ".marketplace-card",
                    ".student-course-card",
                    ".cart-item-card",
                    ".order-card"
                ].join(","));
            });

            const pageSize = Math.max(1, Number.parseInt(grid.dataset.pageSize || "12", 10) || 12);
            const totalPages = Math.ceil(items.length / pageSize);

            grid.dataset.paginationReady = "true";

            if (totalPages <= 1) {
                return;
            }

            const nav = document.createElement("nav");
            nav.className = "catalog-pagination";
            nav.setAttribute("aria-label", "Catalog pages");
            nav.dataset.paginationGrid = String(gridIndex);
            grid.insertAdjacentElement("afterend", nav);

            const url = new URL(window.location.href);
            let currentPage = Math.min(
                totalPages,
                Math.max(1, Number.parseInt(url.searchParams.get("page") || "1", 10) || 1)
            );

            function visiblePageNumbers() {
                if (totalPages <= 7) {
                    return Array.from({ length: totalPages }, function (_, index) {
                        return index + 1;
                    });
                }

                const values = new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
                return Array.from(values)
                    .filter(function (page) { return page >= 1 && page <= totalPages; })
                    .sort(function (a, b) { return a - b; });
            }

            function updateUrl() {
                const nextUrl = new URL(window.location.href);
                if (currentPage === 1) {
                    nextUrl.searchParams.delete("page");
                } else {
                    nextUrl.searchParams.set("page", String(currentPage));
                }
                window.history.replaceState({}, "", nextUrl);
            }

            function makeButton(label, page, disabled, current, ariaLabel) {
                const button = document.createElement("button");
                button.type = "button";
                button.textContent = label;
                button.disabled = disabled;
                button.setAttribute("aria-label", ariaLabel || `Page ${page}`);

                if (current) {
                    button.setAttribute("aria-current", "page");
                }

                button.addEventListener("click", function () {
                    if (disabled || page === currentPage) {
                        return;
                    }

                    currentPage = page;
                    render(true);
                });

                return button;
            }

            function render(shouldScroll) {
                const start = (currentPage - 1) * pageSize;
                const end = start + pageSize;

                items.forEach(function (item, index) {
                    const visible = index >= start && index < end;
                    item.hidden = !visible;
                    item.setAttribute("aria-hidden", visible ? "false" : "true");
                });

                nav.replaceChildren();
                nav.appendChild(makeButton("‹", currentPage - 1, currentPage === 1, false, "Previous page"));

                const pages = visiblePageNumbers();
                pages.forEach(function (page, index) {
                    if (index > 0 && page - pages[index - 1] > 1) {
                        const ellipsis = document.createElement("span");
                        ellipsis.textContent = "…";
                        ellipsis.setAttribute("aria-hidden", "true");
                        nav.appendChild(ellipsis);
                    }

                    nav.appendChild(makeButton(String(page), page, false, page === currentPage));
                });

                nav.appendChild(makeButton("›", currentPage + 1, currentPage === totalPages, false, "Next page"));
                updateUrl();

                if (shouldScroll) {
                    const top = grid.getBoundingClientRect().top + window.scrollY - 112;
                    window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
                }
            }

            render(false);
        });
    }

    initializeCatalogPagination();

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