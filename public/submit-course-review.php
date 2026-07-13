<?php

declare(strict_types=1);

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();
Security::requirePost();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @var mysqli $conn */
$conn = database_connection();
$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$feedback = trim((string) ($_POST['feedback'] ?? ''));

function review_submit_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($courseId <= 0 || $rating < 1 || $rating > 5) {
    review_submit_response(['ok' => false, 'message' => 'Choose a rating from 1 to 5 stars.'], 422);
}

$feedbackLength = function_exists('mb_strlen')
    ? mb_strlen($feedback, 'UTF-8')
    : strlen($feedback);

if ($feedbackLength < 10 || $feedbackLength > 2000) {
    review_submit_response([
        'ok' => false,
        'message' => 'Feedback must contain between 10 and 2000 characters.',
    ], 422);
}

$cleanFeedback = strip_tags($feedback);
$cleanFeedback = str_replace("\0", '', $cleanFeedback);
$cleanFeedback = trim((string) preg_replace('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $cleanFeedback));

$eligibilityStmt = $conn->prepare("
    SELECT e.id
    FROM enrollments e
    INNER JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = ?
      AND e.course_id = ?
      AND e.status = 'active'
      AND c.status = 'published'
    LIMIT 1
");
$eligibilityStmt->bind_param('ii', $studentId, $courseId);
$eligibilityStmt->execute();
$eligible = $eligibilityStmt->get_result()->num_rows === 1;
$eligibilityStmt->close();

if (!$eligible) {
    review_submit_response([
        'ok' => false,
        'message' => 'Only students with active access to this course can review it.',
    ], 403);
}

try {
    $stmt = $conn->prepare("
        INSERT INTO reviews (course_id, student_id, rating, review_text, status)
        VALUES (?, ?, ?, ?, 'visible')
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            review_text = VALUES(review_text),
            status = 'visible',
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('iiis', $courseId, $studentId, $rating, $cleanFeedback);
    $stmt->execute();
    $stmt->close();

    review_submit_response([
        'ok' => true,
        'message' => 'Your rating and feedback were saved.',
    ]);
} catch (Throwable $exception) {
    error_log('Course review submission failed: ' . $exception->getMessage());
    review_submit_response([
        'ok' => false,
        'message' => 'The review could not be saved right now.',
    ], 500);
}
