<?php

declare(strict_types=1);

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$lessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($lessonId <= 0) {
    http_response_code(400);
    exit('Invalid lesson resource request.');
}

$stmt = $conn->prepare("
    SELECT l.id, l.title, l.content_type, l.content_url, s.course_id
    FROM course_lessons l
    INNER JOIN course_sections s ON l.section_id = s.id
    INNER JOIN enrollments e ON e.course_id = s.course_id
    WHERE l.id = ?
      AND e.student_id = ?
      AND e.status = 'active'
    LIMIT 1
");
$stmt->bind_param('ii', $lessonId, $studentId);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$lesson) {
    http_response_code(404);
    exit('Lesson resource not found.');
}

if ((string) ($lesson['content_type'] ?? '') !== 'pdf') {
    http_response_code(403);
    exit('Only protected PDF preview is allowed.');
}

$filePath = Security::resolveStoredFile((string) ($lesson['content_url'] ?? ''), [
    __DIR__ . '/../storage/private_uploads/course_resources',
    __DIR__ . '/assets/uploads/course_resources',
    __DIR__ . '/assets/uploads/lesson_resources',
]);

if ($filePath === null || Security::detectMimeType($filePath) !== 'application/pdf') {
    http_response_code(404);
    exit('Protected PDF resource not found.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="protected-course-resource.pdf"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
readfile($filePath);
exit;
