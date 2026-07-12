<?php

declare(strict_types=1);

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';
require_once '../app/helpers/security_helper.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$lessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($lessonId <= 0) {
    http_response_code(400);
    exit('Invalid lesson link request.');
}

$stmt = $conn->prepare("
    SELECT l.content_url
    FROM course_lessons l
    INNER JOIN course_sections s ON s.id = l.section_id
    INNER JOIN enrollments e ON e.course_id = s.course_id
    WHERE l.id = ?
      AND l.content_type IN ('link', 'video')
      AND e.student_id = ?
      AND e.status = 'active'
    LIMIT 1
");
$stmt->bind_param('ii', $lessonId, $studentId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

$safeUrl = security_safe_external_url((string) ($row['content_url'] ?? ''));
if ($safeUrl === null || $safeUrl === '') {
    http_response_code(403);
    exit('This external lesson link is not allowed.');
}

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('Location: ' . $safeUrl, true, 302);
exit;
