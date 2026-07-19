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

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') { return []; }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) { throw new InvalidArgumentException('Request body must be a JSON object.'); }
    return $decoded;
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
        if (!is_array($record)) { throw new ServiceAuthorizationException('That order is not available in your account.'); }
        if ((float) $record['final_amount'] !== 0.0 || $record['order_status'] !== 'pending') {
            throw new ServiceAuthorizationException('Only a pending zero-total order can be activated without payment.');
        }
        $paymentInsert = $database->prepare("INSERT INTO payments (order_id,student_id,payment_method,payment_type,transaction_id,paid_amount,payment_status,verified_at) VALUES (:order_id,:student_id,'free','automatic',:transaction_id,0,'paid',NOW())");
        $paymentInsert->execute(['order_id' => $orderId, 'student_id' => $student['id'], 'transaction_id' => 'FREE-' . $orderId . '-' . $student['id']]);
        $paymentId = (int) $database->lastInsertId();
        $items = $database->prepare('SELECT id,course_id,instructor_id FROM order_items WHERE order_id=:order_id FOR UPDATE');
        $items->execute(['order_id' => $orderId]);
        $orderItems = $items->fetchAll();
        if ($orderItems === []) { throw new RuntimeException('The order does not contain any courses.'); }
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
        $statement = $database->prepare(
            'SELECT e.id, e.course_id, e.access_type, e.status, e.granted_at, DATE_ADD(e.granted_at, INTERVAL 12 HOUR) AS access_request_deadline, '
            . 'c.title, c.short_description, c.thumbnail, c.level, c.language, c.duration, u.full_name AS instructor_name, '
            . '(SELECT COUNT(*) FROM course_lessons cl INNER JOIN course_sections cs ON cs.id = cl.section_id WHERE cs.course_id = c.id) AS lesson_count '
            . 'FROM enrollments e INNER JOIN courses c ON c.id = e.course_id INNER JOIN users u ON u.id = c.instructor_id '
            . 'WHERE e.student_id = :student_id ORDER BY e.granted_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['thumbnail_url'] = trim((string) ($row['thumbnail'] ?? '')) !== '' ? '/media/course-thumbnails/' . rawurlencode(basename((string) $row['thumbnail'])) : '';
            $row['can_request_removal'] = $row['status'] === 'active' && strtotime((string) $row['access_request_deadline']) >= time();
            unset($row['thumbnail']);
        }
        unset($row);
        $respond(['data' => $rows]);
    }

    if ($path === '/api/v1/enrollments/unsubscribe/mine' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT r.id, r.enrollment_id, r.reason, r.request_status, r.requested_at, r.deadline_at, r.processed_at, '
            . 'e.status AS enrollment_status, c.title AS course_title '
            . 'FROM unsubscribe_requests r INNER JOIN enrollments e ON e.id=r.enrollment_id INNER JOIN courses c ON c.id=e.course_id '
            . 'WHERE r.student_id=:student_id ORDER BY r.requested_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/enrollments/(\d+)/unsubscribe$#', $path, $matches) === 1 && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $reason = trim((string) ($input['reason'] ?? ''));
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('Explain the access-removal reason in 10 to 2000 characters.');
        }
        $database->beginTransaction();
        $statement = $database->prepare(
            'SELECT e.id, e.course_id, e.status, e.granted_at, c.title FROM enrollments e INNER JOIN courses c ON c.id=e.course_id '
            . 'WHERE e.id=:id AND e.student_id=:student_id FOR UPDATE'
        );
        $statement->execute(['id' => (int) $matches[1], 'student_id' => $student['id']]);
        $enrollment = $statement->fetch();
        if (!is_array($enrollment) || $enrollment['status'] !== 'active') {
            throw new ServiceAuthorizationException('Only your active enrollment can receive an access-removal request.');
        }
        $deadline = strtotime((string) $enrollment['granted_at'] . ' +12 hours');
        if ($deadline === false || $deadline < time()) {
            throw new ServiceAuthorizationException('The twelve-hour access-removal request window has closed.');
        }
        $existing = $database->prepare('SELECT id FROM unsubscribe_requests WHERE enrollment_id=:enrollment_id AND request_status=\'pending\' LIMIT 1');
        $existing->execute(['enrollment_id' => (int) $enrollment['id']]);
        if ($existing->fetch() !== false) {
            throw new ServiceAuthorizationException('A pending request already exists for this enrollment.');
        }
        $insert = $database->prepare(
            'INSERT INTO unsubscribe_requests (enrollment_id,student_id,reason,request_status,deadline_at) '
            . 'VALUES (:enrollment_id,:student_id,:reason,\'pending\',DATE_ADD(:granted_at, INTERVAL 12 HOUR))'
        );
        $insert->execute(['enrollment_id' => (int) $enrollment['id'], 'student_id' => $student['id'], 'reason' => $reason, 'granted_at' => $enrollment['granted_at']]);
        $requestId = (int) $database->lastInsertId();
        $notifyAdmins = $database->prepare(
            'INSERT INTO notifications (user_id,title,message,notification_type) '
            . 'SELECT id,\'Access-removal request\',:message,\'access_request\' FROM users WHERE role=\'admin\' AND status=\'active\''
        );
        $notifyAdmins->execute(['message' => $student['name'] . ' requested removal from "' . $enrollment['title'] . '".']);
        $database->commit();
        $respond(['message' => 'Access-removal request submitted for administrator review.', 'data' => ['request_id' => $requestId]], 201);
    }

    if ($path === '/api/v1/enrollments/unsubscribe/pending' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query(
            'SELECT r.id, r.enrollment_id, r.reason, r.requested_at, r.deadline_at, s.full_name AS student_name, s.email AS student_email, '
            . 'c.title AS course_title, e.order_id, e.payment_id FROM unsubscribe_requests r '
            . 'INNER JOIN enrollments e ON e.id=r.enrollment_id INNER JOIN users s ON s.id=r.student_id INNER JOIN courses c ON c.id=e.course_id '
            . 'WHERE r.request_status=\'pending\' ORDER BY r.requested_at ASC'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/enrollments/unsubscribe/(\d+)/(approve|reject)$#', $path, $matches) === 1 && $method === 'POST') {
        $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
        $decision = $matches[2];
        $database->beginTransaction();
        $statement = $database->prepare(
            'SELECT r.id, r.enrollment_id, r.student_id, c.title FROM unsubscribe_requests r '
            . 'INNER JOIN enrollments e ON e.id=r.enrollment_id INNER JOIN courses c ON c.id=e.course_id '
            . 'WHERE r.id=:id AND r.request_status=\'pending\' FOR UPDATE'
        );
        $statement->execute(['id' => (int) $matches[1]]);
        $request = $statement->fetch();
        if (!is_array($request)) { throw new ServiceAuthorizationException('The access-removal request is no longer pending.'); }
        $update = $database->prepare('UPDATE unsubscribe_requests SET request_status=:status,processed_by=:admin_id,processed_at=NOW() WHERE id=:id AND request_status=\'pending\'');
        $update->execute(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'admin_id' => $admin['id'], 'id' => (int) $request['id']]);
        if ($decision === 'approve') {
            $revoke = $database->prepare("UPDATE enrollments SET status='revoked',revoked_by_admin=:admin_id,revoked_at=NOW() WHERE id=:id AND status='active'");
            $revoke->execute(['admin_id' => $admin['id'], 'id' => (int) $request['enrollment_id']]);
        }
        $notification = $database->prepare('INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:user_id,:title,:message,\'access_request\')');
        $notification->execute([
            'user_id' => (int) $request['student_id'],
            'title' => $decision === 'approve' ? 'Course access removed' : 'Access-removal request rejected',
            'message' => $decision === 'approve' ? 'Your access to "' . $request['title'] . '" was removed. This does not automatically create a refund.' : 'Your request for "' . $request['title'] . '" was rejected and access remains active.',
        ]);
        $database->commit();
        $respond(['message' => $decision === 'approve' ? 'Access removed.' : 'Request rejected.']);
    }

    if (preg_match('#^/api/v1/enrollments/(\d+)/access$#', $path, $matches) === 1 && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
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

    $respond(['error' => 'Enrollment route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    error_log('Enrollment database failure: ' . $exception->getMessage());
    $respond(['error' => 'Enrollment request could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    error_log('Enrollment service failure: ' . $exception->getMessage());
    $respond(['error' => 'Enrollment service is unavailable.'], 503);
}
