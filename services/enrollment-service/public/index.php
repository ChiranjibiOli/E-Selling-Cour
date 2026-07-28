<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';
require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$repairPaidEnrollments = static function (PDO $database, int $studentId): void {
    $insert = $database->prepare(
        'INSERT INTO enrollments (student_id,course_id,order_id,payment_id,access_type,status,granted_by,granted_at) '
        . 'SELECT o.student_id,oi.course_id,o.id,p.id,\'lifetime\',\'active\',p.verified_by,COALESCE(p.verified_at,NOW()) '
        . 'FROM orders o INNER JOIN payments p ON p.order_id=o.id AND p.student_id=o.student_id '
        . 'INNER JOIN order_items oi ON oi.order_id=o.id '
        . 'WHERE o.student_id=:student_id AND o.order_status=\'paid\' AND p.payment_status=\'paid\' '
        . 'AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id=o.student_id AND e.course_id=oi.course_id)'
    );
    $insert->execute(['student_id' => $studentId]);

    $restore = $database->prepare(
        'UPDATE enrollments e INNER JOIN payments p ON p.id=e.payment_id AND p.student_id=e.student_id '
        . 'INNER JOIN orders o ON o.id=e.order_id AND o.student_id=e.student_id '
        . 'SET e.status=\'active\',e.access_type=\'lifetime\',e.revoked_by_admin=NULL,e.revoked_at=NULL '
        . 'WHERE e.student_id=:student_id AND e.status=\'revoked\' AND p.payment_status=\'paid\' AND o.order_status=\'paid\''
    );
    $restore->execute(['student_id' => $studentId]);
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'enrollment-service']);
    }

    if (preg_match('#^/api/v1/enrollments/free-order/(\d+)$#', $path, $matches) === 1 && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $orderId = (int) $matches[1];
        $database->beginTransaction();
        $order = $database->prepare('SELECT id, coupon_id, final_amount, order_status FROM orders WHERE id=:id AND student_id=:student_id FOR UPDATE');
        $order->execute(['id' => $orderId, 'student_id' => $student['id']]);
        $record = $order->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('That order is not available in your account.');
        }
        if ((float) $record['final_amount'] !== 0.0 || $record['order_status'] !== 'pending') {
            throw new ServiceAuthorizationException('Only a pending zero-total order can be activated without payment.');
        }
        $paymentInsert = $database->prepare("INSERT INTO payments (order_id,student_id,payment_method,payment_type,transaction_id,paid_amount,payment_status,verified_at) VALUES (:order_id,:student_id,'free','automatic',:transaction_id,0,'paid',NOW())");
        $paymentInsert->execute(['order_id' => $orderId, 'student_id' => $student['id'], 'transaction_id' => 'FREE-' . $orderId . '-' . $student['id']]);
        $paymentId = (int) $database->lastInsertId();
        $items = $database->prepare('SELECT id,course_id,instructor_id FROM order_items WHERE order_id=:order_id FOR UPDATE');
        $items->execute(['order_id' => $orderId]);
        $orderItems = $items->fetchAll();
        if ($orderItems === []) {
            throw new RuntimeException('The order does not contain any courses.');
        }
        $enrollment = $database->prepare("INSERT INTO enrollments (student_id,course_id,order_id,payment_id,access_type,status,granted_at) VALUES (:student_id,:course_id,:order_id,:payment_id,'lifetime','active',NOW()) ON DUPLICATE KEY UPDATE order_id=VALUES(order_id),payment_id=VALUES(payment_id),status='active',granted_at=NOW(),revoked_by_admin=NULL,revoked_at=NULL");
        $notification = $database->prepare('INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:user_id,:title,:message,:type)');
        foreach ($orderItems as $item) {
            $enrollment->execute(['student_id' => $student['id'], 'course_id' => (int) $item['course_id'], 'order_id' => $orderId, 'payment_id' => $paymentId]);
            $notification->execute(['user_id' => (int) $item['instructor_id'], 'title' => 'New free enrollment', 'message' => 'A student enrolled in your free or fully discounted course.', 'type' => 'new_enrollment']);
        }
        $database->prepare("UPDATE orders SET order_status='paid' WHERE id=:id AND order_status='pending'")->execute(['id' => $orderId]);
        if ($record['coupon_id'] !== null) {
            $database->prepare('UPDATE coupons SET used_count=used_count+1 WHERE id=:id AND (usage_limit IS NULL OR used_count<usage_limit)')->execute(['id' => (int) $record['coupon_id']]);
        }
        $notification->execute(['user_id' => $student['id'], 'title' => 'Course access activated', 'message' => 'Your zero-total order #' . $orderId . ' is complete. Lifetime access is active.', 'type' => 'enrollment_granted']);
        $database->commit();
        $respond(['message' => 'Lifetime access activated for the zero-total order.', 'data' => ['order_id' => $orderId, 'payment_id' => $paymentId]], 201);
    }

    if ($path === '/api/v1/enrollments/mine' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $repairPaidEnrollments($database, (int) $student['id']);
        $statement = $database->prepare(
            'SELECT e.id, e.course_id, e.access_type, e.status, e.granted_at, '
            . 'c.title, c.short_description, c.thumbnail, c.level, c.language, c.duration, u.full_name AS instructor_name, '
            . '(SELECT COUNT(*) FROM course_lessons cl INNER JOIN course_sections cs ON cs.id = cl.section_id WHERE cs.course_id = c.id) AS lesson_count '
            . 'FROM enrollments e INNER JOIN courses c ON c.id = e.course_id INNER JOIN users u ON u.id = c.instructor_id '
            . 'WHERE e.student_id = :student_id AND e.status = \'active\' ORDER BY e.granted_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['thumbnail_url'] = trim((string) ($row['thumbnail'] ?? '')) !== ''
                ? '/media/course-thumbnails/' . rawurlencode(basename((string) $row['thumbnail']))
                : '';
            unset($row['thumbnail']);
        }
        unset($row);
        $respond(['data' => $rows]);
    }

    if (preg_match('#^/api/v1/enrollments/(\d+)/access$#', $path, $matches) === 1 && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $repairPaidEnrollments($database, (int) $student['id']);
        $statement = $database->prepare('SELECT e.id,e.status,e.access_type,e.granted_at FROM enrollments e WHERE e.student_id=:student_id AND e.course_id=:course_id AND e.status=\'active\' LIMIT 1');
        $statement->execute(['student_id' => $student['id'], 'course_id' => (int) $matches[1]]);
        $enrollment = $statement->fetch();
        $respond(['data' => ['allowed' => is_array($enrollment), 'enrollment' => is_array($enrollment) ? $enrollment : null]]);
    }

    if ($path === '/api/v1/enrollments' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query(
            'SELECT e.id,e.access_type,e.status,e.granted_at,e.revoked_at,s.full_name AS student_name,s.email AS student_email,'
            . 'c.title AS course_title,i.full_name AS instructor_name,e.order_id,e.payment_id '
            . 'FROM enrollments e INNER JOIN users s ON s.id=e.student_id INNER JOIN courses c ON c.id=e.course_id '
            . 'INNER JOIN users i ON i.id=c.instructor_id ORDER BY e.created_at DESC LIMIT 500'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    if (str_contains($path, '/unsubscribe')) {
        $respond(['error' => 'Purchased-course removal requests are no longer supported.'], 410);
    }

    $respond(['error' => 'Enrollment route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Enrollment database failure: ' . $exception->getMessage());
    $respond(['error' => 'Enrollment request could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Enrollment service failure: ' . $exception->getMessage());
    $respond(['error' => 'Enrollment service is unavailable.'], 503);
}
