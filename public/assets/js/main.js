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
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeNavigation();
            closeLogoutModal();
        }
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".navbar, .role-navbar")) {
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
});
