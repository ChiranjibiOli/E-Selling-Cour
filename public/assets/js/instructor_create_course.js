"use strict";

(() => {
    const installCategoryCreatorStyles = () => {
        if (document.getElementById("courseCategoryCreatorStyles")) {
            return;
        }

        const style = document.createElement("style");
        style.id = "courseCategoryCreatorStyles";
        style.textContent = `
            .category-creator{margin-top:10px;padding:11px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc}
            .category-creator-toggle{display:inline-flex;min-height:35px;align-items:center;justify-content:center;padding:0 11px;border:0;border-radius:10px;color:#4338ca;background:#eef2ff;font:inherit;font-size:.72rem;font-weight:900;cursor:pointer}
            .category-creator-panel{display:grid;gap:8px;margin-top:10px}
            .category-creator-panel[hidden]{display:none}
            .category-creator-panel label{color:#344054;font-size:.72rem;font-weight:900}
            .category-creator-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
            .category-creator-row input{min-width:0}
            .category-creator-submit{min-height:40px;padding:0 13px;border:0;border-radius:10px;color:#fff;background:#0d2b31;font:inherit;font-size:.72rem;font-weight:900;cursor:pointer}
            .category-creator-submit:disabled{cursor:wait;opacity:.65}
            .category-creator-help,.category-creator-status{margin:0;color:#667085;font-size:.67rem;line-height:1.5}
            .category-creator-status.is-success{color:#067647}
            .category-creator-status.is-error{color:#b42318}
            @media(max-width:620px){.category-creator-row{grid-template-columns:1fr}.category-creator-submit{width:100%}}
        `;
        document.head.appendChild(style);
    };

    const initializeCategoryCreator = () => {
        const form = document.getElementById("courseBuilderForm");
        const select = document.getElementById("category_id");

        if (!form || !select || select.disabled || select.dataset.categoryCreatorReady === "1") {
            return;
        }

        const fieldGroup = select.closest(".field-group");
        if (!fieldGroup) {
            return;
        }

        select.dataset.categoryCreatorReady = "1";
        installCategoryCreatorStyles();

        const creator = document.createElement("div");
        creator.className = "category-creator";

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "category-creator-toggle";
        toggle.setAttribute("aria-expanded", "false");
        toggle.textContent = "+ Create a new category";

        const panel = document.createElement("div");
        panel.className = "category-creator-panel";
        panel.hidden = true;

        const label = document.createElement("label");
        label.htmlFor = "new_category_name";
        label.textContent = "New category name";

        const row = document.createElement("div");
        row.className = "category-creator-row";

        const input = document.createElement("input");
        input.id = "new_category_name";
        input.type = "text";
        input.maxLength = 100;
        input.autocomplete = "off";
        input.placeholder = "Example: Digital Marketing";

        const submit = document.createElement("button");
        submit.type = "button";
        submit.className = "category-creator-submit";
        submit.textContent = "Create category";

        const help = document.createElement("p");
        help.className = "category-creator-help";
        help.textContent = "Use this only when no existing category fits. The category becomes available here and in the six-card landing collection.";

        const status = document.createElement("p");
        status.className = "category-creator-status";
        status.setAttribute("role", "status");
        status.setAttribute("aria-live", "polite");

        row.append(input, submit);
        panel.append(label, row, help, status);
        creator.append(toggle, panel);
        fieldGroup.appendChild(creator);

        const setStatus = (message, type = "") => {
            status.textContent = message;
            status.classList.toggle("is-success", type === "success");
            status.classList.toggle("is-error", type === "error");
        };

        const setPanelOpen = (open) => {
            panel.hidden = !open;
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
            toggle.textContent = open ? "Cancel new category" : "+ Create a new category";
            if (open) {
                input.focus();
            }
        };

        toggle.addEventListener("click", () => {
            setStatus("");
            setPanelOpen(panel.hidden);
        });

        const createCategory = async () => {
            const name = input.value.replace(/\s+/g, " ").trim();

            if (name.length < 3 || name.length > 100) {
                setStatus("Enter a category name between 3 and 100 characters.", "error");
                input.focus();
                return;
            }

            const csrfInput = form.querySelector('input[name="_csrf_token"]');
            if (!csrfInput || !csrfInput.value) {
                setStatus("Your form session is unavailable. Refresh the page and try again.", "error");
                return;
            }

            submit.disabled = true;
            submit.textContent = "Creating…";
            setStatus("Checking and creating the category…");

            try {
                const payload = new FormData();
                payload.append("_csrf_token", csrfInput.value);
                payload.append("name", name);

                const response = await fetch("instructor-create-category.php", {
                    method: "POST",
                    body: payload,
                    credentials: "same-origin",
                    headers: { Accept: "application/json" },
                });

                const responseText = await response.text();
                let data = null;

                try {
                    data = JSON.parse(responseText);
                } catch (_) {
                    data = null;
                }

                if (!response.ok || !data?.ok) {
                    throw new Error(data?.message || "The category could not be created.");
                }

                const categoryId = Number.parseInt(data.category?.id || "0", 10);
                const categoryName = String(data.category?.name || "").trim();

                if (categoryId < 1 || !categoryName) {
                    throw new Error("The server returned an invalid category.");
                }

                let option = Array.from(select.options).find((item) => item.value === String(categoryId));
                if (!option) {
                    option = document.createElement("option");
                    option.value = String(categoryId);
                    select.appendChild(option);
                }

                option.textContent = categoryName;
                option.dataset.categoryName = categoryName;
                select.value = String(categoryId);
                select.dispatchEvent(new Event("change", { bubbles: true }));
                select.dispatchEvent(new Event("input", { bubbles: true }));

                input.value = "";
                setStatus(data.message || "Category created and selected.", "success");
                toggle.textContent = "+ Create another category";
                toggle.setAttribute("aria-expanded", "true");
            } catch (error) {
                setStatus(error instanceof Error ? error.message : "The category could not be created.", "error");
                input.focus();
            } finally {
                submit.disabled = false;
                submit.textContent = "Create category";
            }
        };

        submit.addEventListener("click", createCategory);
        input.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                createCategory();
            }
        });
    };

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

    const initializeEnhancements = () => {
        initializeHeroControls();
        initializeCategoryCreator();
    };

    const originalScript = document.createElement("script");
    originalScript.src = "assets/js/instructor_create_course_original.js?v=1";
    originalScript.async = false;
    originalScript.addEventListener("load", () => {
        initializeEnhancements();

        if (document.readyState !== "loading") {
            document.dispatchEvent(new Event("DOMContentLoaded"));
        }
    });
    document.head.appendChild(originalScript);

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeEnhancements, { once: true });
    } else {
        initializeEnhancements();
    }
})();