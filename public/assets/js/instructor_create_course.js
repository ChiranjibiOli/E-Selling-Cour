document.addEventListener("DOMContentLoaded", function () {
    const courseTitle = document.getElementById("courseTitle");
    const coursePrice = document.getElementById("coursePrice");
    const courseThumbnail = document.getElementById("courseThumbnail");
    const courseShortDescription = document.getElementById("courseShortDescription");
    const courseLevel = document.getElementById("courseLevel");
    const courseLanguage = document.getElementById("courseLanguage");
    const courseStatus = document.getElementById("courseStatus");

    const previewTitle = document.getElementById("previewTitle");
    const previewPrice = document.getElementById("previewPrice");
    const previewImage = document.getElementById("previewImage");
    const previewShortDescription = document.getElementById("previewShortDescription");
    const previewLevel = document.getElementById("previewLevel");
    const previewLanguage = document.getElementById("previewLanguage");
    const previewStatus = document.getElementById("previewStatus");

    const addPageBtn = document.getElementById("addPageBtn");
    const addPdfBtn = document.getElementById("addPdfBtn");
    const addWordBtn = document.getElementById("addWordBtn");
    const contentBlocks = document.getElementById("contentBlocks");
    const emptyContentBox = document.getElementById("emptyContentBox");

    const outlinePreview = document.getElementById("outlinePreview");
    const realContentPreview = document.getElementById("realContentPreview");
    const contentPayload = document.getElementById("contentPayload");
    const prepareCourseBtn = document.getElementById("prepareCourseBtn");

    let blockCounter = 0;

    function cleanText(value, fallback) {
        const text = String(value || "").trim();
        return text === "" ? fallback : text;
    }
function labelText(value) {
    if (value === "beginner") return "Beginner";
    if (value === "intermediate") return "Intermediate";
    if (value === "advanced") return "Advanced";
    if (value === "draft") return "Draft";
    if (value === "pending") return "Pending Approval";

    return value;
}
    function updateCourseCardPreview() {
        previewTitle.textContent = cleanText(courseTitle.value, "Course title will appear here");

        previewShortDescription.textContent = cleanText(
            courseShortDescription.value,
            "Short description will appear here."
        );

        const priceValue = String(coursePrice.value || "").trim();
        previewPrice.textContent = priceValue === "" ? "Rs. 0" : "Rs. " + priceValue;

        previewLanguage.textContent = labelText(courseLanguage.value);
     previewLevel.textContent = labelText(cleanText(courseLevel.value, "beginner"));
previewStatus.textContent = labelText(cleanText(courseStatus.value, "draft"));
    }

    function updateThumbnailPreview() {
        const file = courseThumbnail.files[0];

        if (!file) {
            previewImage.innerHTML = "";
            const span = document.createElement("span");
            span.textContent = "No Thumbnail";
            previewImage.appendChild(span);
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            previewImage.innerHTML = "";

            const image = document.createElement("img");
            image.src = event.target.result;
            image.alt = "Course thumbnail preview";

            previewImage.appendChild(image);
        };

        reader.readAsDataURL(file);
    }

    function applyCommand(editor, command, value = null) {
        editor.focus();

        if (command === "formatBlock") {
            document.execCommand(command, false, value);
        } else if (command === "foreColor") {
            document.execCommand(command, false, value);
        } else {
            document.execCommand(command, false, null);
        }

        updateContentPreviews();
    }

    function buildToolbar(editor) {
        const toolbar = document.createElement("div");
        toolbar.className = "editor-toolbar";

        const tools = [
            { label: "H1", command: "formatBlock", value: "h1" },
            { label: "H2", command: "formatBlock", value: "h2" },
            { label: "H3", command: "formatBlock", value: "h3" },
            { label: "H4", command: "formatBlock", value: "h4" },
            { label: "H5", command: "formatBlock", value: "h5" },
            { label: "P", command: "formatBlock", value: "p" },
            { label: "Bold", command: "bold" },
            { label: "Italic", command: "italic" },
            { label: "Bullet", command: "insertUnorderedList" },
            { label: "Number", command: "insertOrderedList" }
        ];

        tools.forEach(function (tool) {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = tool.label;

            button.addEventListener("click", function () {
                applyCommand(editor, tool.command, tool.value || null);
            });

            toolbar.appendChild(button);
        });

        const colorInput = document.createElement("input");
        colorInput.type = "color";
        colorInput.title = "Text Color";

        colorInput.addEventListener("input", function () {
            applyCommand(editor, "foreColor", colorInput.value);
        });

        toolbar.appendChild(colorInput);

        return toolbar;
    }

    function showOrHideEmptyBox() {
        const blocks = contentBlocks.querySelectorAll(".content-block");

        if (emptyContentBox) {
            emptyContentBox.style.display = blocks.length === 0 ? "block" : "none";
        }
    }

    function createHiddenInput(name, value = "") {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = value;
        return input;
    }

    function addTextPage() {
        blockCounter++;

        const key = "content_" + blockCounter;

        const block = document.createElement("div");
        block.className = "content-block";
        block.dataset.key = key;
        block.dataset.type = "text";

        block.appendChild(createHiddenInput("content_keys[]", key));
        block.appendChild(createHiddenInput("lesson_type[" + key + "]", "text"));

        const hiddenContent = createHiddenInput("lesson_content[" + key + "]", "");
        hiddenContent.className = "lesson-content-hidden";
        block.appendChild(hiddenContent);

        const header = document.createElement("div");
        header.className = "content-block-header";

        const badge = document.createElement("div");
        badge.className = "content-type-badge";
        badge.textContent = "Blank Editor Page";

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "remove-content-btn";
        removeBtn.textContent = "Remove";

        header.appendChild(badge);
        header.appendChild(removeBtn);

        const titleGroup = document.createElement("div");
        titleGroup.className = "form-group";

        const titleLabel = document.createElement("label");
        titleLabel.textContent = "Page Title";

        const titleInput = document.createElement("input");
        titleInput.type = "text";
        titleInput.name = "lesson_title[" + key + "]";
        titleInput.className = "content-title";
        titleInput.placeholder = "Example: What is Networking?";

        titleGroup.appendChild(titleLabel);
        titleGroup.appendChild(titleInput);

        const editor = document.createElement("div");
        editor.className = "lecture-editor";
        editor.contentEditable = "true";
        editor.innerHTML = "<h1>New Lecture Page</h1><p>Start writing your lecture content here...</p>";

        const toolbar = buildToolbar(editor);

        block.appendChild(header);
        block.appendChild(titleGroup);
        block.appendChild(toolbar);
        block.appendChild(editor);

        titleInput.addEventListener("input", updateContentPreviews);

        editor.addEventListener("input", function () {
            hiddenContent.value = editor.innerHTML;
            updateContentPreviews();
        });

        editor.addEventListener("keyup", function () {
            hiddenContent.value = editor.innerHTML;
            updateContentPreviews();
        });

        editor.addEventListener("mouseup", function () {
            hiddenContent.value = editor.innerHTML;
            updateContentPreviews();
        });

        removeBtn.addEventListener("click", function () {
            block.remove();
            showOrHideEmptyBox();
            updateContentPreviews();
        });

        contentBlocks.appendChild(block);

        hiddenContent.value = editor.innerHTML;

        showOrHideEmptyBox();
        updateContentPreviews();
    }

    function addFileResource(type) {
        blockCounter++;

        const key = "content_" + blockCounter;
        const isPdf = type === "pdf";

        const block = document.createElement("div");
        block.className = "content-block";
        block.dataset.key = key;
        block.dataset.type = type;

        block.appendChild(createHiddenInput("content_keys[]", key));
        block.appendChild(createHiddenInput("lesson_type[" + key + "]", type));
        block.appendChild(createHiddenInput("lesson_content[" + key + "]", ""));

        const header = document.createElement("div");
        header.className = "content-block-header";

        const badge = document.createElement("div");
        badge.className = "content-type-badge";
        badge.textContent = isPdf ? "PDF Resource" : "Word Resource";

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "remove-content-btn";
        removeBtn.textContent = "Remove";

        header.appendChild(badge);
        header.appendChild(removeBtn);

        const titleGroup = document.createElement("div");
        titleGroup.className = "form-group";

        const titleLabel = document.createElement("label");
        titleLabel.textContent = "Resource Title";

        const titleInput = document.createElement("input");
        titleInput.type = "text";
        titleInput.name = "lesson_title[" + key + "]";
        titleInput.className = "content-title";
        titleInput.placeholder = isPdf ? "Example: Networking PDF Notes" : "Example: IP Addressing Word Notes";

        titleGroup.appendChild(titleLabel);
        titleGroup.appendChild(titleInput);

        const fileBox = document.createElement("div");
        fileBox.className = "file-resource-box";

        const fileLabel = document.createElement("label");
        fileLabel.textContent = isPdf ? "Upload PDF File" : "Upload Word File";

        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.name = "lesson_file_" + key;
        fileInput.className = "resource-file";

        if (isPdf) {
            fileInput.accept = "application/pdf";
        } else {
            fileInput.accept = ".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document";
        }

        fileBox.appendChild(fileLabel);
        fileBox.appendChild(fileInput);

        block.appendChild(header);
        block.appendChild(titleGroup);
        block.appendChild(fileBox);

        titleInput.addEventListener("input", updateContentPreviews);
        fileInput.addEventListener("change", updateContentPreviews);

        removeBtn.addEventListener("click", function () {
            block.remove();
            showOrHideEmptyBox();
            updateContentPreviews();
        });

        contentBlocks.appendChild(block);

        showOrHideEmptyBox();
        updateContentPreviews();
    }

    function updateContentPreviews() {
        const blocks = contentBlocks.querySelectorAll(".content-block");

        outlinePreview.innerHTML = "";
        realContentPreview.innerHTML = "";

        if (blocks.length === 0) {
            const emptyOutline = document.createElement("li");
            emptyOutline.textContent = "No content added yet.";
            outlinePreview.appendChild(emptyOutline);

            const emptyReal = document.createElement("p");
            emptyReal.className = "empty-preview-text";
            emptyReal.textContent = "Add a blank editor page to preview real lecture content.";
            realContentPreview.appendChild(emptyReal);

            contentPayload.value = "[]";
            return;
        }

        const payload = [];

        blocks.forEach(function (block, index) {
            const type = block.dataset.type;
            const key = block.dataset.key;

            const titleInput = block.querySelector(".content-title");
            const title = cleanText(titleInput ? titleInput.value : "", "Untitled Content");

            const outlineItem = document.createElement("li");
            const outlineText = document.createTextNode(title + " ");

            const smallType = document.createElement("small");
            smallType.textContent = "(" + type.toUpperCase() + ")";

            outlineItem.appendChild(outlineText);
            outlineItem.appendChild(smallType);

            outlinePreview.appendChild(outlineItem);

            if (type === "text") {
                const editor = block.querySelector(".lecture-editor");
                const hiddenContent = block.querySelector(".lesson-content-hidden");
                const htmlContent = editor ? editor.innerHTML : "";

                if (hiddenContent) {
                    hiddenContent.value = htmlContent;
                }

                const pagePreview = document.createElement("div");
                pagePreview.className = "content-preview-page";
                pagePreview.innerHTML = htmlContent;

                realContentPreview.appendChild(pagePreview);

                payload.push({
                    key: key,
                    type: "text",
                    title: title,
                    content_html: htmlContent,
                    order: index + 1
                });
            } else {
                const fileInput = block.querySelector(".resource-file");
                const fileName = fileInput && fileInput.files[0]
                    ? fileInput.files[0].name
                    : "No file selected";

                const resourceCard = document.createElement("div");
                resourceCard.className = "resource-preview-card";

                const resourceTitle = document.createElement("h3");
                resourceTitle.textContent = title;

                const resourceInfo = document.createElement("p");
                resourceInfo.textContent = type.toUpperCase() + " Resource: " + fileName;

                resourceCard.appendChild(resourceTitle);
                resourceCard.appendChild(resourceInfo);

                realContentPreview.appendChild(resourceCard);

                payload.push({
                    key: key,
                    type: type,
                    title: title,
                    file_name_preview: fileName,
                    order: index + 1
                });
            }
        });

        contentPayload.value = JSON.stringify(payload);
    }

    courseTitle.addEventListener("input", updateCourseCardPreview);
    coursePrice.addEventListener("input", updateCourseCardPreview);
    courseShortDescription.addEventListener("input", updateCourseCardPreview);
    courseLevel.addEventListener("change", updateCourseCardPreview);
    courseLanguage.addEventListener("input", updateCourseCardPreview);
    courseStatus.addEventListener("change", updateCourseCardPreview);

    courseThumbnail.addEventListener("change", function () {
        updateThumbnailPreview();
    });

    addPageBtn.addEventListener("click", function () {
        addTextPage();
    });

    addPdfBtn.addEventListener("click", function () {
        addFileResource("pdf");
    });

    addWordBtn.addEventListener("click", function () {
        addFileResource("word");
    });

   const courseStudioForm = document.getElementById("courseStudioForm");
const confirmOverlay = document.getElementById("confirmOverlay");
const cancelConfirmBtn = document.getElementById("cancelConfirmBtn");
const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

prepareCourseBtn.addEventListener("click", function () {
    updateCourseCardPreview();
    updateContentPreviews();

    confirmOverlay.classList.add("show");
});

cancelConfirmBtn.addEventListener("click", function () {
    confirmOverlay.classList.remove("show");
});

confirmOverlay.addEventListener("click", function (event) {
    if (event.target === confirmOverlay) {
        confirmOverlay.classList.remove("show");
    }
});

confirmSubmitBtn.addEventListener("click", function () {
    updateCourseCardPreview();
    updateContentPreviews();

    let saveInput = document.createElement("input");
    saveInput.type = "hidden";
    saveInput.name = "save_course";
    saveInput.value = "1";

    courseStudioForm.appendChild(saveInput);
    courseStudioForm.submit();
});

    updateCourseCardPreview();
    updateThumbnailPreview();
    updateContentPreviews();
});