document.addEventListener("DOMContentLoaded", function () {
    const instructorCards = document.querySelectorAll(".open-instructor-modal");
    const closeButtons = document.querySelectorAll("[data-close-modal]");
    const modals = document.querySelectorAll(".instructor-modal-overlay");

    instructorCards.forEach(function (card) {
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
            const modal = button.closest(".instructor-modal-overlay");

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