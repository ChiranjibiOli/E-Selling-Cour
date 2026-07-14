"use strict";

document.addEventListener("DOMContentLoaded", () => {
    loadStylesheet("assets/css/panel-editorial.css?v=4", "panel-editorial");
    loadStylesheet("assets/css/panel-navigation.css?v=3", "panel-navigation");
    loadStylesheet("assets/css/panel-sections.css?v=3", "panel-sections");
    loadStylesheet("assets/css/panel-final.css?v=2", "panel-final");
    loadStylesheet("assets/css/panel-refined.css?v=1", "panel-refined");

    initializeNavigation();
    initializeConfirmations();

    const courseSystemActive = Boolean(document.querySelector([
        ".course-studio-page",
        ".student-courses-page",
        ".browse-courses-page",
        ".student-my-courses-page",
        ".instructor-my-courses-page",
        ".admin-courses-page",
        ".course-details-page",
        ".cart-page"
    ].join(",")));

    if (!courseSystemActive) {
        initializeCollapsibleFilters();
        initializeDisclosureCards();
    }

    initializeProfilePhotos();

    function loadStylesheet(href, key) {
        if (document.querySelector(`link[data-panel-style="${key}"]`)) return;
        const stylesheet = document.createElement("link");
        stylesheet.rel = "stylesheet";
        stylesheet.href = href;
        stylesheet.dataset.panelStyle = key;
        document.head.appendChild(stylesheet);
    }

    function initializeNavigation() {
        document.querySelectorAll(".role-navbar").forEach((navbar) => {
            const menu = navbar.querySelector(".role-nav-menu");
            if (!menu) return;

            const toggle = document.querySelector(`[data-panel-menu-toggle][aria-controls="${menu.id}"]`)
                || navbar.querySelector(".nav-toggle");
            const scrim = document.querySelector("[data-panel-sidebar-scrim]");

            const setOpen = (open) => {
                navbar.classList.toggle("menu-open", open);
                document.body.classList.toggle("panel-nav-open", open);
                if (toggle) toggle.setAttribute("aria-expanded", open ? "true" : "false");
            };

            if (toggle) {
                toggle.addEventListener("click", () => {
                    setOpen(!navbar.classList.contains("menu-open"));
                });
            }

            if (scrim) scrim.addEventListener("click", () => setOpen(false));

            menu.querySelectorAll("a, button").forEach((control) => {
                control.addEventListener("click", () => setOpen(false));
            });

            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") setOpen(false);
            });

            window.addEventListener("resize", () => {
                if (window.innerWidth > 1024) setOpen(false);
            });

            const active = menu.querySelector(".active");
            if (active && window.innerWidth <= 1024) {
                requestAnimationFrame(() => {
                    active.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
                });
            }
        });
    }

    function initializeConfirmations() {
        document.querySelectorAll("[data-confirm]").forEach((element) => {
            element.addEventListener("click", (event) => {
                const message = element.dataset.confirm || "Continue with this action?";
                if (!window.confirm(message)) event.preventDefault();
            });
        });
    }

    function initializeCollapsibleFilters() {
        const selectors = [
            ".user-filter-box",
            ".order-filter-box",
            ".report-filter-box",
            ".withdrawal-filter-box",
            ".sales-filter-box",
            ".instructor-filter-box",
            ".notification-filter-box",
            ".filter-box",
            ".filter-form"
        ];

        document.querySelectorAll(selectors.join(",")).forEach((form, index) => {
            if (!(form instanceof HTMLFormElement) || form.dataset.panelFilterReady === "true") return;
            form.dataset.panelFilterReady = "true";

            const meaningfulValue = Array.from(form.elements).some((field) => {
                if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return false;
                if (!field.name || field.name === "_csrf_token" || field.type === "submit" || field.type === "button") return false;
                if (field instanceof HTMLInputElement && ["checkbox", "radio"].includes(field.type)) return field.checked;
                const value = String(field.value || "").trim();
                return value !== "" && value !== "0" && value.toLowerCase() !== "all";
            });

            const hasQuery = new URL(window.location.href).searchParams.size > 0;
            const startsOpen = meaningfulValue || hasQuery;
            const formId = form.id || `panel-filter-${index + 1}`;
            form.id = formId;

            const button = document.createElement("button");
            button.type = "button";
            button.className = "panel-filter-toggle";
            button.setAttribute("aria-controls", formId);
            button.setAttribute("aria-expanded", startsOpen ? "true" : "false");
            button.textContent = startsOpen ? "Hide filters" : "Show filters";
            form.hidden = !startsOpen;
            form.insertAdjacentElement("beforebegin", button);

            button.addEventListener("click", () => {
                const open = button.getAttribute("aria-expanded") !== "true";
                button.setAttribute("aria-expanded", open ? "true" : "false");
                button.textContent = open ? "Hide filters" : "Show filters";
                form.hidden = !open;
                if (open) form.querySelector("input, select, textarea")?.focus({ preventScroll: true });
            });
        });
    }

    function initializeDisclosureCards() {
        const cardSelectors = [
            ".user-card",
            ".student-card",
            ".instructor-card",
            ".order-card",
            ".withdrawal-request-card",
            ".notification-card",
            ".report-list-item",
            ".withdrawal-item"
        ];

        const entries = [];

        const closeEntry = (entry) => {
            entry.button.setAttribute("aria-expanded", "false");
            entry.button.textContent = "Details";
            entry.card.classList.remove("is-open");
            entry.content.forEach((section) => { section.hidden = true; });
        };

        const openEntry = (entry) => {
            entries.forEach((candidate) => {
                if (candidate !== entry && candidate.card.parentElement === entry.card.parentElement) {
                    closeEntry(candidate);
                }
            });

            entry.button.setAttribute("aria-expanded", "true");
            entry.button.textContent = "Hide";
            entry.card.classList.add("is-open");
            entry.content.forEach((section) => { section.hidden = false; });
        };

        const toggleEntry = (entry) => {
            const shouldOpen = entry.button.getAttribute("aria-expanded") !== "true";
            if (shouldOpen) {
                openEntry(entry);
            } else {
                closeEntry(entry);
            }
        };

        document.querySelectorAll(cardSelectors.join(",")).forEach((card, cardIndex) => {
            if (card.dataset.panelDisclosureReady === "true" || card.matches(".open-instructor-modal")) return;

            const directChildren = Array.from(card.children).filter((child) => child instanceof HTMLElement);
            if (directChildren.length < 2) return;

            const summary = directChildren[0];
            const content = directChildren.slice(1);
            const cardId = card.id || `panel-disclosure-card-${cardIndex + 1}`;
            card.id = cardId;
            card.dataset.panelDisclosureReady = "true";
            summary.classList.add("panel-disclosure-summary");

            const button = document.createElement("button");
            button.type = "button";
            button.className = "panel-disclosure-toggle";
            button.setAttribute("aria-controls", `${cardId}-content`);
            button.setAttribute("aria-expanded", "false");
            button.textContent = "Details";
            summary.appendChild(button);

            content.forEach((section, index) => {
                section.dataset.panelCollapsibleContent = "true";
                if (index === 0) section.id = `${cardId}-content`;
                section.hidden = true;
            });

            const entry = { card, summary, button, content };
            entries.push(entry);

            button.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleEntry(entry);
            });

            summary.addEventListener("click", (event) => {
                const target = event.target;
                if (target instanceof Element && target.closest("a, button, input, select, textarea, label, form")) return;
                toggleEntry(entry);
            });
        });

        document.addEventListener("keydown", (event) => {
            if (event.key !== "Escape") return;
            entries.forEach(closeEntry);
        });
    }

    function initializeProfilePhotos() {
        const photo = document.querySelector("[data-profile-photo]");
        const viewButtons = document.querySelectorAll("[data-photo-view]");
        const fileInput = document.querySelector("[data-profile-photo-input]");
        const previewImage = document.querySelector("[data-profile-photo-preview]");
        const changeButtons = document.querySelectorAll("[data-photo-change]");

        if (previewImage instanceof HTMLImageElement && previewImage.src.includes("course-placeholder.svg")) {
            previewImage.src = "assets/images/profile-placeholder.svg";
        }

        changeButtons.forEach((button) => {
            button.addEventListener("click", () => {
                if (fileInput instanceof HTMLInputElement) fileInput.click();
            });
        });

        let activeObjectUrl = null;
        const originalPreviewSource = previewImage instanceof HTMLImageElement ? previewImage.src : "";
        const originalPhotoSource = photo instanceof HTMLImageElement ? photo.src : "";

        const applyPreviewSource = (source) => {
            if (previewImage instanceof HTMLImageElement) previewImage.src = source;
            if (photo instanceof HTMLImageElement) photo.src = source;
        };

        const restoreOriginalSource = () => {
            if (previewImage instanceof HTMLImageElement && originalPreviewSource) previewImage.src = originalPreviewSource;
            if (photo instanceof HTMLImageElement && originalPhotoSource) photo.src = originalPhotoSource;
        };

        if (fileInput instanceof HTMLInputElement) {
            fileInput.addEventListener("change", () => {
                const file = fileInput.files?.[0];
                if (!file) {
                    restoreOriginalSource();
                    return;
                }

                if (!/^image\/(jpeg|png|webp)$/i.test(file.type) || file.size > 2 * 1024 * 1024) {
                    fileInput.value = "";
                    window.alert("Choose a JPG, PNG, or WebP image no larger than 2 MB.");
                    restoreOriginalSource();
                    return;
                }

                if (activeObjectUrl) URL.revokeObjectURL(activeObjectUrl);
                activeObjectUrl = URL.createObjectURL(file);
                applyPreviewSource(activeObjectUrl);
            });
        }

        if (!(photo instanceof HTMLImageElement) || viewButtons.length === 0 || typeof HTMLDialogElement === "undefined") return;

        const dialog = document.createElement("dialog");
        dialog.className = "profile-photo-dialog";

        const header = document.createElement("div");
        header.className = "profile-photo-dialog-header";

        const title = document.createElement("strong");
        title.textContent = photo.alt || "Profile photo";

        const close = document.createElement("button");
        close.type = "button";
        close.className = "profile-photo-dialog-close";
        close.setAttribute("aria-label", "Close photo viewer");
        close.textContent = "×";

        const dialogImage = document.createElement("img");
        dialogImage.alt = photo.alt || "Profile photo";

        header.append(title, close);
        dialog.append(header, dialogImage);
        document.body.appendChild(dialog);

        const showPhoto = () => {
            dialogImage.src = photo.src;
            dialog.showModal();
        };

        viewButtons.forEach((button) => button.addEventListener("click", showPhoto));
        photo.addEventListener("click", showPhoto);
        photo.tabIndex = 0;
        photo.setAttribute("role", "button");
        photo.setAttribute("aria-label", "View profile photo");
        photo.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                showPhoto();
            }
        });
        close.addEventListener("click", () => dialog.close());
        dialog.addEventListener("click", (event) => {
            if (event.target === dialog) dialog.close();
        });
    }
});
