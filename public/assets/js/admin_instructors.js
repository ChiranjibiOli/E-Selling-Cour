"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const instructorCards = [...document.querySelectorAll(".open-instructor-modal")];
    const modals = [...document.querySelectorAll(".instructor-modal-overlay")];
    let activeModal = null;
    let activeTrigger = null;

    const closeModal = (modal, restoreFocus = true) => {
        if (!(modal instanceof HTMLElement)) return;

        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");

        if (activeModal === modal) {
            activeModal = null;
            document.body.classList.remove("instructor-modal-open");

            if (restoreFocus && activeTrigger instanceof HTMLElement) {
                activeTrigger.focus({ preventScroll: true });
            }

            activeTrigger = null;
        }
    };

    const closeAllModals = (except = null, restoreFocus = false) => {
        modals.forEach((modal) => {
            if (modal !== except) closeModal(modal, restoreFocus);
        });
    };

    const openModal = (card) => {
        const targetId = card.getAttribute("data-target");
        const modal = targetId ? document.getElementById(targetId) : null;
        if (!(modal instanceof HTMLElement)) return;

        closeAllModals(modal, false);
        activeModal = modal;
        activeTrigger = card;
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("instructor-modal-open");

        const firstFocusable = modal.querySelector("button, a[href], input, select, textarea");
        if (firstFocusable instanceof HTMLElement) {
            requestAnimationFrame(() => firstFocusable.focus({ preventScroll: true }));
        }
    };

    modals.forEach((modal) => {
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-hidden", "true");

        modal.querySelectorAll("[data-close-modal]").forEach((button) => {
            button.addEventListener("click", () => closeModal(modal));
        });

        modal.addEventListener("click", (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });

    instructorCards.forEach((card) => {
        card.tabIndex = 0;
        card.setAttribute("role", "button");
        card.setAttribute("aria-haspopup", "dialog");

        card.addEventListener("click", (event) => {
            const target = event.target;
            if (target instanceof Element && target.closest("a, button, input, select, textarea, form")) return;
            openModal(card);
        });

        card.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openModal(card);
            }
        });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && activeModal) {
            closeModal(activeModal);
        }
    });
});
