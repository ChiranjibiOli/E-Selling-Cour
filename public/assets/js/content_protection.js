document.addEventListener("DOMContentLoaded", function () {
    const protectedArea = document.querySelector(".protected-course-area");

    if (protectedArea) {
        protectedArea.addEventListener("contextmenu", function (event) {
            event.preventDefault();
        });

        protectedArea.addEventListener("copy", function (event) {
            event.preventDefault();
        });

        protectedArea.addEventListener("cut", function (event) {
            event.preventDefault();
        });

        protectedArea.addEventListener("dragstart", function (event) {
            event.preventDefault();
        });

        protectedArea.addEventListener("selectstart", function (event) {
            event.preventDefault();
        });
    }

    document.addEventListener("keydown", function (event) {
        const key = event.key.toLowerCase();

        if (
            event.key === "F12" ||
            (event.ctrlKey && key === "u") ||
            (event.ctrlKey && key === "s") ||
            (event.ctrlKey && key === "p") ||
            (event.ctrlKey && key === "c") ||
            (event.ctrlKey && key === "x") ||
            (event.ctrlKey && key === "a") ||
            (event.ctrlKey && event.shiftKey && key === "i") ||
            (event.ctrlKey && event.shiftKey && key === "j") ||
            (event.ctrlKey && event.shiftKey && key === "c") ||
            (event.metaKey && key === "c") ||
            (event.metaKey && key === "s") ||
            (event.metaKey && key === "p")
        ) {
            event.preventDefault();
            return false;
        }
    });

    const fullscreenButtons = document.querySelectorAll("[data-fullscreen-target]");

    fullscreenButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const targetId = button.getAttribute("data-fullscreen-target");
            const target = document.getElementById(targetId);

            if (!target) {
                return;
            }

            if (!document.fullscreenElement) {
                target.requestFullscreen().then(function () {
                    button.textContent = "Exit Fullscreen";
                }).catch(function () {
                    alert("Fullscreen is not supported in this browser.");
                });
            } else {
                document.exitFullscreen();
            }
        });
    });

    document.addEventListener("fullscreenchange", function () {
        fullscreenButtons.forEach(function (button) {
            const targetId = button.getAttribute("data-fullscreen-target");
            const target = document.getElementById(targetId);

            if (document.fullscreenElement === target) {
                button.textContent = "Exit Fullscreen";
            } else {
                button.textContent = "View Fullscreen";
            }
        });
    });
});