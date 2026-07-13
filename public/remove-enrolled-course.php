<?php

declare(strict_types=1);

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';
require_once '../app/helpers/notification_helper.php';

StudentMiddleware::handle();
Security::requirePost();

/** @var mysqli $conn */
$conn = database_connection();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);

if ($studentId <= 0 || $enrollmentId <= 0) {
    Auth::redirect('student-my-courses.php?remove_error=1');
}

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $lookupStmt = $conn->prepare("
        SELECT e.id, e.course_id, e.status, c.title
        FROM enrollments e
        INNER JOIN courses c ON c.id = e.course_id
        WHERE e.id = ? AND e.student_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $lookupStmt->bind_param('ii', $enrollmentId, $studentId);
    $lookupStmt->execute();
    $enrollment = $lookupStmt->get_result()->fetch_assoc() ?: null;
    $lookupStmt->close();

    if (!$enrollment || (string) $enrollment['status'] !== 'active') {
        throw new DomainException('This course is not currently active in your account.');
    }

    $updateStmt = $conn->prepare("
        UPDATE enrollments
        SET status = 'revoked',
            revoked_by_admin = NULL,
            revoked_at = NOW()
        WHERE id = ? AND student_id = ? AND status = 'active'
    ");
    $updateStmt->bind_param('ii', $enrollmentId, $studentId);
    $updateStmt->execute();

    if ($updateStmt->affected_rows !== 1) {
        $updateStmt->close();
        throw new RuntimeException('Course access changed before the reset completed.');
    }
    $updateStmt->close();

    $courseId = (int) $enrollment['course_id'];
    $cartStmt = $conn->prepare('DELETE FROM cart WHERE student_id = ? AND course_id = ?');
    $cartStmt->bind_param('ii', $studentId, $courseId);
    $cartStmt->execute();
    $cartStmt->close();

    send_notification(
        $conn,
        $studentId,
        'Course removed from My Courses',
        'Your access to ' . (string) $enrollment['title'] . ' was reset. You must purchase or enroll again to restore access.',
        'course'
    );

    $conn->commit();
    $transactionStarted = false;

    Auth::redirect('student-my-courses.php?removed=1');
} catch (DomainException $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    Auth::redirect('student-my-courses.php?remove_error=1');
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Student course reset failed: ' . $exception->getMessage());
    Auth::redirect('student-my-courses.php?remove_error=1');
}
