<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);
$courseId = (int) ($_GET['id'] ?? 0);

if ($courseId <= 0) {
    Auth::redirect('instructor-courses.php');
}

$stmt = $conn->prepare('SELECT status FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
$stmt->bind_param('ii', $courseId, $instructorId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

if ($course['status'] === 'pending') {
    Auth::redirect('instructor-courses.php?status=pending');
}

Auth::redirect('instructor-create-course.php?draft_id=' . $courseId);
