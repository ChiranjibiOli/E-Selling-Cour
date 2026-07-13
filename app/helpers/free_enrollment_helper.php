<?php

declare(strict_types=1);

require_once __DIR__ . '/notification_helper.php';

if (!function_exists('free_course_enroll_locked')) {
    /**
     * Create an immediate lifetime enrollment for a validated, locked free course.
     *
     * The current schema requires order and payment references on every enrollment,
     * so a zero-value completed audit record is created internally. The student does
     * not submit a transaction ID, payment proof, or wait for admin verification.
     *
     * @param array{id:mixed,title:mixed,price:mixed,instructor_id:mixed} $course
     */
    function free_course_enroll_locked(mysqli $conn, int $studentId, array $course): int
    {
        $courseId = (int) ($course['id'] ?? 0);
        $instructorId = (int) ($course['instructor_id'] ?? 0);
        $courseTitle = trim((string) ($course['title'] ?? 'Free course'));
        $price = (float) ($course['price'] ?? -1);

        if ($studentId <= 0 || $courseId <= 0 || $instructorId <= 0 || !is_finite($price) || $price > 0) {
            throw new DomainException('This course is not available for free enrollment.');
        }

        $enrollmentStmt = $conn->prepare("
            SELECT id, status
            FROM enrollments
            WHERE student_id = ? AND course_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $enrollmentStmt->bind_param('ii', $studentId, $courseId);
        $enrollmentStmt->execute();
        $existingEnrollment = $enrollmentStmt->get_result()->fetch_assoc() ?: null;
        $enrollmentStmt->close();

        if ($existingEnrollment && (string) $existingEnrollment['status'] === 'active') {
            $deleteCartStmt = $conn->prepare('DELETE FROM cart WHERE student_id = ? AND course_id = ?');
            $deleteCartStmt->bind_param('ii', $studentId, $courseId);
            $deleteCartStmt->execute();
            $deleteCartStmt->close();
            return (int) $existingEnrollment['id'];
        }

        $orderStmt = $conn->prepare("
            INSERT INTO orders (
                student_id, coupon_id, original_amount,
                discount_amount, final_amount, order_status
            ) VALUES (?, NULL, 0, 0, 0, 'paid')
        ");
        $orderStmt->bind_param('i', $studentId);
        $orderStmt->execute();
        $orderId = (int) $conn->insert_id;
        $orderStmt->close();

        $itemStmt = $conn->prepare("
            INSERT INTO order_items (
                order_id, course_id, instructor_id,
                course_price, discount_amount, final_price
            ) VALUES (?, ?, ?, 0, 0, 0)
        ");
        $itemStmt->bind_param('iii', $orderId, $courseId, $instructorId);
        $itemStmt->execute();
        $itemStmt->close();

        $transactionId = 'FREE-' . $studentId . '-' . $courseId . '-' . bin2hex(random_bytes(12));
        $paymentStmt = $conn->prepare("
            INSERT INTO payments (
                order_id, student_id, payment_method, payment_type,
                transaction_id, paid_amount, payment_status, verified_at
            ) VALUES (?, ?, 'free', 'free', ?, 0, 'paid', NOW())
        ");
        $paymentStmt->bind_param('iis', $orderId, $studentId, $transactionId);
        $paymentStmt->execute();
        $paymentId = (int) $conn->insert_id;
        $paymentStmt->close();

        if ($existingEnrollment) {
            $enrollmentId = (int) $existingEnrollment['id'];
            $updateStmt = $conn->prepare("
                UPDATE enrollments
                SET order_id = ?, payment_id = ?, access_type = 'lifetime',
                    status = 'active', granted_by = NULL, granted_at = NOW(),
                    revoked_by_admin = NULL, revoked_at = NULL
                WHERE id = ?
            ");
            $updateStmt->bind_param('iii', $orderId, $paymentId, $enrollmentId);
            $updateStmt->execute();
            if ($updateStmt->affected_rows !== 1) {
                throw new RuntimeException('The previous enrollment could not be reactivated.');
            }
            $updateStmt->close();
        } else {
            $insertEnrollmentStmt = $conn->prepare("
                INSERT INTO enrollments (
                    student_id, course_id, order_id, payment_id,
                    access_type, status, granted_by, granted_at
                ) VALUES (?, ?, ?, ?, 'lifetime', 'active', NULL, NOW())
            ");
            $insertEnrollmentStmt->bind_param('iiii', $studentId, $courseId, $orderId, $paymentId);
            $insertEnrollmentStmt->execute();
            $enrollmentId = (int) $conn->insert_id;
            $insertEnrollmentStmt->close();
        }

        $deleteCartStmt = $conn->prepare('DELETE FROM cart WHERE student_id = ? AND course_id = ?');
        $deleteCartStmt->bind_param('ii', $studentId, $courseId);
        $deleteCartStmt->execute();
        $deleteCartStmt->close();

        send_notification(
            $conn,
            $studentId,
            'Free course enrolled',
            'You now have lifetime access to ' . ($courseTitle !== '' ? $courseTitle : 'this free course') . '.',
            'course'
        );

        send_notification(
            $conn,
            $instructorId,
            'New free course enrollment',
            'A student enrolled in ' . ($courseTitle !== '' ? $courseTitle : 'one of your free courses') . '.',
            'course'
        );

        return $enrollmentId;
    }
}

if (!function_exists('enroll_student_in_free_course')) {
    function enroll_student_in_free_course(mysqli $conn, int $studentId, int $courseId): int
    {
        if ($studentId <= 0 || $courseId <= 0) {
            throw new InvalidArgumentException('Invalid free enrollment request.');
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            $courseStmt = $conn->prepare("
                SELECT c.id, c.title, c.price, c.instructor_id
                FROM courses c
                INNER JOIN users instructor ON instructor.id = c.instructor_id
                INNER JOIN categories category ON category.id = c.category_id
                WHERE c.id = ?
                  AND c.status = 'published'
                  AND c.price <= 0
                  AND instructor.role = 'instructor'
                  AND instructor.status = 'active'
                  AND category.status = 'active'
                LIMIT 1
                FOR UPDATE
            ");
            $courseStmt->bind_param('i', $courseId);
            $courseStmt->execute();
            $course = $courseStmt->get_result()->fetch_assoc() ?: null;
            $courseStmt->close();

            if (!$course) {
                throw new DomainException('This free course is no longer available.');
            }

            $enrollmentId = free_course_enroll_locked($conn, $studentId, $course);
            $conn->commit();
            return $enrollmentId;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            throw $exception;
        }
    }
}
