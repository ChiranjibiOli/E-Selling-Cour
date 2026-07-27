<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class CreateCoursePage
{
    public static function render(
        array $categories,
        array $authoring = [],
        string $message = '',
        bool $success = true,
        string $instructorName = 'CourseHub instructor',
    ): Response {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $course = is_array($authoring['course'] ?? null) ? $authoring['course'] : [];
        $sections = is_array($authoring['sections'] ?? null) ? array_values($authoring['sections']) : [];
        $meta = is_array($authoring['meta'] ?? null) ? $authoring['meta'] : [];
        $courseId = (int) ($meta['id'] ?? 0);
        $status = (string) ($meta['status'] ?? ($courseId > 0 ? 'draft' : 'new'));
        $permission = (string) ($meta['edit_permission_status'] ?? 'none');
        $revisionStatus = (string) ($meta['revision_status'] ?? '');
        $locked = $status === 'pending'
            || $revisionStatus === 'pending'
            || ($status === 'published' && $permission !== 'granted' && $revisionStatus !== 'draft');
        $listValue = static fn (mixed $value): string => is_array($value) ? implode("\n", array_map('strval', $value)) : (string) $value;
        $duration = trim((string) ($course['duration'] ?? ''));
        $durationHours = preg_match('/([0-9]+(?:\.[0-9]+)?)/', $duration, $durationMatch) === 1 ? $durationMatch[1] : '';
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';

        $selectedCategoryName = 'Choose a category';
        $categoryOptions = '<option value="">Choose category</option>';
        foreach ($categories as $category) {
            $selected = (string) ($course['category_id'] ?? '') === (string) ($category['id'] ?? '') ? ' selected' : '';
            if ($selected !== '') {
                $selectedCategoryName = (string) ($category['name'] ?? 'Course');
            }
            $categoryOptions .= '<option value="' . (int) ($category['id'] ?? 0) . '"' . $selected . '>' . $e($category['name'] ?? '') . '</option>';
        }
        $levelOptions = '';
        foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label) {
            $levelOptions .= '<option value="' . $value . '"' . (($course['level'] ?? 'beginner') === $value ? ' selected' : '') . '>' . $label . '</option>';
        }

        if ($sections === [] && !$locked) {
            $sections = [[
                'title' => '',
                'lessons' => [[
                    'title' => '', 'content_type' => 'text', 'content_url' => '', 'content_name' => '',
                    'content_text' => '', 'duration_minutes' => 0, 'is_preview' => false,
                ]],
            ]];
        }
        $sectionHtml = '';
        foreach ($sections as $sectionIndex => $section) {
            $sectionHtml .= self::sectionEditor($section, $sectionIndex, $e);
        }

        $thumbnailPath = trim((string) ($course['thumbnail'] ?? ''));
        $thumbnailUrl = $thumbnailPath !== '' ? '/media/course-thumbnails/' . rawurlencode(basename($thumbnailPath)) : '';
        $title = trim((string) ($course['title'] ?? ''));
        $description = trim((string) ($course['short_description'] ?? ''));
        $previewPrice = trim((string) ($course['discount_price'] ?? '')) !== '' ? (string) $course['discount_price'] : (string) ($course['price'] ?? '0');
        $previewPriceText = is_numeric($previewPrice) && (float) $previewPrice > 0 ? 'NPR ' . number_format((float) $previewPrice, 0) : 'Free';
        $statusMessage = match (true) {
            $status === 'pending' => 'This complete course is locked while Admin reviews it.',
            $revisionStatus === 'pending' => 'The live course remains published while Admin reviews this revision.',
            $status === 'published' && $permission === 'requested' => 'Edit permission is waiting for Admin approval.',
            $status === 'published' && $permission === 'denied' => 'Admin denied the current edit request. Request permission again from My courses with a clearer reason.',
            $status === 'published' && $permission !== 'granted' && $revisionStatus !== 'draft' => 'Published courses are locked. Request edit permission from My courses before changing the approved content.',
            default => '',
        };

        if ($locked) {
            $content = $alert
                . '<section class="course-authoring-locked"><span class="status-badge ' . $e($revisionStatus === 'pending' ? 'pending' : $status) . '">' . $e($revisionStatus === 'pending' ? 'revision pending' : $status) . '</span><h2>' . $e($title !== '' ? $title : 'Course authoring') . '</h2><p>' . $e($statusMessage) . '</p><div><a class="portal-button" href="/instructor/courses">Open My courses</a>'
                . ($status === 'published' ? '<a class="portal-button secondary" href="/course?id=' . $courseId . '">View live course</a>' : '') . '</div></section>';
            return PortalPage::render('instructor', 'Course authoring', $content);
        }

        $form = $alert
            . '<div class="complete-authoring-layout" data-complete-authoring>'
            . '<form class="complete-authoring-form" method="post" action="/instructor/courses/create' . ($courseId > 0 ? '?course=' . $courseId : '') . '" enctype="multipart/form-data" novalidate data-course-form>'
            . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><input type="hidden" name="existing_thumbnail" value="' . $e($thumbnailPath) . '"><input type="hidden" name="curriculum_json" value="[]" data-curriculum-json>'
            . '<section class="authoring-sheet"><header class="authoring-sheet-head"><div><span>COMPLETE COURSE AUTHORING</span><h2>' . ($courseId > 0 ? 'Edit the entire course' : 'Create the entire course') . '</h2><p>Course details, learning promise, sections and every lesson live on this one page.</p></div><strong>' . ($courseId > 0 ? '#' . $courseId : 'NEW') . '</strong></header>'
            . '<div class="authoring-block"><div class="authoring-block-title"><span>01</span><div><h3>Course identity</h3><p>These details position the course in the public catalogue.</p></div></div>'
            . self::label('Course title', 'The main public name of the course. Write exactly what the Student will learn.', '<input type="text" name="title" minlength="3" maxlength="180" value="' . $e($course['title'] ?? '') . '" placeholder="Complete Web Application Security" autocomplete="off" required data-preview-source="title" data-error="Write a clear course title between 3 and 180 characters.">')
            . self::label('Course subtitle', 'A one-line promise that explains the result or transformation offered by the course.', '<input type="text" name="subtitle" maxlength="240" value="' . $e($course['subtitle'] ?? '') . '" placeholder="Learn practical testing from request mapping to verified findings" autocomplete="off" data-error="Write a short learning promise for the course subtitle.">')
            . self::label('Short description', 'This summary appears on the course card. Explain the benefit in a few readable sentences.', '<textarea name="short_description" minlength="20" maxlength="500" rows="4" required data-preview-source="description" data-error="Write 20 to 500 characters explaining the main benefit shown on the course card.">' . $e($course['short_description'] ?? '') . '</textarea>')
            . self::label('Full course description', 'Describe the complete scope, teaching approach and what the Student receives after enrolling.', '<textarea name="full_description" minlength="50" maxlength="50000" rows="10" required data-error="Write at least 50 characters describing the complete course.">' . $e($course['full_description'] ?? '') . '</textarea>') . '</div>'
            . '<div class="authoring-block"><div class="authoring-block-title"><span>02</span><div><h3>Learning promise</h3><p>These fields help Students decide whether the course matches their goal and current knowledge.</p></div></div>'
            . '<div class="form-columns">'
            . self::label('Learning outcomes', 'Write what a Student will be able to do after finishing. Use one measurable result per line, such as “Map an HTTP request flow”.', '<textarea name="learning_outcomes" rows="7" maxlength="9030" placeholder="One result per line" data-error="Write at least one result the Student should achieve after completing the course.">' . $e($listValue($course['learning_outcomes'] ?? '')) . '</textarea>')
            . self::label('Course requirements', 'List knowledge, software, equipment or experience needed before starting. Write “No prior experience required” when the course truly has none.', '<textarea name="requirements" rows="7" maxlength="9030" placeholder="One requirement per line" data-error="Explain what the Student needs before starting, or state that no prior experience is required.">' . $e($listValue($course['requirements'] ?? '')) . '</textarea>') . '</div>'
            . self::label('Target audience', 'Name the specific people who will benefit, such as beginners, junior developers or business owners. Use one audience group per line.', '<textarea name="target_audience" rows="5" maxlength="9030" placeholder="One audience group per line" data-error="Name at least one type of Student this course is designed for.">' . $e($listValue($course['target_audience'] ?? '')) . '</textarea>')
            . self::label('Search tags', 'Add short comma-separated words that help the catalogue search understand the topic. Do not repeat the whole title.', '<input type="text" name="tags" maxlength="500" value="' . $e($course['tags'] ?? '') . '" placeholder="security, HTTP, beginner, Burp Suite" autocomplete="off">') . '</div>'
            . '<div class="authoring-block"><div class="authoring-block-title"><span>03</span><div><h3>Delivery, price and media</h3><p>Configure how the course is presented and sold.</p></div></div>'
            . '<div class="form-columns">' . self::label('Category', 'Choose the closest catalogue subject. Admin manages the available category list.', '<select name="category_id" required data-preview-source="category" data-error="Choose the catalogue category that best matches this course.">' . $categoryOptions . '</select>')
            . self::label('Level', 'Choose the assumed learner experience. Beginner means no or little prior knowledge; advanced assumes strong foundations.', '<select name="level" data-preview-source="level">' . $levelOptions . '</select>') . '</div>'
            . '<div class="form-columns">' . self::label('Standard price (NPR)', 'The normal course price. Enter 0 only when the course should be free.', '<input type="number" inputmode="decimal" name="price" min="0" max="10000000" step="0.01" value="' . $e($course['price'] ?? '0') . '" required data-preview-source="price" data-error="Enter the normal course price as a number, or 0 for a free course.">')
            . self::label('Discount price (NPR)', 'Optional temporary selling price. It must be lower than the standard price.', '<input type="number" inputmode="decimal" name="discount_price" min="0" max="10000000" step="0.01" value="' . $e($course['discount_price'] ?? '') . '" data-preview-source="discount" placeholder="Optional" data-error="Enter a discount lower than the standard price, or leave it empty.">') . '</div>'
            . '<div class="form-columns">' . self::label('Language', 'The main spoken or written language used throughout the lessons.', '<input type="text" name="language" minlength="2" maxlength="60" value="' . $e($course['language'] ?? 'English') . '" required data-preview-source="language" data-error="Write the main language used in the course.">')
            . self::label('Estimated duration in hours', 'Approximate total learning time, including video, reading and practice. This is not a deadline.', '<input type="number" inputmode="decimal" name="duration_hours" min="0.25" max="10000" step="0.25" value="' . $e($durationHours) . '" placeholder="12" data-error="Enter the approximate total course time in hours.">') . '</div>'
            . self::label('Introduction video URL', 'Optional public HTTPS video that explains the course before purchase. Do not use a private lesson file here.', '<input type="url" inputmode="url" name="intro_video_url" maxlength="500" value="' . $e($course['intro_video_url'] ?? '') . '" placeholder="https://..." data-error="Enter a normal HTTPS introduction-video address or leave it empty.">')
            . self::label('Course thumbnail', 'Upload the public 16:10 course image shown in the catalogue card. Use JPG, PNG or WebP, at least 640 × 360 pixels.', '<input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" data-preview-source="thumbnail" data-error="Choose a valid landscape JPG, PNG or WebP course image.">' . ($thumbnailPath !== '' ? '<small>Current image is kept until another image is selected.</small>' : '')) . '</div>'
            . '<div class="authoring-block curriculum-authoring-block" id="curriculum"><div class="authoring-block-title"><span>04</span><div><h3>Sections and lessons</h3><p>Numbers are assigned automatically from top to bottom. Add the actual learning content for every lesson type.</p></div></div>'
            . '<div class="curriculum-section-list" data-section-list>' . $sectionHtml . '</div><button class="portal-button secondary" type="button" data-add-section>+ Add next section</button></div>'
            . '<footer class="complete-authoring-actions"><div><strong>Finish course authoring</strong><span>Save keeps the course private. Submit sends the complete snapshot to Admin review.</span></div><button class="portal-button secondary" type="submit" name="action" value="draft">Save private draft</button><button class="portal-button" type="submit" name="action" value="submit">Submit complete course</button></footer></section></form>'
            . '<aside class="course-design-preview" aria-label="Live public course card preview"><header><span>LIVE CARD PREVIEW</span><strong>The final course-card design</strong><p>This preview follows the warm paper-and-gold card design supplied for CourseHub.</p></header>'
            . '<article class="provided-course-card"><div class="provided-course-thumb" data-preview-media>'
            . ($thumbnailUrl !== '' ? '<img src="' . $e($thumbnailUrl) . '" alt="Selected course thumbnail">' : '<div><span>COURSEHUB</span><strong>COURSE IMAGE</strong></div>')
            . '<b>COURSE</b></div><div class="provided-course-body"><span data-preview-category>' . $e($selectedCategoryName) . '</span><h3 data-preview-title>' . $e($title !== '' ? $title : 'Your course title will appear here') . '</h3><p data-preview-description>' . $e($description !== '' ? $description : 'The short course benefit appears here and remains neatly contained inside the card.') . '</p><div class="provided-course-meta"><small data-preview-level>' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . '</small><small data-preview-language>' . $e($course['language'] ?? 'English') . '</small></div><footer><div><small>By</small><strong>' . $e($instructorName) . '</strong></div><b data-preview-price>' . $e($previewPriceText) . '</b></footer></div></article>'
            . '<div class="preview-checklist"><strong>Before submitting</strong><span>✓ Clear public title</span><span>✓ Complete learning promise</span><span>✓ Real thumbnail</span><span>✓ Every lesson contains content</span></div></aside></div>';

        return PortalPage::render('instructor', $courseId > 0 ? 'Course authoring' : 'Create course', $form, '<a class="portal-button secondary" href="/instructor/courses">My courses</a>');
    }

    private static function label(string $title, string $help, string $control): string
    {
        return '<label><span class="field-label-with-info">' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '<button class="field-info" type="button" aria-label="Why this field is needed">i<span role="tooltip">'
            . htmlspecialchars($help, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span></button></span>' . $control . '</label>';
    }

    private static function sectionEditor(array $section, int $sectionIndex, callable $e): string
    {
        $lessons = is_array($section['lessons'] ?? null) ? array_values($section['lessons']) : [];
        $lessonHtml = '';
        foreach ($lessons as $lessonIndex => $lesson) {
            $lessonHtml .= self::lessonEditor($lesson, $sectionIndex, $lessonIndex, $e);
        }
        if ($lessonHtml === '') {
            $lessonHtml = self::lessonEditor([], $sectionIndex, 0, $e);
        }
        return '<article class="course-section-editor" data-section><header><div><span data-section-number>SECTION ' . str_pad((string) ($sectionIndex + 1), 2, '0', STR_PAD_LEFT) . '</span><h4>Learning stage</h4></div><button type="button" class="text-button danger-text" data-remove-section>Remove section</button></header>'
            . self::label('Section title', 'A section groups related lessons into one stage. “Getting started” is only an example for setup and orientation; write a title that describes this actual stage.', '<input type="text" data-section-title minlength="2" maxlength="180" value="' . $e($section['title'] ?? '') . '" placeholder="Getting started" required data-error="Name this learning stage, such as Getting started, Core concepts or Practical project.">')
            . '<div class="course-lesson-list" data-lesson-list>' . $lessonHtml . '</div><button class="portal-button secondary" type="button" data-add-lesson>+ Add next lesson</button></article>';
    }

    private static function lessonEditor(array $lesson, int $sectionIndex, int $lessonIndex, callable $e): string
    {
        $type = (string) ($lesson['content_type'] ?? 'text');
        $options = '';
        foreach (['text' => 'Written text lesson', 'word' => 'Written document lesson', 'video' => 'Uploaded video', 'pdf' => 'Uploaded PDF', 'audio' => 'Uploaded audio', 'image' => 'Uploaded image', 'link' => 'External HTTPS link'] as $value => $label) {
            $options .= '<option value="' . $value . '"' . ($type === $value ? ' selected' : '') . '>' . $label . '</option>';
        }
        $resource = trim((string) ($lesson['content_url'] ?? ''));
        $resourceName = trim((string) ($lesson['content_name'] ?? ''));
        $fileKey = 'lesson_file_' . $sectionIndex . '_' . $lessonIndex;
        return '<article class="course-lesson-editor" data-lesson><header><span data-lesson-number>LESSON ' . str_pad((string) ($lessonIndex + 1), 2, '0', STR_PAD_LEFT) . '</span><button type="button" class="text-button danger-text" data-remove-lesson>Remove lesson</button></header>'
            . '<div class="form-columns">' . self::label('Lesson title', 'Use an outcome-focused title that tells the Student exactly what this lesson covers.', '<input type="text" data-lesson-title minlength="2" maxlength="180" value="' . $e($lesson['title'] ?? '') . '" placeholder="Map the first request and response" required data-error="Write a clear lesson title describing what is taught here.">')
            . self::label('Content type', 'Choose how this lesson is delivered. The correct editor or upload area appears immediately below.', '<select data-content-type>' . $options . '</select>') . '</div>'
            . '<div class="form-columns">' . self::label('Duration in minutes', 'Estimate the time needed to watch, read or complete this lesson. Enter 0 only for a very short reference.', '<input type="number" inputmode="numeric" data-duration min="0" max="10000" step="1" value="' . (int) ($lesson['duration_minutes'] ?? 0) . '" required data-error="Enter the estimated lesson time as a whole number of minutes.">')
            . '<label class="lesson-preview-check"><input type="checkbox" data-is-preview' . (!empty($lesson['is_preview']) ? ' checked' : '') . '> Allow this lesson as a free preview before purchase</label></div>'
            . '<input type="hidden" data-content-url value="' . $e($resource) . '"><input type="hidden" data-content-name value="' . $e($resourceName) . '">'
            . '<div class="lesson-content-panels">'
            . '<div data-content-panel="text word">' . self::label('Written lesson content', 'Write the lesson directly here. Text and document lessons open inside the CourseHub player, so Students do not need another app.', '<textarea data-content-text rows="10" maxlength="200000" placeholder="Write headings, explanations, examples and practice steps here." data-error="Write the lesson content that the Student should read inside CourseHub.">' . $e($lesson['content_text'] ?? '') . '</textarea>') . '</div>'
            . '<div data-content-panel="link">' . self::label('External HTTPS resource', 'Use only an authorised secure link. The Student opens it from the lesson player.', '<input type="url" inputmode="url" data-link-url maxlength="500" value="' . ($type === 'link' ? $e($resource) : '') . '" placeholder="https://..." data-error="Enter a normal HTTPS resource address for this link lesson.">') . '</div>'
            . '<div data-content-panel="video">' . self::fileField('Video file', 'Upload MP4, WebM or MOV. The video plays inside the protected CourseHub player.', 'video/mp4,video/webm,video/quicktime', $fileKey, $resourceName, $e) . '</div>'
            . '<div data-content-panel="pdf">' . self::fileField('PDF lesson file', 'Upload the actual PDF. It opens inside the CourseHub lesson viewer.', 'application/pdf', $fileKey, $resourceName, $e) . '</div>'
            . '<div data-content-panel="audio">' . self::fileField('Audio lesson file', 'Upload MP3, WAV, OGG or M4A. The Student listens inside the player.', 'audio/mpeg,audio/wav,audio/ogg,audio/mp4', $fileKey, $resourceName, $e) . '</div>'
            . '<div data-content-panel="image">' . self::fileField('Lesson image', 'Upload a readable JPG, PNG or WebP image of at least 200 × 200 pixels.', 'image/jpeg,image/png,image/webp', $fileKey, $resourceName, $e) . '</div></div></article>';
    }

    private static function fileField(string $title, string $help, string $accept, string $fileKey, string $existingName, callable $e): string
    {
        $status = $existingName !== '' ? '<small class="existing-resource">Current file: ' . $e($existingName) . '. Select another file only to replace it.</small>' : '<small class="existing-resource">No file uploaded yet.</small>';
        return self::label($title, $help, '<input type="file" data-resource-file name="' . $e($fileKey) . '" accept="' . $e($accept) . '" data-error="Choose the actual lesson file required by this content type.">' . $status);
    }
}
