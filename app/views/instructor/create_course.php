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


function course_builder_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function course_builder_clean_text(mixed $value, bool $multiline = false): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    $text = strip_tags((string) $value);
    $text = str_replace("\0", '', $text);
    $text = (string) preg_replace('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    if ($multiline) {
        $text = (string) preg_replace('/[\t ]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);
        return trim($text);
    }

    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function course_builder_clean_rich_text(mixed $value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    return trim(Security::sanitizeRichText((string) $value));
}

function course_builder_normalize_url(mixed $value): ?string
{
    if (!is_scalar($value) && $value !== null) {
        return null;
    }

    $url = trim((string) $value);

    if ($url === '') {
        return '';
    }

    if (course_builder_length($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }

    return $url;
}

function course_builder_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is larger than the server allows.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload directory is unavailable.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload.',
        default => 'The file upload failed.',
    };
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
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = course_builder_upload_error_message($errorCode);
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'The thumbnail upload could not be verified.';
        return null;
    }

    if ($size < 1 || $size > 2 * 1024 * 1024) {
        $errors[] = 'The thumbnail must be a non-empty image no larger than 2 MB.';
        return null;
    }

    $imageInfo = @getimagesize($tmpName);

    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        $errors[] = 'The thumbnail content is not a valid image.';
        return null;
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];

    if ($width < 320 || $height < 180) {
        $errors[] = 'The thumbnail must be at least 320 × 180 pixels.';
        return null;
    }

    if ($width > 8000 || $height > 8000 || ($width * $height) > 40_000_000) {
        $errors[] = 'The thumbnail dimensions are too large.';
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_string($mime) || !isset($allowed[$mime])) {
        $errors[] = 'The thumbnail must contain a genuine JPG, PNG, or WebP image.';
        return null;
    }

    $directory = __DIR__ . '/../../../public/assets/uploads/course_thumbnails';

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        $errors[] = 'The thumbnail directory could not be created.';
        return null;
    }

    $fileName = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
    $destination = $directory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'The thumbnail could not be saved.';
        return null;
    }

    @chmod($destination, 0644);
    return $fileName;
}

