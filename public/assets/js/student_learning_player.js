document.addEventListener("DOMContentLoaded", function () {
    console.log("Student learning player loaded");

    const player = document.getElementById("learningPlayer");
    const lessonButtons = document.querySelectorAll("[data-lesson-id]");
    const lessonPanels = document.querySelectorAll("[data-lesson-panel]");
    const focusModeBtn = document.getElementById("focusModeBtn");
    const toggleSidebarBtn = document.getElementById("toggleSidebarBtn");

    console.log("Lesson buttons:", lessonButtons.length);
    console.log("Lesson panels:", lessonPanels.length);

    function activateLesson(lessonId) {
        lessonId = String(lessonId || "").trim();

        console.log("Trying to activate lesson:", lessonId);

        if (lessonId === "" || lessonId === "0") {
            console.log("Invalid lesson id:", lessonId);
            return;
        }

        const targetPanel = document.querySelector('[data-lesson-panel="' + lessonId + '"]');

        if (!targetPanel) {
            console.log("Target lesson panel not found:", lessonId);
            return;
        }

        lessonButtons.forEach(function (button) {
            const buttonLessonId = button.getAttribute("data-lesson-id");

            if (buttonLessonId === lessonId) {
                button.classList.add("active");
            } else {
                button.classList.remove("active");
            }
        });

        lessonPanels.forEach(function (panel) {
            const panelLessonId = panel.getAttribute("data-lesson-panel");

            if (panelLessonId === lessonId) {
                panel.classList.add("active");
            } else {
                panel.classList.remove("active");
            }
        });

        const activeButton = document.querySelector('[data-lesson-id="' + lessonId + '"]');

        if (activeButton) {
            activeButton.scrollIntoView({
                behavior: "smooth",
                block: "nearest"
            });
        }

        const activeTextContent = targetPanel.querySelector(".lesson-html-content");

        if (activeTextContent) {
            activeTextContent.scrollTop = 0;
        }

        const activePdf = targetPanel.querySelector(".pdf-viewer-shell iframe");

        if (activePdf) {
            const oldSrc = activePdf.getAttribute("src");
            activePdf.setAttribute("src", oldSrc);
        }

        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set("lesson_id", lessonId);
        window.history.replaceState({}, "", newUrl.toString());
    }

    lessonButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const lessonId = button.getAttribute("data-lesson-id");
            activateLesson(lessonId);
        });
    });

    document.addEventListener("click", function (event) {
        const navButton = event.target.closest("[data-go-lesson]");

        if (!navButton) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const lessonId = navButton.getAttribute("data-go-lesson");

        console.log("Navigation button clicked:", lessonId);

        if (!lessonId || lessonId === "0" || navButton.disabled) {
            console.log("Navigation button disabled or invalid:", lessonId);
            return;
        }

        activateLesson(lessonId);
    });

    if (focusModeBtn && player) {
        focusModeBtn.addEventListener("click", function () {
            player.classList.toggle("focus-mode");

            if (player.classList.contains("focus-mode")) {
                focusModeBtn.textContent = "Exit Focus Mode";
                document.body.style.overflow = "hidden";
            } else {
                focusModeBtn.textContent = "Enter Focus Mode";
                document.body.style.overflow = "";
            }
        });
    }

    if (toggleSidebarBtn && player) {
        toggleSidebarBtn.addEventListener("click", function () {
            player.classList.toggle("sidebar-hidden");

            if (player.classList.contains("sidebar-hidden")) {
                toggleSidebarBtn.textContent = "Show Outline";
            } else {
                toggleSidebarBtn.textContent = "Hide Outline";
            }
        });
    }

    document.addEventListener("keydown", function (event) {
        const key = event.key.toLowerCase();

        if (key === "escape" && player && player.classList.contains("focus-mode")) {
            player.classList.remove("focus-mode");
            document.body.style.overflow = "";

            if (focusModeBtn) {
                focusModeBtn.textContent = "Enter Focus Mode";
            }
        }

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
});