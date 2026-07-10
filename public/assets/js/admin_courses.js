document.addEventListener("DOMContentLoaded", function () {
    const courseCards = document.querySelectorAll(".open-course-modal");
    const closeButtons = document.querySelectorAll("[data-close-modal]");
    const modals = document.querySelectorAll(".course-modal-overlay");

    courseCards.forEach(function (card) {
        card.addEventListener("click", function () {
            const targetId = card.getAttribute("data-target");
            const modal = document.getElementById(targetId);

            if (modal) {
                modal.classList.add("show");
            }
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const modal = button.closest(".course-modal-overlay");

            if (modal) {
                modal.classList.remove("show");
            }
        });
    });

    modals.forEach(function (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.classList.remove("show");
            }
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            modals.forEach(function (modal) {
                modal.classList.remove("show");
            });
        }
    });
});