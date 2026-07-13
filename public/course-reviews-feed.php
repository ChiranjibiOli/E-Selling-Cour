<?php

declare(strict_types=1);

require_once '../app/core/Auth.php';
require_once '../app/config/database.php';

Auth::start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @var mysqli $conn */
$conn = database_connection();
$currentUser = Auth::user();
$viewerId = (int) ($currentUser['id'] ?? 0);
$viewerRole = (string) ($currentUser['role'] ?? 'guest');
$courseId = (int) ($_GET['course_id'] ?? 0);
$slug = trim((string) ($_GET['slug'] ?? ''));

function review_feed_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($courseId <= 0 && ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1)) {
    review_feed_response(['ok' => false, 'message' => 'Invalid course.'], 422);
}

if ($courseId > 0) {
    $courseStmt = $conn->prepare('SELECT id, slug, status, instructor_id FROM courses WHERE id = ? LIMIT 1');
    $courseStmt->bind_param('i', $courseId);
} else {
    $courseStmt = $conn->prepare('SELECT id, slug, status, instructor_id FROM courses WHERE slug = ? LIMIT 1');
    $courseStmt->bind_param('s', $slug);
}

$courseStmt->execute();
$course = $courseStmt->get_result()->fetch_assoc() ?: null;
$courseStmt->close();

if (!$course) {
    review_feed_response(['ok' => false, 'message' => 'Course not found.'], 404);
}

$courseId = (int) $course['id'];
$isOwner = $viewerRole === 'instructor' && (int) $course['instructor_id'] === $viewerId;
$isAdmin = $viewerRole === 'admin';

if ((string) $course['status'] !== 'published' && !$isOwner && !$isAdmin) {
    review_feed_response(['ok' => false, 'message' => 'Course reviews are unavailable.'], 404);
}

$summaryStmt = $conn->prepare("
    SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
    FROM reviews
    WHERE course_id = ? AND status = 'visible'
");
$summaryStmt->bind_param('i', $courseId);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: ['review_count' => 0, 'average_rating' => 0];
$summaryStmt->close();

$reviewStmt = $conn->prepare("
    SELECT r.rating, r.review_text, r.updated_at, u.full_name
    FROM reviews r
    INNER JOIN users u ON u.id = r.student_id
    WHERE r.course_id = ?
      AND r.status = 'visible'
      AND u.role = 'student'
    ORDER BY r.updated_at DESC, r.id DESC
    LIMIT 20
");
$reviewStmt->bind_param('i', $courseId);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();
$reviews = [];

while ($row = $reviewResult->fetch_assoc()) {
    $reviews[] = [
        'name' => (string) $row['full_name'],
        'rating' => (int) $row['rating'],
        'feedback' => (string) ($row['review_text'] ?? ''),
        'updated_at' => date('M j, Y', strtotime((string) $row['updated_at'])),
    ];
}
$reviewStmt->close();

$canReview = false;
$currentReview = null;

if ($viewerRole === 'student' && $viewerId > 0) {
    $eligibilityStmt = $conn->prepare("
        SELECT e.id
        FROM enrollments e
        WHERE e.student_id = ? AND e.course_id = ? AND e.status = 'active'
        LIMIT 1
    ");
    $eligibilityStmt->bind_param('ii', $viewerId, $courseId);
    $eligibilityStmt->execute();
    $canReview = $eligibilityStmt->get_result()->num_rows === 1;
    $eligibilityStmt->close();

    if ($canReview) {
        $currentStmt = $conn->prepare("
            SELECT rating, review_text
            FROM reviews
            WHERE course_id = ? AND student_id = ?
            LIMIT 1
        ");
        $currentStmt->bind_param('ii', $courseId, $viewerId);
        $currentStmt->execute();
        $row = $currentStmt->get_result()->fetch_assoc();
        $currentStmt->close();

        if ($row) {
            $currentReview = [
                'rating' => (int) $row['rating'],
                'feedback' => (string) ($row['review_text'] ?? ''),
            ];
        }
    }
}

review_feed_response([
    'ok' => true,
    'course_id' => $courseId,
    'average_rating' => round((float) $summary['average_rating'], 1),
    'review_count' => (int) $summary['review_count'],
    'reviews' => $reviews,
    'can_review' => $canReview,
    'current_review' => $currentReview,
    'csrf_token' => $canReview ? Security::token() : null,
]);
