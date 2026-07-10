document.addEventListener("DOMContentLoaded", function () {
    console.log("Admin orders JS loaded");

    document.addEventListener("click", function (event) {
        const orderCard = event.target.closest(".open-order-modal");

        if (orderCard) {
            const targetId = orderCard.getAttribute("data-target");
            const modal = document.getElementById(targetId);

            console.log("Clicked order card:", targetId);

            if (modal) {
                modal.classList.add("show");
                document.body.style.overflow = "hidden";
            } else {
                console.log("Modal not found:", targetId);
            }

            return;
        }

        const closeButton = event.target.closest("[data-close-modal]");

        if (closeButton) {
            const modal = closeButton.closest(".order-modal-overlay");

            if (modal) {
                modal.classList.remove("show");
                document.body.style.overflow = "";
            }

            return;
        }

        if (event.target.classList.contains("order-modal-overlay")) {
            event.target.classList.remove("show");
            document.body.style.overflow = "";
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            document.querySelectorAll(".order-modal-overlay.show").forEach(function (modal) {
                modal.classList.remove("show");
            });

            document.body.style.overflow = "";
        }
    });
});