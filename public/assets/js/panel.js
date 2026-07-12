"use strict";

document.addEventListener("DOMContentLoaded", () => {
    if (!document.querySelector('link[data-panel-editorial="true"]')) {
        const editorialStyles = document.createElement("link");
        editorialStyles.rel = "stylesheet";
        editorialStyles.href = "assets/css/panel-editorial.css?v=1";
        editorialStyles.dataset.panelEditorial = "true";
        document.head.appendChild(editorialStyles);
    }

    const navStyles = document.createElement("style");
    navStyles.textContent = `
        .authenticated-panel { padding-top: 96px; padding-bottom: 0; }
        .role-navbar {
            position: fixed !important;
            top: 14px !important;
            left: 50% !important;
            z-index: 10000 !important;
            width: min(1460px, calc(100% - 28px)) !important;
            height: 68px !important;
            transform: translateX(-50%) !important;
            border: 1px solid rgba(120,92,46,.18) !important;
            border-radius: 24px !important;
            background: linear-gradient(135deg, rgba(255,252,245,.9), rgba(239,229,212,.8)) !important;
            box-shadow: 0 18px 48px rgba(31,25,18,.14), inset 0 1px 0 rgba(255,255,255,.8) !important;
            -webkit-backdrop-filter: blur(24px) saturate(145%) !important;
            backdrop-filter: blur(24px) saturate(145%) !important;
        }
        .role-nav-container {
            width: 100% !important;
            min-height: 68px !important;
            padding: 0 14px 0 18px !important;
            gap: 18px !important;
        }
        .role-logo { font-size: .95rem !important; }
        .role-logo::before { width: 34px !important; height: 34px !important; margin-right: 9px !important; }
        .role-nav-menu {
            min-width: 0;
            justify-content: center !important;
            gap: 3px !important;
            padding: 6px !important;
            border: 1px solid rgba(120,92,46,.13);
            border-radius: 999px;
            background: rgba(255,255,255,.42);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.75);
            overflow-x: auto;
            scrollbar-width: none;
        }
        .role-nav-menu::-webkit-scrollbar { display: none; }
        .role-nav-menu li { flex: 0 0 auto; }
        .role-nav-menu a,
        .role-logout-btn,
        .student-logout-item .confirm-logout-btn {
            min-height: 38px !important;
            padding: 0 12px !important;
            border-radius: 999px !important;
            color: #3e372f !important;
            font-size: .72rem !important;
            background: transparent !important;
        }
        .role-nav-menu a::before,
        .role-logout-btn::before,
        .student-logout-item .confirm-logout-btn::before {
            content: attr(data-icon);
            margin-right: 7px;
            color: #9a6e23;
            font-size: .95rem;
            line-height: 1;
        }
        .role-nav-menu a:hover,
        .role-nav-menu a.active {
            color: #171511 !important;
            background: #fffdf8 !important;
            box-shadow: 0 7px 18px rgba(39,31,21,.09) !important;
        }
        .role-nav-menu a.active::before { color: #b57d22; }
        .role-logout-btn,
        .student-logout-item .confirm-logout-btn {
            color: #8f3f38 !important;
            background: rgba(167,68,61,.07) !important;
        }
        .nav-toggle { display: none !important; }

        @media (max-width: 900px) {
            .authenticated-panel { padding-top: 14px; padding-bottom: 104px; }
            .role-navbar {
                top: auto !important;
                bottom: 12px !important;
                width: min(620px, calc(100% - 20px)) !important;
                height: 78px !important;
                border-radius: 28px !important;
                background: rgba(255,252,245,.94) !important;
                box-shadow: 0 18px 45px rgba(31,25,18,.22), inset 0 1px 0 rgba(255,255,255,.9) !important;
            }
            .role-nav-container {
                min-height: 78px !important;
                padding: 7px 8px !important;
            }
            .role-logo, .nav-toggle { display: none !important; }
            .role-nav-menu {
                position: static !important;
                display: flex !important;
                width: 100% !important;
                height: 64px !important;
                max-height: none !important;
                padding: 0 2px !important;
                border: 0 !important;
                border-radius: 22px !important;
                background: transparent !important;
                box-shadow: none !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: flex-start !important;
                gap: 0 !important;
                overflow-x: auto !important;
                scroll-snap-type: x proximity;
            }
            .role-nav-menu li { min-width: 74px; scroll-snap-align: center; }
            .role-nav-menu a,
            .role-logout-btn,
            .student-logout-item .confirm-logout-btn {
                width: 100% !important;
                min-height: 58px !important;
                margin: 0 !important;
                padding: 6px 8px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 4px !important;
                border-radius: 18px !important;
                color: #4f473e !important;
                font-size: .63rem !important;
                line-height: 1.05 !important;
                text-align: center !important;
                background: transparent !important;
            }
            .role-nav-menu a::before,
            .role-logout-btn::before,
            .student-logout-item .confirm-logout-btn::before {
                margin: 0 !important;
                color: #6f665d;
                font-size: 1.25rem;
            }
            .role-nav-menu a.active {
                color: #8f641f !important;
                background: rgba(184,137,57,.12) !important;
                box-shadow: none !important;
            }
            .role-nav-menu a.active::before { color: #b57d22 !important; }
            .role-nav-menu form { width: 100%; }
        }

        @media (max-width: 480px) {
            .role-navbar { bottom: 8px !important; width: calc(100% - 12px) !important; }
            .role-nav-menu li { min-width: 68px; }
            .role-nav-menu a,
            .role-logout-btn,
            .student-logout-item .confirm-logout-btn { font-size: .58rem !important; }
        }
    `;
    document.head.appendChild(navStyles);

    document.querySelectorAll(".role-navbar").forEach((navbar) => {
        const toggle = navbar.querySelector(".nav-toggle");
        const menu = navbar.querySelector(".role-nav-menu");
        if (!menu) return;

        if (toggle) {
            toggle.addEventListener("click", () => {
                const open = navbar.classList.toggle("menu-open");
                toggle.setAttribute("aria-expanded", open ? "true" : "false");
            });
        }

        menu.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                navbar.classList.remove("menu-open");
                if (toggle) toggle.setAttribute("aria-expanded", "false");
            });
        });

        const active = menu.querySelector(".active");
        if (active && window.innerWidth <= 900) {
            requestAnimationFrame(() => active.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" }));
        }
    });

    document.querySelectorAll("[data-confirm]").forEach((element) => {
        element.addEventListener("click", (event) => {
            const message = element.dataset.confirm || "Continue with this action?";
            if (!window.confirm(message)) event.preventDefault();
        });
    });

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
            const button = document.createElement("button");
            const formId = form.id || `panel-filter-${index + 1}`;
            form.id = formId;
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
            ".pending-card",
            ".report-list-item",
            ".withdrawal-item"
        ];

        document.querySelectorAll(cardSelectors.join(",")).forEach((card, cardIndex) => {
            if (card.dataset.panelDisclosureReady === "true") return;
            const directChildren = Array.from(card.children).filter((child) => child instanceof HTMLElement);
            if (directChildren.length < 2) return;

            const summary = directChildren[0];
            const content = directChildren.slice(1);
            const contentId = card.id || `panel-disclosure-card-${cardIndex + 1}`;
            card.id = contentId;
            card.dataset.panelDisclosureReady = "true";
            summary.classList.add("panel-disclosure-summary");

            const button = document.createElement("button");
            button.type = "button";
            button.className = "panel-disclosure-toggle";
            button.setAttribute("aria-controls", `${contentId}-content`);
            button.setAttribute("aria-expanded", "false");
            button.textContent = "Details";
            summary.appendChild(button);

            content.forEach((section, index) => {
                section.dataset.panelCollapsibleContent = "true";
                if (index === 0) section.id = `${contentId}-content`;
                section.hidden = true;
            });

            button.addEventListener("click", () => {
                const open = button.getAttribute("aria-expanded") !== "true";
                button.setAttribute("aria-expanded", open ? "true" : "false");
                button.textContent = open ? "Hide" : "Details";
                content.forEach((section) => { section.hidden = !open; });
            });
        });
    }

    function initializeProfilePhotos() {
        const photo = document.querySelector("[data-profile-photo]");
        const viewButtons = document.querySelectorAll("[data-photo-view]");
        const fileInput = document.querySelector("[data-profile-photo-input]");
        const previewImage = document.querySelector("[data-profile-photo-preview]");
        const changeButtons = document.querySelectorAll("[data-photo-change]");

        changeButtons.forEach((button) => {
            button.addEventListener("click", () => {
                if (fileInput instanceof HTMLInputElement) fileInput.click();
            });
        });

        if (fileInput instanceof HTMLInputElement && previewImage instanceof HTMLImageElement) {
            const originalSource = previewImage.src;
            fileInput.addEventListener("change", () => {
                const file = fileInput.files?.[0];
                if (!file) {
                    previewImage.src = originalSource;
                    return;
                }

                if (!/^image\/(jpeg|png|webp)$/i.test(file.type) || file.size > 2 * 1024 * 1024) {
                    fileInput.value = "";
                    window.alert("Choose a JPG, PNG, or WebP image no larger than 2 MB.");
                    previewImage.src = originalSource;
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                previewImage.onload = () => URL.revokeObjectURL(objectUrl);
                previewImage.src = objectUrl;
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
        dialogImage.src = photo.src;
        dialogImage.alt = photo.alt || "Profile photo";
        header.append(title, close);
        dialog.append(header, dialogImage);
        document.body.appendChild(dialog);

        viewButtons.forEach((button) => button.addEventListener("click", () => dialog.showModal()));
        photo.addEventListener("click", () => dialog.showModal());
        photo.tabIndex = 0;
        photo.setAttribute("role", "button");
        photo.setAttribute("aria-label", "View profile photo");
        photo.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                dialog.showModal();
            }
        });
        close.addEventListener("click", () => dialog.close());
        dialog.addEventListener("click", (event) => {
            if (event.target === dialog) dialog.close();
        });
    }
});
