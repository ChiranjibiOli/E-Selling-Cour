<?php

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$lessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($lessonId <= 0) {
    http_response_code(400);
    exit('Invalid lesson.');
}

$sql = "
    SELECT 
        l.id,
        l.title,
        l.content_type,
        l.content_url,
        s.course_id
    FROM course_lessons l
    INNER JOIN course_sections s ON l.section_id = s.id
    INNER JOIN enrollments e ON e.course_id = s.course_id
    WHERE l.id = ?
      AND e.student_id = ?
      AND e.status = 'active'
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $lessonId, $studentId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    http_response_code(403);
    exit('Access denied.');
}

$lesson = $result->fetch_assoc();
$stmt->close();

$contentType = $lesson['content_type'] ?? '';
$contentUrl = $lesson['content_url'] ?? '';

if ($contentUrl === '') {
    http_response_code(404);
    exit('Resource missing.');
}

if ($contentType !== 'pdf') {
    http_response_code(403);
    exit('Only protected PDF preview is allowed.');
}

$fileName = basename($contentUrl);

$possibleFolders = [
    __DIR__ . '/../storage/private_uploads/course_resources/',
    __DIR__ . '/assets/uploads/course_resources/',
    __DIR__ . '/assets/uploads/lesson_resources/'
];

$filePath = Security::resolveStoredFile($fileName, $possibleFolders);

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('Resource file not found.');
}

$mimeType = mime_content_type($filePath);

if ($mimeType !== 'application/pdf') {
    http_response_code(403);
    exit('Only PDF preview is allowed.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="protected-course-resource.pdf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');

readfile($filePath);
exit;
