<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/course_workflow_helper.php';

InstructorMiddleware::handle();

$authUser = Auth::user();
$instructorId = (int) ($authUser['id'] ?? 0);
$message = '';
$messageType = '';

function course_builder_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function course_builder_slug(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'course';
}

function course_builder_unique_slug(mysqli $conn, string $title): string
{
    $baseSlug = course_builder_slug($title);
    $slug = $baseSlug;
    $counter = 2;
    $stmt = $conn->prepare('SELECT id FROM courses WHERE slug = ? LIMIT 1');

    while (true) {
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;

        if (!$exists) {
            $stmt->close();
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

function course_builder_safe_key(string $key): string
{
    return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $key);
}

function course_builder_delete_file(string $directory, ?string $fileName): void
{
    $safeName = basename((string) $fileName);

    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        return;
    }

    $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (is_file($path)) {
        unlink($path);
    }
}

function course_builder_upload_thumbnail(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'The thumbnail upload failed.';
        return null;
    }

    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $errors[] = 'The thumbnail must be 2 MB or smaller.';
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        $errors[] = 'The thumbnail must be a JPG, PNG, or WebP image.';
        return null;
    }

    $directory = __DIR__ . '/../../../public/assets/uploads/course_thumbnails';

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        $errors[] = 'The thumbnail directory could not be created.';
        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $fileName)) {
        $errors[] = 'The thumbnail could not be saved.';
        return null;
    }

    return $fileName;
}

function course_builder_upload_resource(array $file, string $type, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'A lesson resource failed to upload.';
        return null;
    }

    if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
        $errors[] = 'Lesson resources must be 20 MB or smaller.';
        return null;
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $allowed = $type === 'pdf'
        ? ['pdf' => ['application/pdf']]
        : [
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
        ];

    if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
        $errors[] = $type === 'pdf'
            ? 'PDF lessons must contain a valid PDF file.'
            : 'Word lessons must contain a valid DOC or DOCX file.';
        return null;
    }

    $directory = __DIR__ . '/../../../storage/private_uploads/course_resources';

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        $errors[] = 'The private lesson resource directory could not be created.';
        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $fileName)) {
        $errors[] = 'A lesson resource could not be saved.';
        return null;
    }

    return $fileName;
}

