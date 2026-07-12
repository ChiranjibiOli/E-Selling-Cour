"use strict";

(() => {
    const initializeHeroControls = () => {
        const hero = document.querySelector(".course-studio-page .studio-hero");

        if (!hero || hero.dataset.heroEnhanced === "1") {
            return;
        }

        hero.dataset.heroEnhanced = "1";

        const eyebrow = hero.querySelector(".studio-eyebrow");
        const title = hero.querySelector(".studio-title-row h1");
        const intro = hero.querySelector(".studio-intro");

        if (eyebrow) eyebrow.textContent = "Course creation workspace";
        if (title) title.textContent = "Create a course students can follow with confidence";
        if (intro) {
            intro.textContent = "Add the course details, organize chapters and lessons, preview the student experience, then save privately or submit it for admin review.";
        }

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "studio-hero-toggle";
        toggle.setAttribute("aria-controls", "courseHeroContent");

        const label = document.createElement("span");
        label.className = "studio-hero-toggle-label";

        const arrow = document.createElement("span");
        arrow.className = "studio-hero-toggle-arrow";
        arrow.setAttribute("aria-hidden", "true");
        arrow.textContent = "⌃";

        toggle.append(label, arrow);
        hero.appendChild(toggle);

        const storedState = sessionStorage.getItem("courseStudioHero");
        const startCollapsed = storedState === "collapsed";

        const applyState = (collapsed) => {
            hero.classList.toggle("is-compact", collapsed);
            toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
            label.textContent = collapsed ? "Show full introduction" : "Show less";
            arrow.textContent = collapsed ? "⌄" : "⌃";
            sessionStorage.setItem("courseStudioHero", collapsed ? "collapsed" : "expanded");
        };

        applyState(startCollapsed);

        toggle.addEventListener("click", () => {
            applyState(!hero.classList.contains("is-compact"));
        });
    };

    const originalScript = document.createElement("script");
    originalScript.src = "assets/js/instructor_create_course_original.js?v=1";
    originalScript.async = false;
    originalScript.addEventListener("load", () => {
        initializeHeroControls();

        if (document.readyState !== "loading") {
            document.dispatchEvent(new Event("DOMContentLoaded"));
        }
    });
    document.head.appendChild(originalScript);

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeHeroControls, { once: true });
    } else {
        initializeHeroControls();
    }
})();
