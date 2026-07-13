<?php

declare(strict_types=1);

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';
require_once '../app/helpers/free_enrollment_helper.php';

StudentMiddleware::handle();
Security::requirePost();

/** @var mysqli $conn */
$conn = database_connection();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);
$slug = trim((string) ($_POST['slug'] ?? ''));

if ($studentId <= 0) {
    Auth::redirect('login.php');
}

if ($courseId <= 0 && $slug !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1) {
    $lookupStmt = $conn->prepare("
        SELECT c.id
        FROM courses c
        INNER JOIN users instructor ON instructor.id = c.instructor_id
        INNER JOIN categories category ON category.id = c.category_id
        WHERE c.slug = ?
          AND c.status = 'published'
          AND c.price <= 0
          AND instructor.role = 'instructor'
          AND instructor.status = 'active'
          AND category.status = 'active'
        LIMIT 1
    ");
    $lookupStmt->bind_param('s', $slug);
    $lookupStmt->execute();
    $row = $lookupStmt->get_result()->fetch_assoc() ?: null;
    $lookupStmt->close();
    $courseId = (int) ($row['id'] ?? 0);
}

if ($courseId <= 0) {
    Auth::redirect('student-browse-courses.php?invalid=1');
}

try {
    enroll_student_in_free_course($conn, $studentId, $courseId);
    Auth::redirect('student-course-view.php?course_id=' . $courseId . '&free_enrolled=1');
} catch (DomainException | InvalidArgumentException $exception) {
    Auth::redirect('student-browse-courses.php?notfound=1');
} catch (Throwable $exception) {
    error_log('Free course enrollment failed: ' . $exception->getMessage());
    Auth::redirect('student-browse-courses.php?free_error=1');
}