function course_builder_upload_resource(array $file, string $type, array &$errors): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = course_builder_upload_error_message($errorCode);
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'A lesson resource upload could not be verified.';
        return null;
    }

    if ($size < 1 || $size > 20 * 1024 * 1024) {
        $errors[] = 'Lesson resources must be non-empty and 20 MB or smaller.';
        return null;
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $header = (string) file_get_contents($tmpName, false, null, 0, 8);
    $isValid = false;

    if ($type === 'pdf' && $extension === 'pdf') {
        $isValid = str_starts_with($header, '%PDF-') && $mime === 'application/pdf';
    }

    if ($type === 'word' && $extension === 'doc') {
        $isOleDocument = $header === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        $isValid = $isOleDocument && in_array($mime, [
            'application/msword',
            'application/CDFV2',
            'application/x-ole-storage',
            'application/octet-stream',
        ], true);
    }

    if ($type === 'word' && $extension === 'docx') {
        $isZipDocument = str_starts_with($header, "PK\x03\x04");
        $isValid = $isZipDocument && in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ], true);

        if ($isValid && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $opened = $zip->open($tmpName) === true;
            $isValid = $opened
                && $zip->locateName('[Content_Types].xml') !== false
                && $zip->locateName('word/document.xml') !== false;

            if ($opened) {
                $zip->close();
            }
        }
    }

    if (!$isValid) {
        $errors[] = $type === 'pdf'
            ? 'PDF lessons must contain a genuine PDF document.'
            : 'Word lessons must contain a genuine DOC or DOCX document.';
        return null;
    }

    $directory = __DIR__ . '/../../../storage/private_uploads/course_resources';

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        $errors[] = 'The private lesson resource directory could not be created.';
        return null;
    }

    $fileName = bin2hex(random_bytes(24)) . '.' . $extension;
    $destination = $directory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'A lesson resource could not be saved.';
        return null;
    }

    @chmod($destination, 0640);
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

    $form['title'] = course_builder_clean_text($_POST['title'] ?? '');
    $form['category_id'] = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT, [
        'options' => ['default' => 0, 'min_range' => 0],
    ]);
    $form['price'] = course_builder_clean_text($_POST['price'] ?? '0');
    $form['level'] = course_builder_clean_text($_POST['level'] ?? 'beginner');
    $form['language'] = course_builder_clean_text($_POST['language'] ?? 'English');
    $form['short_description'] = course_builder_clean_text($_POST['short_description'] ?? '', true);
    $form['full_description'] = course_builder_clean_text($_POST['full_description'] ?? '', true);

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

    if ($form['title'] === '' || course_builder_length($form['title']) > 180) {
        $errors[] = 'Course title is required and must be 180 characters or fewer.';
    }

    if (!is_numeric($form['price']) || (float) $form['price'] < 0 || (float) $form['price'] > 9999999999.99) {
        $errors[] = 'Enter a valid non-negative course price.';
    }

    if (!in_array($form['level'], ['beginner', 'intermediate', 'advanced'], true)) {
        $errors[] = 'Choose a valid course level.';
    }

    if ($form['language'] === '' || course_builder_length($form['language']) > 60) {
        $errors[] = 'Language is required and must be 60 characters or fewer.';
    }

    if (course_builder_length($form['short_description']) > 500) {
        $errors[] = 'Short description must be 500 characters or fewer.';
    }


    if (course_builder_length($form['full_description']) > 10000) {
        $errors[] = 'Full description must be 10,000 characters or fewer.';
    }

    if (preg_match('/[<>]/', $form['title'] . $form['language'])) {
        $errors[] = 'Course title and language cannot contain HTML markup.';
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

    if ($isSubmitting && course_builder_length($form['title']) < 8) {
        $errors[] = 'Use at least 8 characters for the course title before submitting.';
    }

    if ($isSubmitting && course_builder_length($form['short_description']) < 30) {
        $errors[] = 'Write at least 30 characters in the short description before submitting.';
    }

    if ($isSubmitting && course_builder_length($form['full_description']) < 80) {
        $errors[] = 'Write at least 80 characters in the full description before submitting.';
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
    $sectionKeys = is_array($_POST['section_keys'] ?? null) ? array_values($_POST['section_keys']) : [];

    if (count($sectionKeys) > 50) {
        $errors[] = 'A course can contain at most 50 chapters.';
        $sectionKeys = array_slice($sectionKeys, 0, 50);
    }

    $seenSectionKeys = [];
    $allowedLessonTypes = ['text', 'video', 'link', 'pdf', 'word'];
    $totalLessonCount = 0;
    $totalDurationMinutes = 0;

    foreach ($sectionKeys as $rawSectionKey) {
        $sectionKey = course_builder_safe_key((string) $rawSectionKey);

        if ($sectionKey === '' || isset($seenSectionKeys[$sectionKey])) {
            continue;
        }

        $seenSectionKeys[$sectionKey] = true;
        $sectionTitle = course_builder_clean_text($_POST['section_title'][$sectionKey] ?? '');

        if (course_builder_length($sectionTitle) > 160) {
            $errors[] = 'Chapter titles must be 160 characters or fewer.';
        }
        $lessonKeys = $_POST['lesson_keys'][$sectionKey] ?? [];
        $lessonKeys = is_array($lessonKeys) ? array_values($lessonKeys) : [];

        if (count($lessonKeys) > 100) {
            $errors[] = 'A chapter can contain at most 100 lessons.';
            $lessonKeys = array_slice($lessonKeys, 0, 100);
        }

        $seenLessonKeys = [];
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

            if ($lessonKey === '' || isset($seenLessonKeys[$lessonKey])) {
                continue;
            }

            $seenLessonKeys[$lessonKey] = true;
            $lessonId = filter_var($_POST['lesson_id'][$sectionKey][$lessonKey] ?? 0, FILTER_VALIDATE_INT, [
                'options' => ['default' => 0, 'min_range' => 0],
            ]);
            $lessonTitle = course_builder_clean_text($_POST['lesson_title'][$sectionKey][$lessonKey] ?? '');
            $lessonType = course_builder_clean_text($_POST['lesson_type'][$sectionKey][$lessonKey] ?? 'text');
            $contentText = (string) ($_POST['lesson_content'][$sectionKey][$lessonKey] ?? '');
            $contentUrlInput = $_POST['lesson_url'][$sectionKey][$lessonKey] ?? '';
            $contentUrl = is_scalar($contentUrlInput) ? trim((string) $contentUrlInput) : '';
            $duration = max(0, min(1440, (int) ($_POST['lesson_duration'][$sectionKey][$lessonKey] ?? 0)));
            $isPreview = isset($_POST['lesson_preview'][$sectionKey][$lessonKey]);

            if (!in_array($lessonType, $allowedLessonTypes, true)) {
                $lessonType = 'text';
                $errors[] = 'A lesson contains an invalid content type.';
            }


            if (course_builder_length($lessonTitle) > 180) {
                $errors[] = 'Lesson titles must be 180 characters or fewer.';
            }

            if ($lessonId > 0 && !isset($existingLessons[$lessonId])) {
                $errors[] = 'A lesson reference does not belong to this course.';
                $lessonId = 0;
            }

            if ($totalLessonCount >= 300) {
                $errors[] = 'A course can contain at most 300 lessons.';
                break 2;
            }

            if ($isSubmitting && $lessonTitle === '') {
                $errors[] = 'Every lesson needs a title.';
            }

            $storedFile = null;
            $existingLesson = $lessonId > 0 && isset($existingLessons[$lessonId])
                ? $existingLessons[$lessonId]
                : null;

            if ($lessonType === 'text') {
                $contentText = course_builder_clean_rich_text($contentText);

                if (course_builder_length($contentText) > 100000) {
                    $errors[] = 'Text lessons must be 100,000 characters or fewer.';
                }

                if ($isSubmitting && trim(strip_tags($contentText)) === '') {
                    $errors[] = 'Text lessons must contain lesson content.';
                }

                $contentUrl = null;
            } elseif (in_array($lessonType, ['video', 'link'], true)) {
                $contentText = null;

                $normalizedUrl = course_builder_normalize_url($contentUrl);

                if ($normalizedUrl === null) {
                    $errors[] = 'Video and link lessons must use a valid HTTP or HTTPS URL without embedded credentials.';
                    $contentUrl = '';
                } else {
                    $contentUrl = $normalizedUrl;
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

$pageStyles = [
    'assets/css/navbars/instructor-navbar.css?v=1',
    'assets/css/pages/instructor/create-course.css?v=20',
];

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>

<link rel="stylesheet" href="assets/css/pages/instructor/create-course.css?v=4">


<main class="course-studio-page" data-published-edit="<?php echo $isPublishedEdit ? '1' : '0'; ?>">
    <section class="course-studio-shell">
        <header class="studio-hero">
            <div class="studio-hero-copy">
                <nav class="studio-breadcrumb" aria-label="Breadcrumb">
                    <a href="instructor-dashboard.php">Instructor</a>
                    <span aria-hidden="true">/</span>
                    <a href="instructor-courses.php">My courses</a>
                    <span aria-hidden="true">/</span>
                    <strong><?php echo $editingCourse ? 'Course studio' : 'New course'; ?></strong>
                </nav>
                <div class="studio-title-row">
                    <div>
                        <p class="studio-eyebrow">Course production workspace</p>
                        <h1><?php echo $isPublishedEdit ? 'Update course curriculum' : ($editingCourse ? 'Continue building your course' : 'Build a course students will trust'); ?></h1>
                    </div>
                    <span class="studio-status-badge <?php echo $editingCourse ? 'is-' . course_builder_h($editingCourse['status']) : 'is-new'; ?>">
                        <span></span>
                        <?php echo $editingCourse ? ucfirst(course_builder_h($editingCourse['status'])) : 'New course'; ?>
                    </span>
                </div>
                <p class="studio-intro">
                    Structure the offer, build the curriculum, and inspect the exact marketplace card before sending anything to admin.
                    Drafts stay private until you deliberately submit them.
                </p>
            </div>
            <div class="hero-actions">
                <a class="studio-button studio-button-ghost" href="instructor-courses.php">Back to courses</a>
                <?php if ($editingCourse): ?>
                    <a class="studio-button studio-button-dark" href="instructor-create-course.php">Create another</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="studio-alert studio-alert-<?php echo course_builder_h($messageType); ?>" role="alert">
                <span class="studio-alert-icon" aria-hidden="true"><?php echo $messageType === 'success' ? '✓' : '!'; ?></span>
                <p><?php echo course_builder_h($message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($draftCourses): ?>
            <details class="draft-drawer">
                <summary>
                    <span>
                        <strong>Saved drafts</strong>
                        <small>Resume another private or rejected course</small>
                    </span>
                    <span class="draft-count"><?php echo count($draftCourses); ?></span>
                </summary>
                <div class="draft-grid">
                    <?php foreach ($draftCourses as $draft): ?>
                        <a class="draft-tile" href="instructor-create-course.php?draft_id=<?php echo (int) $draft['id']; ?>">
                            <span class="draft-tile-icon" aria-hidden="true">✦</span>
                            <span>
                                <strong><?php echo course_builder_h($draft['title']); ?></strong>
                                <small>
                                    <?php echo $draft['status'] === 'rejected' ? 'Needs revision' : 'Private draft'; ?>
                                    · <?php echo course_builder_h(date('M j, Y', strtotime($draft['updated_at']))); ?>
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>

        <div class="studio-workspace">
            <aside class="studio-rail" aria-label="Course builder navigation">
                <div class="rail-card">
                    <p class="rail-label">Build sequence</p>
                    <a class="rail-step is-active" href="#course-basics" data-step-link="basics">
                        <span>01</span>
                        <div><strong>Course offer</strong><small>Title, positioning and price</small></div>
                    </a>
                    <a class="rail-step" href="#course-curriculum" data-step-link="curriculum">
                        <span>02</span>
                        <div><strong>Curriculum</strong><small>Chapters and lessons</small></div>
                    </a>
                    <a class="rail-step" href="#course-review" data-step-link="review">
                        <span>03</span>
                        <div><strong>Review</strong><small>Quality and submission</small></div>
                    </a>
                </div>

                <div class="rail-card quality-card">
                    <div class="quality-heading">
                        <span>Course readiness</span>
                        <strong id="qualityScore">0%</strong>
                    </div>
                    <div class="quality-track" aria-hidden="true"><span id="qualityBar"></span></div>
                    <p id="qualityMessage">Start with a clear title and course thumbnail.</p>
                </div>

                <div class="security-note">
                    <span aria-hidden="true">🔒</span>
                    <div>
                        <strong>Protected workflow</strong>
                        <p>CSRF checks, ownership checks, prepared queries, strict file inspection and server-side validation are applied before saving.</p>
                    </div>
                </div>
            </aside>

            <form method="post" enctype="multipart/form-data" id="courseBuilderForm" class="studio-form" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="draft_id" value="<?php echo (int) $draftId; ?>">

                <section class="studio-panel" id="course-basics" data-studio-section="basics">
                    <div class="panel-heading">
                        <div class="panel-heading-icon" aria-hidden="true">01</div>
                        <div>
                            <p class="panel-kicker">Marketplace offer</p>
                            <h2>Design the course students discover</h2>
                            <p>This information powers public cards, search results and the course detail page.</p>
                        </div>
                    </div>

                    <?php if ($isPublishedEdit): ?>
                        <div class="locked-banner">
                            <span aria-hidden="true">🔐</span>
                            <p><strong>Published details are locked.</strong> Curriculum changes can still be submitted to admin for approval.</p>
                        </div>
                    <?php endif; ?>

                    <fieldset <?php echo $isPublishedEdit ? 'disabled' : ''; ?> class="offer-fields">
                        <div class="field-group field-span-2" data-field="title">
                            <div class="field-label-row">
                                <label for="title">Course title <span>*</span></label>
                                <span class="character-counter" data-counter-for="title">0 / 180</span>
                            </div>
                            <input id="title" name="title" maxlength="180" required autocomplete="off"
                                   value="<?php echo course_builder_h($form['title']); ?>"
                                   placeholder="Example: Practical Web Security from HTTP to Exploitation">
                            <p class="field-help">Be specific about the result, skill or transformation students will receive.</p>
                            <p class="field-error" data-error-for="title"></p>
                        </div>

                        <div class="field-group">
                            <label for="category_id">Category <span>*</span></label>
                            <select id="category_id" name="category_id" required>
                                <option value="0">Select the best category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int) $category['id']; ?>"
                                            data-category-name="<?php echo course_builder_h($category['name']); ?>"
                                        <?php echo (int) $form['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo course_builder_h($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-error" data-error-for="category_id"></p>
                        </div>

                        <div class="field-group">
                            <label for="price">Price in NPR <span>*</span></label>
                            <div class="money-input"><span>Rs.</span><input id="price" name="price" type="number" min="0" max="9999999999.99" step="0.01" required inputmode="decimal" value="<?php echo course_builder_h($form['price']); ?>"></div>
                            <p class="field-help">Use 0 for a free course.</p>
                            <p class="field-error" data-error-for="price"></p>
                        </div>

                        <div class="field-group">
                            <label for="level">Learning level</label>
                            <select id="level" name="level">
                                <?php foreach (['beginner', 'intermediate', 'advanced'] as $level): ?>
                                    <option value="<?php echo $level; ?>" <?php echo $form['level'] === $level ? 'selected' : ''; ?>><?php echo ucfirst($level); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-group">
                            <div class="field-label-row">
                                <label for="language">Language <span>*</span></label>
                                <span class="character-counter" data-counter-for="language">0 / 60</span>
                            </div>
                            <input id="language" name="language" maxlength="60" required autocomplete="off" value="<?php echo course_builder_h($form['language']); ?>" placeholder="English, Nepali, Hindi...">
                            <p class="field-error" data-error-for="language"></p>
                        </div>

                        <div class="field-group field-span-2">
                            <div class="field-label-row">
                                <label for="short_description">Course-card summary <span>*</span></label>
                                <span class="character-counter" data-counter-for="short_description">0 / 500</span>
                            </div>
                            <textarea id="short_description" name="short_description" rows="4" maxlength="500" required placeholder="Explain the course value in two or three direct sentences."><?php echo course_builder_h($form['short_description']); ?></textarea>
                            <p class="field-help">This is the first explanation students see in search and course cards.</p>
                            <p class="field-error" data-error-for="short_description"></p>
                        </div>

                        <div class="field-group field-span-2">
                            <div class="field-label-row">
                                <label for="full_description">Full course description <span>*</span></label>
                                <span class="character-counter" data-counter-for="full_description">0 / 10000</span>
                            </div>
                            <textarea id="full_description" name="full_description" rows="9" maxlength="10000" required placeholder="Describe learning outcomes, audience, prerequisites, teaching approach and what is included."><?php echo course_builder_h($form['full_description']); ?></textarea>
                            <p class="field-help">Plain text is stored here. HTML and executable markup are removed server-side.</p>
                            <p class="field-error" data-error-for="full_description"></p>
                        </div>

                        <div class="field-group field-span-2">
                            <label for="thumbnail">Course thumbnail <span>*</span></label>
                            <label class="thumbnail-dropzone" for="thumbnail" id="thumbnailDropzone">
                                <input id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp" <?php echo $form['thumbnail'] === '' ? 'required' : ''; ?>>
                                <span class="dropzone-art" aria-hidden="true">⬆</span>
                                <span class="dropzone-copy">
                                    <strong>Drop a course cover here or browse</strong>
                                    <small>JPG, PNG or WebP · maximum 2 MB · landscape format recommended</small>
                                </span>
                                <span class="dropzone-action">Choose image</span>
                            </label>
                            <p class="field-error" data-error-for="thumbnail"></p>
                        </div>
                    </fieldset>
                </section>

                <section class="studio-panel" id="course-curriculum" data-studio-section="curriculum">
                    <div class="panel-heading curriculum-heading">
                        <div class="panel-heading-icon" aria-hidden="true">02</div>
                        <div>
                            <p class="panel-kicker">Learning architecture</p>
                            <h2>Build a curriculum, not a file dump</h2>
                            <p>Group lessons into chapters and control the exact order students follow.</p>
                        </div>
                        <button type="button" class="studio-button studio-button-primary" id="addChapter">+ Add chapter</button>
                    </div>

                    <div class="curriculum-toolbar">
                        <div>
                            <strong id="curriculumSummary">0 chapters · 0 lessons · 0 minutes</strong>
                            <small>Use the arrows to reorder content. Changes are saved in the displayed order.</small>
                        </div>
                        <button type="button" class="text-action" id="expandAllChapters">Expand all</button>
                    </div>

                    <div class="chapter-list" id="chapterList">
                        <?php foreach ($builderSections as $sectionIndex => $section): ?>
                            <article class="chapter-card" data-section-key="<?php echo course_builder_h($section['key']); ?>">
                                <input type="hidden" name="section_keys[]" value="<?php echo course_builder_h($section['key']); ?>">
                                <header class="chapter-header">
                                    <button type="button" class="drag-indicator" aria-label="Chapter position" tabindex="-1">⋮⋮</button>
                                    <span class="chapter-number"><?php echo str_pad((string) ($sectionIndex + 1), 2, '0', STR_PAD_LEFT); ?></span>
                                    <div class="chapter-title-field">
                                        <label>Chapter title</label>
                                        <input name="section_title[<?php echo course_builder_h($section['key']); ?>]" maxlength="160" value="<?php echo course_builder_h($section['title']); ?>" placeholder="Example: Understanding the HTTP request lifecycle">
                                    </div>
                                    <div class="chapter-header-actions">
                                        <button type="button" class="icon-action move-chapter-up" aria-label="Move chapter up">↑</button>
                                        <button type="button" class="icon-action move-chapter-down" aria-label="Move chapter down">↓</button>
                                        <button type="button" class="icon-action toggle-chapter" aria-label="Collapse chapter" aria-expanded="true">⌃</button>
                                        <button type="button" class="icon-action danger remove-chapter" aria-label="Remove chapter">×</button>
                                    </div>
                                </header>
                                <div class="chapter-body">
                                    <div class="lessons-list">
                                        <?php foreach ($section['lessons'] as $lessonIndex => $lesson): ?>
                                            <article class="lesson-card" data-lesson-key="<?php echo course_builder_h($lesson['key']); ?>">
                                                <input type="hidden" name="lesson_keys[<?php echo course_builder_h($section['key']); ?>][]" value="<?php echo course_builder_h($lesson['key']); ?>">
                                                <input type="hidden" name="lesson_id[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" value="<?php echo (int) $lesson['id']; ?>">
                                                <div class="lesson-order">L<?php echo $lessonIndex + 1; ?></div>
                                                <div class="lesson-editor">
                                                    <div class="lesson-primary-row">
                                                        <div class="lesson-title-wrap">
                                                            <label>Lesson title</label>
                                                            <input name="lesson_title[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" maxlength="180" value="<?php echo course_builder_h($lesson['title']); ?>" placeholder="Describe this lesson clearly">
                                                        </div>
                                                        <div class="lesson-type-wrap">
                                                            <label>Content type</label>
                                                            <select class="lesson-type" name="lesson_type[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]">
                                                                <?php foreach (['text' => 'Text lesson', 'video' => 'Video URL', 'link' => 'External link', 'pdf' => 'PDF document', 'word' => 'Word document'] as $type => $label): ?>
                                                                    <option value="<?php echo $type; ?>" <?php echo $lesson['type'] === $type ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="lesson-content-panels">
                                                        <div class="lesson-content-field text-field">
                                                            <label>Lesson content</label>
                                                            <textarea name="lesson_content[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" rows="7" placeholder="Write the learning material. Safe headings, paragraphs and lists are supported."><?php echo course_builder_h($lesson['content_text']); ?></textarea>
                                                        </div>
                                                        <div class="lesson-content-field url-field">
                                                            <label>Secure HTTP(S) URL</label>
                                                            <input type="url" maxlength="2048" name="lesson_url[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" value="<?php echo in_array($lesson['type'], ['video', 'link'], true) ? course_builder_h($lesson['content_url']) : ''; ?>" placeholder="https://example.com/resource">
                                                        </div>
                                                        <div class="lesson-content-field file-field">
                                                            <label>Private lesson document</label>
                                                            <?php if (in_array($lesson['type'], ['pdf', 'word'], true) && $lesson['content_url'] !== ''): ?>
                                                                <p class="resource-current">Stored privately: <?php echo course_builder_h(basename($lesson['content_url'])); ?></p>
                                                            <?php endif; ?>
                                                            <input type="file" name="lesson_file_<?php echo course_builder_h($section['key']); ?>_<?php echo course_builder_h($lesson['key']); ?>">
                                                            <small>Documents are stored outside the public web root and served only after authorization.</small>
                                                        </div>
                                                    </div>
                                                    <div class="lesson-meta-row">
                                                        <label class="duration-field"><span>Duration</span><input type="number" min="0" max="1440" name="lesson_duration[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" value="<?php echo (int) $lesson['duration']; ?>"><small>minutes</small></label>
                                                        <label class="preview-toggle"><input type="checkbox" name="lesson_preview[<?php echo course_builder_h($section['key']); ?>][<?php echo course_builder_h($lesson['key']); ?>]" value="1" <?php echo $lesson['is_preview'] ? 'checked' : ''; ?>><span></span><strong>Free preview</strong></label>
                                                        <div class="lesson-actions">
                                                            <button type="button" class="icon-action move-lesson-up" aria-label="Move lesson up">↑</button>
                                                            <button type="button" class="icon-action move-lesson-down" aria-label="Move lesson down">↓</button>
                                                            <button type="button" class="text-action danger remove-lesson">Remove lesson</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="add-lesson-button add-lesson">+ Add another lesson to this chapter</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="curriculum-empty" id="emptyBuilder" <?php echo $builderSections ? 'hidden' : ''; ?>>
                        <span class="empty-illustration" aria-hidden="true">▦</span>
                        <h3>Your curriculum starts with one chapter</h3>
                        <p>Create a chapter, then add text, video, link, PDF or Word lessons in the order students should learn them.</p>
                        <button type="button" class="studio-button studio-button-primary" id="emptyAddChapter">Create first chapter</button>
                    </div>
                </section>

                <section class="studio-panel review-panel" id="course-review" data-studio-section="review">
                    <div class="panel-heading">
                        <div class="panel-heading-icon" aria-hidden="true">03</div>
                        <div>
                            <p class="panel-kicker">Final quality gate</p>
                            <h2>Review before admin sees it</h2>
                            <p>Draft saving is flexible. Admin submission requires a complete, valid course.</p>
                        </div>
                    </div>
                    <div class="review-grid">
                        <div class="review-checklist" id="reviewChecklist">
                            <div data-check="title"><span>○</span><p><strong>Clear course title</strong><small>At least 8 useful characters</small></p></div>
                            <div data-check="category"><span>○</span><p><strong>Correct category</strong><small>Helps search and discovery</small></p></div>
                            <div data-check="description"><span>○</span><p><strong>Useful descriptions</strong><small>Short summary and detailed explanation</small></p></div>
                            <div data-check="thumbnail"><span>○</span><p><strong>Course thumbnail</strong><small>Valid JPG, PNG or WebP</small></p></div>
                            <div data-check="curriculum"><span>○</span><p><strong>Learning curriculum</strong><small>At least one chapter and lesson</small></p></div>
                        </div>
                        <div class="submission-explainer">
                            <span class="submission-icon" aria-hidden="true">⌁</span>
                            <div>
                                <h3><?php echo $isPublishedEdit ? 'Submit curriculum changes' : 'What happens after submission?'; ?></h3>
                                <p>Admin receives a pending version for review. The course is not publicly visible until it is approved. Rejected courses return with a review note and remain editable.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <footer class="studio-submit-bar">
                    <div class="submit-state">
                        <span class="save-indicator" aria-hidden="true"></span>
                        <div><strong id="submitStateTitle">Ready to continue</strong><small id="submitStateText">Save privately or submit when the checklist is complete.</small></div>
                    </div>
                    <div class="submit-actions">
                        <?php if (!$isPublishedEdit): ?>
                            <button type="submit" class="studio-button studio-button-ghost" name="course_action" value="save_draft">Save private draft</button>
                        <?php endif; ?>
                        <button type="submit" class="studio-button studio-button-submit" name="course_action" value="submit_review" id="submitReview"><?php echo $isPublishedEdit ? 'Submit curriculum changes' : 'Submit for admin review'; ?><span aria-hidden="true">→</span></button>
                    </div>
                </footer>
            </form>

            <aside class="preview-column" aria-label="Live course preview">
                <div class="preview-sticky">
                    <div class="preview-heading">
                        <div><p>Live marketplace preview</p><h2>Student view</h2></div>
                        <span>Live</span>
                    </div>

                    <article class="marketplace-card" id="liveCourseCard">
                        <div class="marketplace-cover">
                            <?php if ($form['thumbnail'] !== ''): ?>
                                <img id="previewImage" src="assets/uploads/course_thumbnails/<?php echo rawurlencode(basename($form['thumbnail'])); ?>" alt="Course thumbnail preview">
                            <?php else: ?>
                                <img id="previewImage" src="assets/images/course-placeholder.svg" alt="Course thumbnail preview">
                            <?php endif; ?>
                            <span class="preview-category" id="previewCategory">Uncategorized</span>
                            <span class="preview-level" id="previewLevel"><?php echo course_builder_h(ucfirst($form['level'])); ?></span>
                        </div>
                        <div class="marketplace-content">
                            <div class="preview-meta"><span id="previewLanguage"><?php echo course_builder_h($form['language'] ?: 'Language'); ?></span><span>•</span><span id="previewDuration">0 min</span></div>
                            <h3 id="previewTitle"><?php echo course_builder_h($form['title'] ?: 'Your course title appears here'); ?></h3>
                            <p id="previewDescription"><?php echo course_builder_h($form['short_description'] ?: 'Write a concise promise explaining what students will learn and why it matters.'); ?></p>
                            <div class="preview-rating"><span>★ ★ ★ ★ ★</span><small>New course</small></div>
                            <div class="preview-price-row"><strong id="previewPrice">Rs. <?php echo course_builder_h(number_format((float) $form['price'], 0)); ?></strong><button type="button" tabindex="-1">View course</button></div>
                        </div>
                    </article>

                    <div class="outline-preview-card">
                        <div class="outline-heading"><div><p>Curriculum map</p><h3>What students will see</h3></div><span id="outlineCount">0 lessons</span></div>
                        <div class="outline-list" id="previewOutline"><p class="outline-empty">Add chapters to build the course outline.</p></div>
                    </div>

                    <p class="preview-disclaimer">This preview is generated locally from your form. The server validates and sanitizes every submitted value again.</p>
                </div>
            </aside>
        </div>
    </section>
</main>

<script src="assets/js/instructor_create_course.js?v=4" defer></script>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>