function course_builder_load_course(mysqli $conn, int $courseId, int $instructorId): ?array
{
    $stmt = $conn->prepare("
        SELECT id, category_id, title, slug, short_description, full_description,
               thumbnail, price, level, language, status
        FROM courses
        WHERE id = ?
          AND instructor_id = ?
          AND status IN ('draft', 'rejected', 'published')
        LIMIT 1
    ");
    $stmt->bind_param('ii', $courseId, $instructorId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $course;
}

function course_builder_load_sections(mysqli $conn, int $courseId): array
{
    $stmt = $conn->prepare("
        SELECT s.id AS section_id, s.title AS section_title, s.sort_order AS section_order,
               l.id AS lesson_id, l.title AS lesson_title, l.content_type,
               l.content_url, l.content_text, l.duration_minutes,
               l.is_preview, l.sort_order AS lesson_order
        FROM course_sections s
        LEFT JOIN course_lessons l ON l.section_id = s.id
        WHERE s.course_id = ?
        ORDER BY s.sort_order, s.id, l.sort_order, l.id
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sections = [];

    while ($row = $result->fetch_assoc()) {
        $sectionKey = 'section_' . (int) $row['section_id'];

        if (!isset($sections[$sectionKey])) {
            $sections[$sectionKey] = [
                'key' => $sectionKey,
                'title' => (string) $row['section_title'],
                'lessons' => [],
            ];
        }

        if (!empty($row['lesson_id'])) {
            $lessonKey = 'lesson_' . (int) $row['lesson_id'];
            $sections[$sectionKey]['lessons'][$lessonKey] = [
                'key' => $lessonKey,
                'id' => (int) $row['lesson_id'],
                'title' => (string) $row['lesson_title'],
                'type' => (string) $row['content_type'],
                'content_text' => (string) ($row['content_text'] ?? ''),
                'content_url' => (string) ($row['content_url'] ?? ''),
                'duration' => (int) $row['duration_minutes'],
                'is_preview' => (int) $row['is_preview'] === 1,
            ];
        }
    }

    $stmt->close();

    return array_values(array_map(static function (array $section): array {
        $section['lessons'] = array_values($section['lessons']);
        return $section;
    }, $sections));
}

function course_builder_existing_lessons(mysqli $conn, int $courseId): array
{
    $stmt = $conn->prepare("
        SELECT l.id, l.content_type, l.content_url
        FROM course_lessons l
        INNER JOIN course_sections s ON s.id = l.section_id
        WHERE s.course_id = ?
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lessons = [];

    while ($row = $result->fetch_assoc()) {
        $lessons[(int) $row['id']] = $row;
    }

    $stmt->close();
    return $lessons;
}

function course_builder_duration_label(int $minutes): ?string
{
    if ($minutes <= 0) {
        return null;
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours > 0 && $remainingMinutes > 0) {
        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    return $hours > 0 ? $hours . 'h' : $remainingMinutes . 'm';
}

function course_builder_snapshot(array $sections): array
{
    return array_map(static function (array $section): array {
        return [
            'title' => trim((string) ($section['title'] ?? '')),
            'lessons' => array_map(static function (array $lesson): array {
                return [
                    'title' => trim((string) ($lesson['title'] ?? '')),
                    'type' => (string) ($lesson['type'] ?? ''),
                    'content_text' => (string) ($lesson['content_text'] ?? ''),
                    'content_url' => basename((string) ($lesson['content_url'] ?? '')),
                    'duration' => (int) ($lesson['duration'] ?? 0),
                    'is_preview' => !empty($lesson['is_preview']),
                ];
            }, $section['lessons'] ?? []),
        ];
    }, $sections);
}

$categories = [];
$categoryResult = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");

while ($categoryResult && $category = $categoryResult->fetch_assoc()) {
    $categories[] = $category;
}

$draftId = (int) ($_POST['draft_id'] ?? $_GET['draft_id'] ?? 0);
$editingCourse = $draftId > 0 ? course_builder_load_course($conn, $draftId, $instructorId) : null;
$isPublishedEdit = $editingCourse && $editingCourse['status'] === 'published';

if ($draftId > 0 && !$editingCourse) {
    http_response_code(404);
    exit('Draft not found or it can no longer be edited.');
}

$form = [
    'title' => (string) ($editingCourse['title'] ?? ''),
    'category_id' => (int) ($editingCourse['category_id'] ?? 0),
    'price' => (string) ($editingCourse['price'] ?? '0.00'),
    'level' => (string) ($editingCourse['level'] ?? 'beginner'),
    'language' => (string) ($editingCourse['language'] ?? 'English'),
    'short_description' => (string) ($editingCourse['short_description'] ?? ''),
    'full_description' => (string) ($editingCourse['full_description'] ?? ''),
    'thumbnail' => (string) ($editingCourse['thumbnail'] ?? ''),
];

$builderSections = $editingCourse ? course_builder_load_sections($conn, $draftId) : [];
$originalSections = $builderSections;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['course_action'] ?? '');
    $targetStatus = $action === 'submit_review' ? 'pending' : 'draft';
    $isSubmitting = $targetStatus === 'pending';
    $errors = [];
    $newFiles = [];
    $retainedResourceFiles = [];

    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $form['price'] = trim((string) ($_POST['price'] ?? '0'));
    $form['level'] = trim((string) ($_POST['level'] ?? 'beginner'));
    $form['language'] = trim((string) ($_POST['language'] ?? 'English'));
    $form['short_description'] = trim((string) ($_POST['short_description'] ?? ''));
    $form['full_description'] = trim((string) ($_POST['full_description'] ?? ''));

    if ($isPublishedEdit) {
        if ($action !== 'submit_review') {
            $errors[] = 'Published courses can only submit lesson changes for admin review.';
        }

        $targetStatus = 'pending';
        $isSubmitting = true;
        $form = [
            'title' => (string) $editingCourse['title'],
            'category_id' => (int) ($editingCourse['category_id'] ?? 0),
            'price' => (string) $editingCourse['price'],
            'level' => (string) $editingCourse['level'],
            'language' => (string) $editingCourse['language'],
            'short_description' => (string) $editingCourse['short_description'],
            'full_description' => (string) $editingCourse['full_description'],
            'thumbnail' => (string) ($editingCourse['thumbnail'] ?? ''),
        ];
    }

    if (!in_array($action, ['save_draft', 'submit_review'], true)) {
        $errors[] = 'Choose Save draft or Submit for review.';
    }

    if ($form['title'] === '' || strlen($form['title']) > 180) {
        $errors[] = 'Course title is required and must be 180 characters or fewer.';
    }

    if (!is_numeric($form['price']) || (float) $form['price'] < 0 || (float) $form['price'] > 9999999999.99) {
        $errors[] = 'Enter a valid non-negative course price.';
    }

    if (!in_array($form['level'], ['beginner', 'intermediate', 'advanced'], true)) {
        $errors[] = 'Choose a valid course level.';
    }

    if ($form['language'] === '' || strlen($form['language']) > 60) {
        $errors[] = 'Language is required and must be 60 characters or fewer.';
    }

    if (strlen($form['short_description']) > 500) {
        $errors[] = 'Short description must be 500 characters or fewer.';
    }

    if ($form['category_id'] > 0) {
        $categoryCheck = $conn->prepare("SELECT id FROM categories WHERE id = ? AND status = 'active' LIMIT 1");
        $categoryCheck->bind_param('i', $form['category_id']);
        $categoryCheck->execute();
        $validCategory = $categoryCheck->get_result()->num_rows === 1;
        $categoryCheck->close();

        if (!$validCategory) {
            $errors[] = 'Choose an active course category.';
        }
    } elseif ($isSubmitting) {
        $errors[] = 'Choose a category before submitting the course.';
    }

    if ($isSubmitting && $form['short_description'] === '') {
        $errors[] = 'Add a short description before submitting the course.';
    }

    if ($isSubmitting && $form['full_description'] === '') {
        $errors[] = 'Add a full description before submitting the course.';
    }

    $newThumbnail = $isPublishedEdit
        ? null
        : course_builder_upload_thumbnail($_FILES['thumbnail'] ?? [], $errors);

    if ($newThumbnail !== null) {
        $newFiles[] = ['thumbnail', $newThumbnail];
        $form['thumbnail'] = $newThumbnail;
    }

    if ($isSubmitting && $form['thumbnail'] === '') {
        $errors[] = 'Upload a course thumbnail before submitting the course.';
    }

    $existingLessons = $editingCourse ? course_builder_existing_lessons($conn, $draftId) : [];
    $builderSections = [];
    $sectionKeys = is_array($_POST['section_keys'] ?? null) ? $_POST['section_keys'] : [];
    $allowedLessonTypes = ['text', 'video', 'link', 'pdf', 'word'];
    $totalLessonCount = 0;
    $totalDurationMinutes = 0;

    foreach ($sectionKeys as $rawSectionKey) {
        $sectionKey = course_builder_safe_key((string) $rawSectionKey);

        if ($sectionKey === '') {
            continue;
        }

        $sectionTitle = trim((string) ($_POST['section_title'][$sectionKey] ?? ''));
        $lessonKeys = $_POST['lesson_keys'][$sectionKey] ?? [];
        $section = [
            'key' => $sectionKey,
            'title' => $sectionTitle,
            'lessons' => [],
        ];

        if ($isSubmitting && $sectionTitle === '') {
            $errors[] = 'Every chapter needs a title.';
        }

        foreach (is_array($lessonKeys) ? $lessonKeys : [] as $rawLessonKey) {
            $lessonKey = course_builder_safe_key((string) $rawLessonKey);

            if ($lessonKey === '') {
                continue;
            }

            $lessonId = (int) ($_POST['lesson_id'][$sectionKey][$lessonKey] ?? 0);
            $lessonTitle = trim((string) ($_POST['lesson_title'][$sectionKey][$lessonKey] ?? ''));
            $lessonType = trim((string) ($_POST['lesson_type'][$sectionKey][$lessonKey] ?? 'text'));
            $contentText = trim((string) ($_POST['lesson_content'][$sectionKey][$lessonKey] ?? ''));
            $contentUrl = trim((string) ($_POST['lesson_url'][$sectionKey][$lessonKey] ?? ''));
            $duration = max(0, min(1440, (int) ($_POST['lesson_duration'][$sectionKey][$lessonKey] ?? 0)));
            $isPreview = isset($_POST['lesson_preview'][$sectionKey][$lessonKey]);

            if (!in_array($lessonType, $allowedLessonTypes, true)) {
                $lessonType = 'text';
                $errors[] = 'A lesson contains an invalid content type.';
            }

            if ($isSubmitting && $lessonTitle === '') {
                $errors[] = 'Every lesson needs a title.';
            }

            $storedFile = null;
            $existingLesson = $lessonId > 0 && isset($existingLessons[$lessonId])
                ? $existingLessons[$lessonId]
                : null;

            if ($lessonType === 'text') {
                $contentText = Security::sanitizeRichText($contentText);

                if ($isSubmitting && trim(strip_tags($contentText)) === '') {
                    $errors[] = 'Text lessons must contain lesson content.';
                }

                $contentUrl = null;
            } elseif (in_array($lessonType, ['video', 'link'], true)) {
                $contentText = null;

                if ($contentUrl !== '' && filter_var($contentUrl, FILTER_VALIDATE_URL) === false) {
                    $errors[] = 'Video and link lessons must contain a valid URL.';
                }

                if ($isSubmitting && $contentUrl === '') {
                    $errors[] = 'Video and link lessons must contain a URL.';
                }
            } else {
                $contentText = null;
                $contentUrl = null;
                $fileInputName = 'lesson_file_' . $sectionKey . '_' . $lessonKey;
                $uploadedFile = course_builder_upload_resource(
                    $_FILES[$fileInputName] ?? [],
                    $lessonType,
                    $errors
                );

                if ($uploadedFile !== null) {
                    $storedFile = $uploadedFile;
                    $newFiles[] = ['resource', $uploadedFile];
                } elseif (
                    $existingLesson
                    && ($existingLesson['content_type'] ?? '') === $lessonType
                    && !empty($existingLesson['content_url'])
                ) {
                    $storedFile = basename((string) $existingLesson['content_url']);
                }

                if ($isSubmitting && !$storedFile) {
                    $errors[] = strtoupper($lessonType) . ' lessons must contain a file.';
                }

                $contentUrl = $storedFile;

                if ($storedFile) {
                    $retainedResourceFiles[] = $storedFile;
                }
            }

            $section['lessons'][] = [
                'key' => $lessonKey,
                'id' => $lessonId,
                'title' => $lessonTitle,
                'type' => $lessonType,
                'content_text' => (string) ($contentText ?? ''),
                'content_url' => (string) ($contentUrl ?? ''),
                'duration' => $duration,
                'is_preview' => $isPreview,
            ];

            $totalLessonCount++;
            $totalDurationMinutes += $duration;
        }

        if ($isSubmitting && empty($section['lessons'])) {
            $errors[] = 'Every chapter needs at least one lesson.';
        }

        $builderSections[] = $section;
    }

    if ($isSubmitting && empty($builderSections)) {
        $errors[] = 'Add at least one chapter before submitting the course.';
    }

    if ($isSubmitting && $totalLessonCount === 0) {
        $errors[] = 'Add at least one lesson before submitting the course.';
    }

    if (
        $isPublishedEdit
        && course_builder_snapshot($originalSections) === course_builder_snapshot($builderSections)
    ) {
        $errors[] = 'No lesson changes were detected.';
    }

    if (empty($errors)) {
        $oldThumbnail = (string) ($editingCourse['thumbnail'] ?? '');
        $oldResourceFiles = array_values(array_filter(array_map(
            static fn (array $lesson): string => basename((string) ($lesson['content_url'] ?? '')),
            $existingLessons
        )));

        try {
            $conn->begin_transaction();
            $price = (float) $form['price'];
            $categoryId = $form['category_id'] > 0 ? $form['category_id'] : null;
            $durationLabel = course_builder_duration_label($totalDurationMinutes);
            $submittedAt = $targetStatus === 'pending' ? date('Y-m-d H:i:s') : null;

            if ($editingCourse) {
                $courseId = (int) $editingCourse['id'];
                $update = $conn->prepare("
                    UPDATE courses
                    SET category_id = ?, title = ?, short_description = ?, full_description = ?,
                        thumbnail = ?, price = ?, level = ?, language = ?, duration = ?,
                        submitted_at = ?, reviewed_at = NULL, reviewed_by = NULL,
                        review_note = NULL, status = ?
                    WHERE id = ? AND instructor_id = ? AND status IN ('draft', 'rejected', 'published')
                ");
                $update->bind_param(
                    'issssdsssssii',
                    $categoryId,
                    $form['title'],
                    $form['short_description'],
                    $form['full_description'],
                    $form['thumbnail'],
                    $price,
                    $form['level'],
                    $form['language'],
                    $durationLabel,
                    $submittedAt,
                    $targetStatus,
                    $courseId,
                    $instructorId
                );
                $update->execute();
                $update->close();

                $deleteSections = $conn->prepare('DELETE FROM course_sections WHERE course_id = ?');
                $deleteSections->bind_param('i', $courseId);
                $deleteSections->execute();
                $deleteSections->close();
            } else {
                $slug = course_builder_unique_slug($conn, $form['title']);
                $insert = $conn->prepare("
                    INSERT INTO courses (
                        instructor_id, category_id, title, slug, short_description,
                        full_description, thumbnail, price, level, language, duration,
                        submitted_at, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->bind_param(
                    'iisssssdsssss',
                    $instructorId,
                    $categoryId,
                    $form['title'],
                    $slug,
                    $form['short_description'],
                    $form['full_description'],
                    $form['thumbnail'],
                    $price,
                    $form['level'],
                    $form['language'],
                    $durationLabel,
                    $submittedAt,
                    $targetStatus
                );
                $insert->execute();
                $courseId = (int) $conn->insert_id;
                $insert->close();
            }

            $insertSection = $conn->prepare(
                'INSERT INTO course_sections (course_id, title, sort_order) VALUES (?, ?, ?)'
            );
            $insertLesson = $conn->prepare("
                INSERT INTO course_lessons (
                    section_id, title, content_type, content_url, content_text,
                    duration_minutes, is_preview, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($builderSections as $sectionIndex => $section) {
                $sectionTitle = $section['title'] !== ''
                    ? $section['title']
                    : 'Untitled chapter';
                $sectionOrder = $sectionIndex + 1;
                $insertSection->bind_param('isi', $courseId, $sectionTitle, $sectionOrder);
                $insertSection->execute();
                $sectionId = (int) $conn->insert_id;

                foreach ($section['lessons'] as $lessonIndex => $lesson) {
                    $lessonTitle = $lesson['title'] !== '' ? $lesson['title'] : 'Untitled lesson';
                    $contentUrl = $lesson['content_url'] !== '' ? $lesson['content_url'] : null;
                    $contentText = $lesson['content_text'] !== '' ? $lesson['content_text'] : null;
                    $duration = (int) $lesson['duration'];
                    $preview = $lesson['is_preview'] ? 1 : 0;
                    $lessonOrder = $lessonIndex + 1;
                    $insertLesson->bind_param(
                        'issssiii',
                        $sectionId,
                        $lessonTitle,
                        $lesson['type'],
                        $contentUrl,
                        $contentText,
                        $duration,
                        $preview,
                        $lessonOrder
                    );
                    $insertLesson->execute();
                }
            }

            $insertSection->close();
            $insertLesson->close();

            if ($targetStatus === 'pending') {
                course_workflow_record_change(
                    $conn,
                    $courseId,
                    $instructorId,
                    $form['title'],
                    $isPublishedEdit ? 'lesson_update' : 'course_submission',
                    course_builder_snapshot($originalSections),
                    course_builder_snapshot($builderSections),
                    (string) ($editingCourse['status'] ?? 'new'),
                    'pending'
                );
            }

            $conn->commit();

            if ($newThumbnail && $oldThumbnail && $newThumbnail !== $oldThumbnail) {
                course_builder_delete_file(
                    __DIR__ . '/../../../public/assets/uploads/course_thumbnails',
                    $oldThumbnail
                );
            }

            foreach (array_diff($oldResourceFiles, $retainedResourceFiles) as $removedFile) {
                course_builder_delete_file(
                    __DIR__ . '/../../../storage/private_uploads/course_resources',
                    $removedFile
                );
            }

            if ($targetStatus === 'draft') {
                header('Location: instructor-create-course.php?draft_id=' . $courseId . '&saved=1');
            } else {
                header('Location: instructor-courses.php?submitted=1');
            }

            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('Course builder save failed: ' . $exception->getMessage());
            $errors[] = 'The course could not be saved. Please try again.';
        }
    }

    if (!empty($errors)) {
        foreach ($newFiles as [$kind, $fileName]) {
            $directory = $kind === 'thumbnail'
                ? __DIR__ . '/../../../public/assets/uploads/course_thumbnails'
                : __DIR__ . '/../../../storage/private_uploads/course_resources';
            course_builder_delete_file($directory, $fileName);
        }

        if ($newThumbnail !== null) {
            $form['thumbnail'] = (string) ($editingCourse['thumbnail'] ?? '');
        }

        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    }
}

if (isset($_GET['saved'])) {
    $message = 'Draft saved. It remains private and can be edited here later.';
    $messageType = 'success';
}

$draftCourses = [];
$draftStmt = $conn->prepare("
    SELECT id, title, status, updated_at
    FROM courses
    WHERE instructor_id = ? AND status IN ('draft', 'rejected')
    ORDER BY updated_at DESC
");
$draftStmt->bind_param('i', $instructorId);
$draftStmt->execute();
$draftResult = $draftStmt->get_result();

while ($draft = $draftResult->fetch_assoc()) {
    $draftCourses[] = $draft;
}

$draftStmt->close();
$pageTitle = $isPublishedEdit ? 'Manage course lessons' : ($editingCourse ? 'Edit course draft' : 'Create course');

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>

<link rel="stylesheet" href="assets/css/pages/instructor/create-course.css?v=2">

<main class="course-builder-page">
    <section class="course-builder-shell">
        <header class="builder-heading">
            <div>
                <p class="eyebrow">Instructor course builder</p>
                <h1><?php echo $isPublishedEdit ? 'Manage course lessons' : ($editingCourse ? 'Edit draft' : 'Create a new course'); ?></h1>
                <p>Add the course information first, then organize learning material chapter by chapter.</p>
            </div>
            <?php if ($editingCourse): ?>
                <a class="new-course-link" href="instructor-create-course.php">Create another course</a>
            <?php endif; ?>
        </header>

        <p class="privacy-note">
            <?php if ($isPublishedEdit): ?>
                <strong>Course information is locked.</strong> You can change only chapters and lessons. Every saved content change is recorded and sent to admin for approval.
            <?php else: ?>
                <strong>Drafts are private.</strong> Saving a draft does not send it to admin and does not show it to students.
                Admin receives the course only when you choose <strong>Submit for review</strong>.
            <?php endif; ?>
        </p>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo course_builder_h($messageType); ?>">
                <?php echo course_builder_h($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($draftCourses): ?>
            <section class="draft-panel">
                <div class="draft-panel-header">
                    <h2>Your private drafts</h2>
                    <span><?php echo count($draftCourses); ?> saved</span>
                </div>
                <div class="draft-list">
                    <?php foreach ($draftCourses as $draft): ?>
                        <div class="draft-row">
                            <div>
                                <strong><?php echo course_builder_h($draft['title']); ?></strong>
                                <small>
                                    <?php echo $draft['status'] === 'rejected' ? 'Rejected — revise and resubmit' : 'Private draft'; ?>
                                    · Updated <?php echo course_builder_h(date('M j, Y g:i A', strtotime($draft['updated_at']))); ?>
                                </small>
                            </div>
                            <a href="instructor-create-course.php?draft_id=<?php echo (int) $draft['id']; ?>">Edit draft</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="courseBuilderForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="draft_id" value="<?php echo (int) $draftId; ?>">

            <section class="builder-card">
                <div class="card-title">
                    <span class="step-number">1</span>
                    <div>
                        <h2>Course details</h2>
                        <p>Information students see before purchasing the course.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <fieldset class="form-group form-group-wide" <?php echo $isPublishedEdit ? 'disabled' : ''; ?> style="display:contents">
                    <div class="form-group form-group-wide">
                        <label for="title">Course title *</label>
                        <input id="title" name="title" maxlength="180" required
                               value="<?php echo course_builder_h($form['title']); ?>"
                               placeholder="Example: Complete Web Application Security">
                    </div>

                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="0">Choose category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>"
                                    <?php echo (int) $form['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo course_builder_h($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (Rs.)</label>
                        <input id="price" name="price" type="number" min="0" max="9999999999.99" step="0.01"
                               value="<?php echo course_builder_h($form['price']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="level">Level</label>
                        <select id="level" name="level">
                            <?php foreach (['beginner', 'intermediate', 'advanced'] as $level): ?>
                                <option value="<?php echo $level; ?>" <?php echo $form['level'] === $level ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($level); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="language">Language</label>
                        <input id="language" name="language" maxlength="60"
                               value="<?php echo course_builder_h($form['language']); ?>">
                    </div>

                    <div class="form-group form-group-wide">
                        <label for="short_description">Short description</label>
                        <textarea id="short_description" name="short_description" rows="3" maxlength="500"
                                  placeholder="A short summary for the course card"><?php echo course_builder_h($form['short_description']); ?></textarea>
                    </div>

                    <div class="form-group form-group-wide">
                        <label for="full_description">Full description</label>
                        <textarea id="full_description" name="full_description" rows="7"
                                  placeholder="Explain what students will learn, prerequisites, and outcomes"><?php echo course_builder_h($form['full_description']); ?></textarea>
                    </div>

                    <div class="form-group form-group-wide">
                        <label for="thumbnail">Course thumbnail</label>
                        <?php if ($form['thumbnail'] !== ''): ?>
                            <div class="current-thumbnail">
                                <img src="assets/uploads/course_thumbnails/<?php echo rawurlencode(basename($form['thumbnail'])); ?>"
                                     alt="Current course thumbnail">
                                <span class="form-hint">Upload a new image only if you want to replace this thumbnail.</span>
                            </div>
                        <?php endif; ?>
                        <input id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp">
                        <span class="form-hint">JPG, PNG, or WebP. Maximum 2 MB.</span>
                    </div>
                    </fieldset>
                </div>
            </section>

            <section class="builder-card">
                <div class="card-title">
                    <span class="step-number">2</span>
                    <div>
                        <h2>Chapters and lessons</h2>
                        <p>Organize the complete course curriculum in the order students should learn it.</p>
                    </div>
                </div>

                <div class="chapter-list" id="chapterList">
                    <?php foreach ($builderSections as $section): ?>
                        <article class="chapter-card" data-section-key="<?php echo course_builder_h($section['key']); ?>">
                            <input type="hidden" name="section_keys[]" value="<?php echo course_builder_h($section['key']); ?>">
                            <div class="chapter-header">
                                <input name="section_title[<?php echo course_builder_h($section['key']); ?>]"
                                       value="<?php echo course_builder_h($section['title']); ?>"
                                       placeholder="Chapter title">
                                <button type="button" class="builder-button button-danger remove-chapter">Remove chapter</button>
                            </div>
                            <div class="lessons-list">
                                <?php foreach ($section['lessons'] as $lesson): ?>
                                    <article class="lesson-card" data-lesson-key="<?php echo course_builder_h($lesson['key']); ?>">
                                        <input type="hidden" name="lesson_keys[<?php echo course_builder_h($section['key']); ?>][]" value="<?php echo course_builder_h($lesson['key']); ?>">
                                        <input type="hidden" name="lesson_id[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" value="<?php echo (int) $lesson['id']; ?>">
                                        <div class="lesson-top">
                                            <input name="lesson_title[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]"
                                                   value="<?php echo course_builder_h($lesson['title']); ?>" placeholder="Lesson title">
                                            <select class="lesson-type" name="lesson_type[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]">
                                                <?php foreach (['text' => 'Text lesson', 'video' => 'Video URL', 'link' => 'External link', 'pdf' => 'PDF file', 'word' => 'Word file'] as $type => $label): ?>
                                                    <option value="<?php echo $type; ?>" <?php echo $lesson['type'] === $type ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="builder-button button-danger remove-lesson">Remove</button>
                                        </div>
                                        <div class="lesson-fields">
                                            <div class="lesson-content-field text-field">
                                                <textarea name="lesson_content[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" placeholder="Write the lesson content here"><?php echo course_builder_h($lesson['content_text']); ?></textarea>
                                            </div>
                                            <div class="lesson-content-field url-field">
                                                <input name="lesson_url[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]"
                                                       value="<?php echo in_array($lesson['type'], ['video', 'link'], true) ? course_builder_h($lesson['content_url']) : ''; ?>"
                                                       placeholder="https://example.com/video-or-resource">
                                            </div>
                                            <div class="lesson-content-field file-field">
                                                <?php if (in_array($lesson['type'], ['pdf', 'word'], true) && $lesson['content_url'] !== ''): ?>
                                                    <p class="resource-current">Current private file: <?php echo course_builder_h(basename($lesson['content_url'])); ?></p>
                                                <?php endif; ?>
                                                <input type="file" name="lesson_file_<?php echo course_builder_h($section['key']); ?>_<?php echo course_builder_h($lesson['key']); ?>">
                                            </div>
                                            <div>
                                                <label>Duration (minutes)</label>
                                                <input type="number" min="0" max="1440"
                                                       name="lesson_duration[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]"
                                                       value="<?php echo (int) $lesson['duration']; ?>">
                                            </div>
                                            <label class="preview-check">
                                                <input type="checkbox"
                                                       name="lesson_preview[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]"
                                                       value="1" <?php echo $lesson['is_preview'] ? 'checked' : ''; ?>>
                                                Free preview lesson
                                            </label>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <div class="chapter-actions">
                                <button type="button" class="builder-button button-secondary add-lesson">+ Add lesson</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="empty-builder" id="emptyBuilder" <?php echo $builderSections ? 'hidden' : ''; ?>>
                    No chapters yet. You may save an incomplete draft, or add a chapter now.
                </div>

                <p style="margin:16px 0 0">
                    <button type="button" class="builder-button button-secondary" id="addChapter">+ Add chapter</button>
                </p>
            </section>

            <div class="form-actions">
                <?php if (!$isPublishedEdit): ?>
                    <button type="submit" class="builder-button button-primary" name="course_action" value="save_draft">
                        Save private draft
                    </button>
                <?php endif; ?>
                <button type="submit" class="builder-button button-submit" name="course_action" value="submit_review" id="submitReview">
                    <?php echo $isPublishedEdit ? 'Submit lesson changes' : 'Submit for admin review'; ?>
                </button>
            </div>
        </form>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chapterList = document.getElementById('chapterList');
    const emptyBuilder = document.getElementById('emptyBuilder');
    const addChapterButton = document.getElementById('addChapter');
    const form = document.getElementById('courseBuilderForm');
    let sequence = Date.now();

    const safeKey = prefix => `${prefix}_${++sequence}`;
    const escapeName = value => String(value).replace(/[^A-Za-z0-9_-]/g, '');

    function lessonMarkup(sectionKey, lessonKey) {
        const section = escapeName(sectionKey);
        const lesson = escapeName(lessonKey);
        return `
            <article class="lesson-card" data-lesson-key="${lesson}">
                <input type="hidden" name="lesson_keys[${section}][]" value="${lesson}">
                <input type="hidden" name="lesson_id[${section}][${lesson}]" value="0">
                <div class="lesson-top">
                    <input name="lesson_title[${section}][${lesson}]" placeholder="Lesson title">
                    <select class="lesson-type" name="lesson_type[${section}][${lesson}]">
                        <option value="text">Text lesson</option>
                        <option value="video">Video URL</option>
                        <option value="link">External link</option>
                        <option value="pdf">PDF file</option>
                        <option value="word">Word file</option>
                    </select>
                    <button type="button" class="builder-button button-danger remove-lesson">Remove</button>
                </div>
                <div class="lesson-fields">
                    <div class="lesson-content-field text-field">
                        <textarea name="lesson_content[${section}][${lesson}]" placeholder="Write the lesson content here"></textarea>
                    </div>
                    <div class="lesson-content-field url-field">
                        <input name="lesson_url[${section}][${lesson}]" placeholder="https://example.com/video-or-resource">
                    </div>
                    <div class="lesson-content-field file-field">
                        <input type="file" name="lesson_file_${section}_${lesson}">
                    </div>
                    <div>
                        <label>Duration (minutes)</label>
                        <input type="number" min="0" max="1440" name="lesson_duration[${section}][${lesson}]" value="0">
                    </div>
                    <label class="preview-check">
                        <input type="checkbox" name="lesson_preview[${section}][${lesson}]" value="1">
                        Free preview lesson
                    </label>
                </div>
            </article>`;
    }

    function chapterMarkup(sectionKey) {
        const section = escapeName(sectionKey);
        const lesson = safeKey('lesson');
        return `
            <article class="chapter-card" data-section-key="${section}">
                <input type="hidden" name="section_keys[]" value="${section}">
                <div class="chapter-header">
                    <input name="section_title[${section}]" placeholder="Chapter title">
                    <button type="button" class="builder-button button-danger remove-chapter">Remove chapter</button>
                </div>
                <div class="lessons-list">${lessonMarkup(section, lesson)}</div>
                <div class="chapter-actions">
                    <button type="button" class="builder-button button-secondary add-lesson">+ Add lesson</button>
                </div>
            </article>`;
    }

    function updateEmptyState() {
        emptyBuilder.hidden = chapterList.querySelector('.chapter-card') !== null;
    }

    function updateLessonFields(lessonCard) {
        const type = lessonCard.querySelector('.lesson-type').value;
        const textField = lessonCard.querySelector('.text-field');
        const urlField = lessonCard.querySelector('.url-field');
        const fileField = lessonCard.querySelector('.file-field');
        textField.hidden = type !== 'text';
        urlField.hidden = !['video', 'link'].includes(type);
        fileField.hidden = !['pdf', 'word'].includes(type);
        const fileInput = fileField.querySelector('input[type="file"]');
        fileInput.accept = type === 'pdf'
            ? 'application/pdf,.pdf'
            : '.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    addChapterButton.addEventListener('click', () => {
        chapterList.insertAdjacentHTML('beforeend', chapterMarkup(safeKey('section')));
        const newChapter = chapterList.lastElementChild;
        updateLessonFields(newChapter.querySelector('.lesson-card'));
        updateEmptyState();
        newChapter.querySelector('.chapter-header input').focus();
    });

    chapterList.addEventListener('click', event => {
        const addLesson = event.target.closest('.add-lesson');
        const removeLesson = event.target.closest('.remove-lesson');
        const removeChapter = event.target.closest('.remove-chapter');

        if (addLesson) {
            const chapter = addLesson.closest('.chapter-card');
            const sectionKey = chapter.dataset.sectionKey;
            chapter.querySelector('.lessons-list').insertAdjacentHTML(
                'beforeend',
                lessonMarkup(sectionKey, safeKey('lesson'))
            );
            const newLesson = chapter.querySelector('.lessons-list').lastElementChild;
            updateLessonFields(newLesson);
            newLesson.querySelector('input:not([type="hidden"])').focus();
        }

        if (removeLesson && confirm('Remove this lesson from the draft?')) {
            removeLesson.closest('.lesson-card').remove();
        }

        if (removeChapter && confirm('Remove this chapter and all lessons inside it?')) {
            removeChapter.closest('.chapter-card').remove();
            updateEmptyState();
        }
    });

    chapterList.addEventListener('change', event => {
        if (event.target.classList.contains('lesson-type')) {
            updateLessonFields(event.target.closest('.lesson-card'));
        }
    });

    chapterList.querySelectorAll('.lesson-card').forEach(updateLessonFields);
    updateEmptyState();

    form.addEventListener('submit', event => {
        const submitter = event.submitter;

        if (submitter && submitter.value === 'submit_review') {
            const confirmed = confirm(
                'Submit this course to admin? You will not be able to edit it while it is pending review.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
