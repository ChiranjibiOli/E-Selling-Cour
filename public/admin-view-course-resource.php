<?php

declare(strict_types=1);

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';
require_once '../app/helpers/security_helper.php';

AdminMiddleware::handle();

$lessonId = (int) ($_GET['lesson_id'] ?? 0);
if ($lessonId <= 0) {
    http_response_code(400);
    exit('Invalid lesson resource request.');
}

$stmt = $conn->prepare("
    SELECT l.id, l.title, l.content_type, l.content_url
    FROM course_lessons l
    INNER JOIN course_sections s ON l.section_id = s.id
    INNER JOIN courses c ON s.course_id = c.id
    WHERE l.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $lessonId);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$lesson) {
    http_response_code(404);
    exit('Lesson resource not found.');
}

$contentUrl = (string) ($lesson['content_url'] ?? '');
$contentType = (string) ($lesson['content_type'] ?? '');

if ($contentUrl === '') {
    http_response_code(404);
    exit('Lesson resource not found.');
}

if (in_array($contentType, ['link', 'video'], true)) {
    $safeUrl = security_safe_external_url($contentUrl);
    if ($safeUrl === null || $safeUrl === '') {
        http_response_code(403);
        exit('The external lesson URL is not allowed.');
    }

    header('Referrer-Policy: no-referrer');
    header('Location: ' . $safeUrl, true, 302);
    exit;
}

$filePath = Security::resolveStoredFile($contentUrl, [
    __DIR__ . '/../storage/private_uploads/course_resources',
    __DIR__ . '/assets/uploads/course_resources',
    __DIR__ . '/assets/uploads/lesson_resources',
    __DIR__ . '/assets/uploads/course_materials',
]);

if ($filePath === null) {
    http_response_code(404);
    exit('Lesson resource not found.');
}

$mimeType = Security::detectMimeType($filePath);
$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(403);
    exit('Unsupported lesson resource type.');
}

$downloadName = Security::safeDownloadName($contentUrl, 'course-resource');
$disposition = $mimeType === 'application/pdf' ? 'inline' : 'attachment';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
