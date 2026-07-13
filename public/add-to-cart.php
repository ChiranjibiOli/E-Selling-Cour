<?php

declare(strict_types=1);

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();
Security::requirePost();

/** @var mysqli $conn */
$conn = database_connection();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);

if ($courseId <= 0) {
    Auth::redirect('student-browse-courses.php?invalid=1');
}

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $courseStmt = $conn->prepare("
        SELECT c.id
        FROM courses c
        INNER JOIN users instructor ON instructor.id = c.instructor_id
        INNER JOIN categories category ON category.id = c.category_id
        WHERE c.id = ?
          AND c.status = 'published'
          AND instructor.role = 'instructor'
          AND instructor.status = 'active'
          AND category.status = 'active'
        LIMIT 1
        FOR UPDATE
    ");
    $courseStmt->bind_param('i', $courseId);
    $courseStmt->execute();
    $courseExists = $courseStmt->get_result()->num_rows === 1;
    $courseStmt->close();

    if (!$courseExists) {
        throw new DomainException('Course not found.');
    }

    $enrollStmt = $conn->prepare("
        SELECT id
        FROM enrollments
        WHERE student_id = ?
          AND course_id = ?
          AND status = 'active'
        LIMIT 1
        FOR UPDATE
    ");
    $enrollStmt->bind_param('ii', $studentId, $courseId);
    $enrollStmt->execute();
    $alreadyEnrolled = $enrollStmt->get_result()->num_rows > 0;
    $enrollStmt->close();

    if ($alreadyEnrolled) {
        $conn->commit();
        $transactionStarted = false;
        Auth::redirect('student-course-view.php?course_id=' . $courseId);
    }

    $pendingStmt = $conn->prepare("
        SELECT o.id
        FROM orders o
        INNER JOIN order_items oi ON oi.order_id = o.id
        WHERE o.student_id = ?
          AND oi.course_id = ?
          AND o.order_status = 'pending'
        LIMIT 1
        FOR UPDATE
    ");
    $pendingStmt->bind_param('ii', $studentId, $courseId);
    $pendingStmt->execute();
    $hasPendingOrder = $pendingStmt->get_result()->num_rows > 0;
    $pendingStmt->close();

    if ($hasPendingOrder) {
        $conn->commit();
        $transactionStarted = false;
        Auth::redirect('student-my-courses.php?payment_pending=1');
    }

    $insertStmt = $conn->prepare('INSERT IGNORE INTO cart (student_id, course_id) VALUES (?, ?)');
    $insertStmt->bind_param('ii', $studentId, $courseId);
    $insertStmt->execute();
    $insertStmt->close();

    $conn->commit();
    $transactionStarted = false;
    Auth::redirect('cart.php?added=1');
} catch (DomainException $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    Auth::redirect('student-browse-courses.php?notfound=1');
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Add-to-cart failed: ' . $exception->getMessage());
    Auth::redirect('student-browse-courses.php?cart_error=1');
}
