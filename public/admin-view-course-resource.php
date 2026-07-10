<?php

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

$lessonId = isset($_GET['lesson_id']) ? (int) $_GET['lesson_id'] : 0;

if ($lessonId <= 0) {
    http_response_code(400);
    exit('Invalid request: lesson id missing.');
}

$sql = "
    SELECT 
        l.id,
        l.title,
        l.content_type,
        l.content_url
    FROM course_lessons l
    INNER JOIN course_sections s ON l.section_id = s.id
    INNER JOIN courses c ON s.course_id = c.id
    WHERE l.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit('Database query error.');
}

$stmt->bind_param("i", $lessonId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    http_response_code(404);
    exit('Lesson resource not found.');
}

$lesson = $result->fetch_assoc();
$stmt->close();

$contentUrl = $lesson['content_url'] ?? '';
$contentType = $lesson['content_type'] ?? '';

if ($contentUrl === '') {
    http_response_code(404);
    exit('Resource file is empty.');
}

if (($contentType === 'link' || $contentType === 'video') && preg_match('/^https?:\/\//i', $contentUrl)) {
    header("Location: " . $contentUrl);
    exit;
}

$fileName = basename($contentUrl);

$possibleFolders = [
    __DIR__ . '/../storage/private_uploads/course_resources/',
    __DIR__ . '/assets/uploads/course_resources/',
    __DIR__ . '/assets/uploads/lesson_resources/',
    __DIR__ . '/assets/uploads/course_materials/'
];

$filePath = Security::resolveStoredFile($fileName, $possibleFolders);

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('Resource file not found: ' . htmlspecialchars($fileName));
}

$mimeType = mime_content_type($filePath);

$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(403);
    exit('Unsupported resource type: ' . htmlspecialchars($mimeType));
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
header('Cache-Control: private, no-store, max-age=0');

readfile($filePath);
exit;
