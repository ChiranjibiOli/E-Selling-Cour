"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("courseBuilderForm");

    if (!form) {
        return;
    }

    const chapterList = document.getElementById("chapterList");
    const emptyBuilder = document.getElementById("emptyBuilder");
    const addChapterButton = document.getElementById("addChapter");
    const emptyAddChapterButton = document.getElementById("emptyAddChapter");
    const expandAllButton = document.getElementById("expandAllChapters");
    const thumbnailInput = document.getElementById("thumbnail");
    const thumbnailDropzone = document.getElementById("thumbnailDropzone");
    const previewImage = document.getElementById("previewImage");
    const submitReviewButton = document.getElementById("submitReview");
    const publishedEdit = document.querySelector(".course-studio-page")?.dataset.publishedEdit === "1";
    const originalThumbnailSource = previewImage?.getAttribute("src") || "assets/images/course-placeholder.svg";
    let generatedSequence = Date.now();
    let activeObjectUrl = null;

    const fields = {
        title: document.getElementById("title"),
        category: document.getElementById("category_id"),
        price: document.getElementById("price"),
        level: document.getElementById("level"),
        language: document.getElementById("language"),
        shortDescription: document.getElementById("short_description"),
        fullDescription: document.getElementById("full_description"),
    };

    const preview = {
        title: document.getElementById("previewTitle"),
        category: document.getElementById("previewCategory"),
        price: document.getElementById("previewPrice"),
        level: document.getElementById("previewLevel"),
        language: document.getElementById("previewLanguage"),
        duration: document.getElementById("previewDuration"),
        description: document.getElementById("previewDescription"),
        outline: document.getElementById("previewOutline"),
        outlineCount: document.getElementById("outlineCount"),
        curriculumSummary: document.getElementById("curriculumSummary"),
        qualityScore: document.getElementById("qualityScore"),
        qualityBar: document.getElementById("qualityBar"),
        qualityMessage: document.getElementById("qualityMessage"),
        submitStateTitle: document.getElementById("submitStateTitle"),
        submitStateText: document.getElementById("submitStateText"),
    };

    const generatedKey = (prefix) => `${prefix}_${++generatedSequence}`;
    const safeName = (value) => String(value).replace(/[^A-Za-z0-9_-]/g, "");
    const cleanDisplayText = (value, fallback) => {
        const text = String(value || "").replace(/\s+/g, " ").trim();
        return text || fallback;
    };

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function lessonMarkup(sectionKey, lessonKey) {
        const section = safeName(sectionKey);
        const lesson = safeName(lessonKey);

        return `
            <article class="lesson-card" data-lesson-key="${lesson}">
                <input type="hidden" name="lesson_keys[${section}][]" value="${lesson}">
                <input type="hidden" name="lesson_id[${section}][${lesson}]" value="0">
                <div class="lesson-order">L1</div>
                <div class="lesson-editor">
                    <div class="lesson-primary-row">
                        <div class="lesson-title-wrap">
                            <label>Lesson title</label>
                            <input name="lesson_title[${section}][${lesson}]" maxlength="180" placeholder="Describe this lesson clearly">
                        </div>
                        <div class="lesson-type-wrap">
                            <label>Content type</label>
                            <select class="lesson-type" name="lesson_type[${section}][${lesson}]">
                                <option value="text">Text lesson</option>
                                <option value="video">Video URL</option>
                                <option value="link">External link</option>
                                <option value="pdf">PDF document</option>
                                <option value="word">Word document</option>
                            </select>
                        </div>
                    </div>
                    <div class="lesson-content-panels">
                        <div class="lesson-content-field text-field">
                            <label>Lesson content</label>
                            <textarea name="lesson_content[${section}][${lesson}]" rows="7" placeholder="Write the learning material. Safe headings, paragraphs and lists are supported."></textarea>
                        </div>
                        <div class="lesson-content-field url-field">
                            <label>Secure HTTP(S) URL</label>
                            <input type="url" maxlength="2048" name="lesson_url[${section}][${lesson}]" placeholder="https://example.com/resource">
                        </div>
                        <div class="lesson-content-field file-field">
                            <label>Private lesson document</label>
                            <input type="file" name="lesson_file_${section}_${lesson}">
                            <small>Documents are stored outside the public web root and served only after authorization.</small>
                        </div>
                    </div>
                    <div class="lesson-meta-row">
                        <label class="duration-field"><span>Duration</span><input type="number" min="0" max="1440" name="lesson_duration[${section}][${lesson}]" value="0"><small>minutes</small></label>
                        <label class="preview-toggle"><input type="checkbox" name="lesson_preview[${section}][${lesson}]" value="1"><span></span><strong>Free preview</strong></label>
                        <div class="lesson-actions">
                            <button type="button" class="icon-action move-lesson-up" aria-label="Move lesson up">↑</button>
                            <button type="button" class="icon-action move-lesson-down" aria-label="Move lesson down">↓</button>
                            <button type="button" class="text-action danger remove-lesson">Remove lesson</button>
                        </div>
                    </div>
                </div>
            </article>`;
    }

    function chapterMarkup(sectionKey) {
        const section = safeName(sectionKey);
        const lesson = generatedKey("lesson");

        return `
            <article class="chapter-card" data-section-key="${section}">
                <input type="hidden" name="section_keys[]" value="${section}">
                <header class="chapter-header">
                    <button type="button" class="drag-indicator" aria-label="Chapter position" tabindex="-1">⋮⋮</button>
                    <span class="chapter-number">01</span>
                    <div class="chapter-title-field">
                        <label>Chapter title</label>
                        <input name="section_title[${section}]" maxlength="160" placeholder="Example: Understanding the HTTP request lifecycle">
                    </div>
                    <div class="chapter-header-actions">
                        <button type="button" class="icon-action move-chapter-up" aria-label="Move chapter up">↑</button>
                        <button type="button" class="icon-action move-chapter-down" aria-label="Move chapter down">↓</button>
                        <button type="button" class="icon-action toggle-chapter" aria-label="Collapse chapter" aria-expanded="true">⌃</button>
                        <button type="button" class="icon-action danger remove-chapter" aria-label="Remove chapter">×</button>
                    </div>
                </header>
                <div class="chapter-body">
                    <div class="lessons-list">${lessonMarkup(section, lesson)}</div>
                    <button type="button" class="add-lesson-button add-lesson">+ Add another lesson to this chapter</button>
                </div>
            </article>`;
    }

    function updateLessonFields(lessonCard) {
        const typeSelect = lessonCard.querySelector(".lesson-type");
        const type = typeSelect?.value || "text";
        const textField = lessonCard.querySelector(".text-field");
        const urlField = lessonCard.querySelector(".url-field");
        const fileField = lessonCard.querySelector(".file-field");
        const fileInput = fileField?.querySelector('input[type="file"]');
        const urlInput = urlField?.querySelector('input[type="url"]');

        if (textField) textField.hidden = type !== "text";
        if (urlField) urlField.hidden = !["video", "link"].includes(type);
        if (fileField) fileField.hidden = !["pdf", "word"].includes(type);

        if (fileInput) {
            fileInput.accept = type === "pdf"
                ? "application/pdf,.pdf"
                : ".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document";
            fileInput.setCustomValidity("");
        }

        if (urlInput) {
            urlInput.placeholder = type === "video"
                ? "https://youtube.com/watch?v=... or another HTTPS video URL"
                : "https://example.com/learning-resource";
            urlInput.setCustomValidity("");
        }
    }

    function updatePositions() {
        const chapters = [...chapterList.querySelectorAll(":scope > .chapter-card")];

        chapters.forEach((chapter, chapterIndex) => {
            const number = chapter.querySelector(".chapter-number");
            if (number) number.textContent = String(chapterIndex + 1).padStart(2, "0");

            const lessons = [...chapter.querySelectorAll(".lessons-list > .lesson-card")];
            lessons.forEach((lesson, lessonIndex) => {
                const order = lesson.querySelector(".lesson-order");
                if (order) order.textContent = `L${lessonIndex + 1}`;
            });
        });
    }

    function updateEmptyState() {
        if (!emptyBuilder) return;
        emptyBuilder.hidden = Boolean(chapterList.querySelector(":scope > .chapter-card"));
    }

    function addChapter() {
        if (chapterList.children.length >= 50) {
            window.alert("A course can contain at most 50 chapters.");
            return;
        }

        chapterList.insertAdjacentHTML("beforeend", chapterMarkup(generatedKey("section")));
        const chapter = chapterList.lastElementChild;
        chapter.querySelectorAll(".lesson-card").forEach(updateLessonFields);
        updatePositions();
        updateEmptyState();
        updatePreviewAndQuality();
        chapter.querySelector(".chapter-title-field input")?.focus();
        chapter.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    function moveElement(item, direction, selector) {
        if (!item) return;
        const sibling = direction === "up" ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling || !sibling.matches(selector)) return;

        if (direction === "up") {
            item.parentNode.insertBefore(item, sibling);
        } else {
            item.parentNode.insertBefore(sibling, item);
        }

        updatePositions();
        updatePreviewAndQuality();
    }

    function toggleChapter(chapter, forceExpanded = null) {
        const body = chapter.querySelector(".chapter-body");
        const button = chapter.querySelector(".toggle-chapter");
        const currentlyCollapsed = chapter.classList.contains("is-collapsed");
        const shouldExpand = forceExpanded === null ? currentlyCollapsed : Boolean(forceExpanded);

        chapter.classList.toggle("is-collapsed", !shouldExpand);
        if (body) body.hidden = !shouldExpand;
        if (button) {
            button.setAttribute("aria-expanded", shouldExpand ? "true" : "false");
            button.textContent = shouldExpand ? "⌃" : "⌄";
        }
    }

    function getCurriculumData() {
        const chapters = [...chapterList.querySelectorAll(":scope > .chapter-card")];
        let lessonCount = 0;
        let totalMinutes = 0;
        let previewCount = 0;

        const outline = chapters.map((chapter, chapterIndex) => {
            const chapterTitle = cleanDisplayText(
                chapter.querySelector(".chapter-title-field input")?.value,
                `Chapter ${chapterIndex + 1}`
            );
            const lessons = [...chapter.querySelectorAll(".lessons-list > .lesson-card")].map((lesson, lessonIndex) => {
                lessonCount++;
                const duration = Math.max(0, Number.parseInt(lesson.querySelector('.duration-field input')?.value || "0", 10) || 0);
                totalMinutes += Math.min(duration, 1440);
                if (lesson.querySelector('.preview-toggle input')?.checked) previewCount++;

                return {
                    title: cleanDisplayText(
                        lesson.querySelector(".lesson-title-wrap input")?.value,
                        `Lesson ${lessonIndex + 1}`
                    ),
                    type: lesson.querySelector(".lesson-type")?.value || "text",
                    duration,
                };
            });

            return { title: chapterTitle, lessons };
        });

        return { chapters, outline, lessonCount, totalMinutes, previewCount };
    }

    function formatPrice(value) {
        const numeric = Number.parseFloat(value);
        if (!Number.isFinite(numeric) || numeric <= 0) return "Free";
        return `Rs. ${new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 }).format(numeric)}`;
    }

    function formatDuration(minutes) {
        if (minutes <= 0) return "0 min";
        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;
        if (hours && remainder) return `${hours}h ${remainder}m`;
        return hours ? `${hours}h` : `${remainder} min`;
    }

    function renderOutline(curriculum) {
        if (!preview.outline) return;
        preview.outline.replaceChildren();

        if (curriculum.outline.length === 0) {
            preview.outline.appendChild(element("p", "outline-empty", "Add chapters to build the course outline."));
            return;
        }

        curriculum.outline.slice(0, 6).forEach((chapter, index) => {
            const row = element("div", "outline-row");
            const badge = element("span", "outline-index", String(index + 1).padStart(2, "0"));
            const copy = element("div", "outline-copy");
            copy.appendChild(element("strong", "", chapter.title));
            copy.appendChild(element("small", "", `${chapter.lessons.length} lesson${chapter.lessons.length === 1 ? "" : "s"}`));
            row.append(badge, copy);
            preview.outline.appendChild(row);
        });

        if (curriculum.outline.length > 6) {
            preview.outline.appendChild(element("p", "outline-more", `+ ${curriculum.outline.length - 6} more chapters`));
        }
    }

    function hasExistingThumbnail() {
        return Boolean(previewImage && !previewImage.src.endsWith("course-placeholder.svg"));
    }

    function updateChecklist(checks) {
        Object.entries(checks).forEach(([key, passed]) => {
            const item = document.querySelector(`[data-check="${key}"]`);
            if (!item) return;
            item.classList.toggle("is-complete", passed);
            const icon = item.querySelector(":scope > span");
            if (icon) icon.textContent = passed ? "✓" : "○";
        });
    }

    function updatePreviewAndQuality() {
        const curriculum = getCurriculumData();
        const selectedCategory = fields.category?.selectedOptions?.[0];
        const categoryName = selectedCategory?.dataset.categoryName || "Uncategorized";
        const title = cleanDisplayText(fields.title?.value, "Your course title appears here");
        const description = cleanDisplayText(
            fields.shortDescription?.value,
            "Write a concise promise explaining what students will learn and why it matters."
        );

        if (preview.title) preview.title.textContent = title;
        if (preview.description) preview.description.textContent = description;
        if (preview.category) preview.category.textContent = categoryName;
        if (preview.price) preview.price.textContent = formatPrice(fields.price?.value || "0");
        if (preview.level) preview.level.textContent = cleanDisplayText(fields.level?.value, "Beginner").replace(/^./, c => c.toUpperCase());
        if (preview.language) preview.language.textContent = cleanDisplayText(fields.language?.value, "Language");
        if (preview.duration) preview.duration.textContent = formatDuration(curriculum.totalMinutes);
        if (preview.outlineCount) preview.outlineCount.textContent = `${curriculum.lessonCount} lesson${curriculum.lessonCount === 1 ? "" : "s"}`;
        if (preview.curriculumSummary) {
            preview.curriculumSummary.textContent = `${curriculum.chapters.length} chapter${curriculum.chapters.length === 1 ? "" : "s"} · ${curriculum.lessonCount} lesson${curriculum.lessonCount === 1 ? "" : "s"} · ${curriculum.totalMinutes} minutes`;
        }

        renderOutline(curriculum);

        const checks = {
            title: (fields.title?.value.trim().length || 0) >= 8,
            category: Number.parseInt(fields.category?.value || "0", 10) > 0,
            description: (fields.shortDescription?.value.trim().length || 0) >= 30
                && (fields.fullDescription?.value.trim().length || 0) >= 80,
            thumbnail: hasExistingThumbnail() || Boolean(thumbnailInput?.files?.length),
            curriculum: curriculum.chapters.length > 0 && curriculum.lessonCount > 0,
        };

        const complete = Object.values(checks).filter(Boolean).length;
        const score = Math.round((complete / Object.keys(checks).length) * 100);
        if (preview.qualityScore) preview.qualityScore.textContent = `${score}%`;
        if (preview.qualityBar) preview.qualityBar.style.width = `${score}%`;
        if (preview.qualityMessage) {
            preview.qualityMessage.textContent = score === 100
                ? "The course has the core information required for admin review."
                : `${Object.keys(checks).length - complete} quality item${Object.keys(checks).length - complete === 1 ? "" : "s"} still need attention.`;
        }
        if (preview.submitStateTitle) preview.submitStateTitle.textContent = score === 100 ? "Ready for admin review" : "Course still in production";
        if (preview.submitStateText) preview.submitStateText.textContent = score === 100
            ? "Review the live card once more, then submit it."
            : "You can save a private draft now and complete the remaining items later.";

        updateChecklist(checks);
        updatePositions();
        updateEmptyState();
    }

    function updateCounter(input) {
        if (!input?.id) return;
        const counter = document.querySelector(`[data-counter-for="${input.id}"]`);
        if (!counter) return;
        const max = Number.parseInt(input.getAttribute("maxlength") || "0", 10);
        counter.textContent = `${input.value.length} / ${max}`;
        counter.classList.toggle("is-near-limit", max > 0 && input.value.length >= Math.floor(max * 0.9));
    }

    function setFieldError(fieldName, message) {
        const target = document.querySelector(`[data-error-for="${fieldName}"]`);
        const input = document.getElementById(fieldName);
        if (target) target.textContent = message;
        input?.closest(".field-group")?.classList.toggle("has-error", Boolean(message));
    }

    function clearFieldErrors() {
        document.querySelectorAll(".field-error").forEach((node) => { node.textContent = ""; });
        document.querySelectorAll(".field-group.has-error").forEach((node) => node.classList.remove("has-error"));
        form.querySelectorAll(":invalid").forEach((node) => node.setCustomValidity?.(""));
    }

    function validateBaseFields(forReview) {
        clearFieldErrors();
        const errors = [];
        const title = fields.title?.value.trim() || "";
        const language = fields.language?.value.trim() || "";
        const price = Number.parseFloat(fields.price?.value || "");

        if (title.length < 1 || title.length > 180) {
            setFieldError("title", "Enter a course title no longer than 180 characters.");
            errors.push(fields.title);
        }

        if (language.length < 1 || language.length > 60) {
            setFieldError("language", "Enter a language no longer than 60 characters.");
            errors.push(fields.language);
        }

        if (!Number.isFinite(price) || price < 0) {
            setFieldError("price", "Enter a valid non-negative price.");
            errors.push(fields.price);
        }

        if (!forReview) return errors;

        if (title.length < 8) {
            setFieldError("title", "Use at least 8 characters so the course title is meaningful.");
            errors.push(fields.title);
        }

        if (Number.parseInt(fields.category?.value || "0", 10) < 1) {
            setFieldError("category_id", "Choose a course category.");
            errors.push(fields.category);
        }

        if ((fields.shortDescription?.value.trim().length || 0) < 30) {
            setFieldError("short_description", "Write at least 30 characters describing the course value.");
            errors.push(fields.shortDescription);
        }

        if ((fields.fullDescription?.value.trim().length || 0) < 80) {
            setFieldError("full_description", "Write at least 80 characters covering outcomes, audience and requirements.");
            errors.push(fields.fullDescription);
        }

        if (!hasExistingThumbnail() && !thumbnailInput?.files?.length) {
            setFieldError("thumbnail", "Choose a valid course thumbnail before submitting.");
            errors.push(thumbnailInput);
        }

        return errors;
    }

    function validateCurriculum(forReview) {
        if (!forReview) return [];
        const errors = [];
        const chapters = [...chapterList.querySelectorAll(":scope > .chapter-card")];

        if (chapters.length === 0) {
            errors.push(addChapterButton);
            return errors;
        }

        chapters.forEach((chapter) => {
            const chapterTitle = chapter.querySelector(".chapter-title-field input");
            const lessons = [...chapter.querySelectorAll(".lessons-list > .lesson-card")];

            if (!chapterTitle?.value.trim()) {
                chapterTitle?.classList.add("input-error");
                errors.push(chapterTitle);
            } else {
                chapterTitle.classList.remove("input-error");
            }

            if (lessons.length === 0) {
                errors.push(chapter.querySelector(".add-lesson"));
            }

            lessons.forEach((lesson) => {
                const title = lesson.querySelector(".lesson-title-wrap input");
                const type = lesson.querySelector(".lesson-type")?.value || "text";
                const text = lesson.querySelector(".text-field textarea");
                const url = lesson.querySelector(".url-field input");
                const file = lesson.querySelector(".file-field input[type='file']");
                const hasStoredFile = Boolean(lesson.querySelector(".resource-current"));

                if (!title?.value.trim()) {
                    title?.classList.add("input-error");
                    errors.push(title);
                } else {
                    title.classList.remove("input-error");
                }

                if (type === "text" && !text?.value.replace(/<[^>]*>/g, "").trim()) {
                    text?.classList.add("input-error");
                    errors.push(text);
                } else {
                    text?.classList.remove("input-error");
                }

                if (["video", "link"].includes(type)) {
                    let validUrl = false;
                    try {
                        const parsed = new URL(url?.value || "");
                        validUrl = ["http:", "https:"].includes(parsed.protocol) && !parsed.username && !parsed.password;
                    } catch (_) {
                        validUrl = false;
                    }

                    url?.classList.toggle("input-error", !validUrl);
                    if (!validUrl) errors.push(url);
                } else {
                    url?.classList.remove("input-error");
                }

                if (["pdf", "word"].includes(type) && !hasStoredFile && !file?.files?.length) {
                    file?.classList.add("input-error");
                    errors.push(file);
                } else {
                    file?.classList.remove("input-error");
                }
            });
        });

        return errors.filter(Boolean);
    }


    function validateThumbnailFile(file) {
        if (!file) return true;

        const allowedTypes = new Set(["image/jpeg", "image/png", "image/webp"]);
        const allowedExtensions = /\.(jpe?g|png|webp)$/i;

        if (!allowedTypes.has(file.type) || !allowedExtensions.test(file.name || "")) {
            thumbnailInput?.setCustomValidity("Choose a JPG, PNG or WebP image.");
            setFieldError("thumbnail", "Choose a genuine JPG, PNG or WebP image.");
            return false;
        }

        if (file.size < 1 || file.size > 2 * 1024 * 1024) {
            thumbnailInput?.setCustomValidity("The thumbnail must be 2 MB or smaller.");
            setFieldError("thumbnail", "The thumbnail must be a non-empty image no larger than 2 MB.");
            return false;
        }

        thumbnailInput?.setCustomValidity("");
        setFieldError("thumbnail", "");
        return true;
    }

    function setPreviewImageSource(source, fileName = "") {
        if (!previewImage) return;

        previewImage.classList.remove("is-loaded", "is-error");
        previewImage.removeAttribute("data-fallback-applied");

        const handleLoad = () => {
            previewImage.classList.add("is-loaded");
            previewImage.classList.remove("is-error");
        };

        const handleError = () => {
            previewImage.classList.remove("is-loaded");
            previewImage.classList.add("is-error");

            if (previewImage.dataset.fallbackApplied !== "1") {
                previewImage.dataset.fallbackApplied = "1";
                previewImage.src = originalThumbnailSource;
            }
        };

        previewImage.onload = handleLoad;
        previewImage.onerror = handleError;
        previewImage.src = source;

        const strong = thumbnailDropzone?.querySelector(".dropzone-copy strong");
        const small = thumbnailDropzone?.querySelector(".dropzone-copy small");

        if (fileName && strong) {
            strong.textContent = fileName;
        }

        if (fileName && small) {
            small.textContent = "Image selected · preview updated · server verification happens on save";
        }
    }

    async function readThumbnailFile(file) {
        const dataUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ""));
            reader.onerror = () => reject(new Error("The browser could not read this image."));
            reader.readAsDataURL(file);
        });

        const probe = new Image();
        probe.decoding = "async";

        await new Promise((resolve, reject) => {
            probe.onload = resolve;
            probe.onerror = () => reject(new Error("The selected file is not a readable image."));
            probe.src = dataUrl;
        });

        if (probe.naturalWidth < 320 || probe.naturalHeight < 180) {
            throw new Error("Use an image at least 320 × 180 pixels.");
        }

        if (probe.naturalWidth > 8000 || probe.naturalHeight > 8000) {
            throw new Error("The image dimensions are too large.");
        }

        return dataUrl;
    }

    async function updateThumbnailPreview() {
        const file = thumbnailInput?.files?.[0];

        if (!file) {
            setPreviewImageSource(originalThumbnailSource);
            thumbnailDropzone?.classList.remove("has-file", "is-processing");
            updatePreviewAndQuality();
            return;
        }

        if (!validateThumbnailFile(file)) {
            if (thumbnailInput) thumbnailInput.value = "";
            setPreviewImageSource(originalThumbnailSource);
            thumbnailDropzone?.classList.remove("has-file", "is-processing");
            updatePreviewAndQuality();
            return;
        }

        thumbnailDropzone?.classList.add("is-processing");

        try {
            const dataUrl = await readThumbnailFile(file);
            setPreviewImageSource(dataUrl, file.name);
            thumbnailDropzone?.classList.add("has-file");
            setFieldError("thumbnail", "");
        } catch (error) {
            if (thumbnailInput) thumbnailInput.value = "";
            thumbnailDropzone?.classList.remove("has-file");
            setPreviewImageSource(originalThumbnailSource);
            setFieldError("thumbnail", error instanceof Error ? error.message : "The image could not be previewed.");
        } finally {
            thumbnailDropzone?.classList.remove("is-processing");
            updatePreviewAndQuality();
        }
    }


    [fields.title, fields.category, fields.price, fields.level, fields.language, fields.shortDescription, fields.fullDescription]
        .filter(Boolean)
        .forEach((input) => {
            input.addEventListener("input", () => {
                updateCounter(input);
                updatePreviewAndQuality();
            });
            input.addEventListener("change", updatePreviewAndQuality);
            updateCounter(input);
        });

    thumbnailInput?.addEventListener("change", updateThumbnailPreview);

    if (previewImage) {
        previewImage.addEventListener("load", () => previewImage.classList.add("is-loaded"));
        previewImage.addEventListener("error", () => {
            previewImage.classList.add("is-error");
            if (previewImage.dataset.fallbackApplied !== "1") {
                previewImage.dataset.fallbackApplied = "1";
                previewImage.src = originalThumbnailSource;
            }
        });

        if (previewImage.complete && previewImage.naturalWidth > 0) {
            previewImage.classList.add("is-loaded");
        }
    }


    ["dragenter", "dragover"].forEach((eventName) => {
        thumbnailDropzone?.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            thumbnailDropzone.classList.add("is-dragging");
        });
    });

    ["dragleave", "dragend"].forEach((eventName) => {
        thumbnailDropzone?.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            thumbnailDropzone.classList.remove("is-dragging");
        });
    });

    thumbnailDropzone?.addEventListener("drop", (event) => {
        event.preventDefault();
        event.stopPropagation();
        thumbnailDropzone.classList.remove("is-dragging");

        const files = event.dataTransfer?.files;
        if (!files?.length || !thumbnailInput) return;

        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        thumbnailInput.files = transfer.files;
        void updateThumbnailPreview();
    });

    addChapterButton?.addEventListener("click", addChapter);
    emptyAddChapterButton?.addEventListener("click", addChapter);

    expandAllButton?.addEventListener("click", () => {
        const chapters = [...chapterList.querySelectorAll(":scope > .chapter-card")];
        const allExpanded = chapters.every((chapter) => !chapter.classList.contains("is-collapsed"));
        chapters.forEach((chapter) => toggleChapter(chapter, !allExpanded));
        expandAllButton.textContent = allExpanded ? "Expand all" : "Collapse all";
    });

    chapterList.addEventListener("click", (event) => {
        const target = event.target;
        const chapter = target.closest(".chapter-card");
        const lesson = target.closest(".lesson-card");

        if (target.closest(".add-lesson")) {
            const lessonsList = chapter.querySelector(".lessons-list");
            if (lessonsList.children.length >= 100) {
                window.alert("A chapter can contain at most 100 lessons.");
                return;
            }
            const sectionKey = chapter.dataset.sectionKey;
            lessonsList.insertAdjacentHTML("beforeend", lessonMarkup(sectionKey, generatedKey("lesson")));
            const addedLesson = lessonsList.lastElementChild;
            updateLessonFields(addedLesson);
            updatePositions();
            updatePreviewAndQuality();
            addedLesson.querySelector(".lesson-title-wrap input")?.focus();
            return;
        }

        if (target.closest(".remove-lesson")) {
            if (window.confirm("Remove this lesson from the course draft?")) {
                lesson.remove();
                updatePreviewAndQuality();
            }
            return;
        }

        if (target.closest(".remove-chapter")) {
            if (window.confirm("Remove this chapter and every lesson inside it?")) {
                chapter.remove();
                updatePreviewAndQuality();
            }
            return;
        }

        if (target.closest(".move-chapter-up")) return moveElement(chapter, "up", ".chapter-card");
        if (target.closest(".move-chapter-down")) return moveElement(chapter, "down", ".chapter-card");
        if (target.closest(".move-lesson-up")) return moveElement(lesson, "up", ".lesson-card");
        if (target.closest(".move-lesson-down")) return moveElement(lesson, "down", ".lesson-card");
        if (target.closest(".toggle-chapter")) return toggleChapter(chapter);
    });

    chapterList.addEventListener("input", updatePreviewAndQuality);
    chapterList.addEventListener("change", (event) => {
        if (event.target.classList.contains("lesson-type")) {
            updateLessonFields(event.target.closest(".lesson-card"));
        }
        updatePreviewAndQuality();
    });

    chapterList.querySelectorAll(".lesson-card").forEach(updateLessonFields);

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (!visible) return;
        document.querySelectorAll("[data-step-link]").forEach((link) => {
            link.classList.toggle("is-active", link.dataset.stepLink === visible.target.dataset.studioSection);
        });
    }, { rootMargin: "-25% 0px -60%", threshold: [0.05, 0.35] });

    document.querySelectorAll("[data-studio-section]").forEach((section) => observer.observe(section));

    form.addEventListener("submit", (event) => {
        const submitter = event.submitter;
        const isReview = submitter?.value === "submit_review";
        const baseErrors = validateBaseFields(isReview);
        const curriculumErrors = validateCurriculum(isReview);
        const errors = [...baseErrors, ...curriculumErrors].filter(Boolean);

        if (errors.length > 0) {
            event.preventDefault();
            const first = errors[0];
            first.closest(".chapter-card")?.classList.remove("is-collapsed");
            const chapterBody = first.closest(".chapter-card")?.querySelector(".chapter-body");
            if (chapterBody) chapterBody.hidden = false;
            first.scrollIntoView({ behavior: "smooth", block: "center" });
            first.focus?.({ preventScroll: true });
            if (preview.submitStateTitle) preview.submitStateTitle.textContent = "Review the highlighted fields";
            if (preview.submitStateText) preview.submitStateText.textContent = `${errors.length} item${errors.length === 1 ? "" : "s"} need attention before this action.`;
            return;
        }

        if (isReview) {
            const confirmed = window.confirm(
                publishedEdit
                    ? "Submit these curriculum changes to admin for approval?"
                    : "Submit this course to admin? You cannot edit it while it is pending review."
            );
            if (!confirmed) event.preventDefault();
        }
    });

    updatePositions();
    updateEmptyState();
    updatePreviewAndQuality();
});
