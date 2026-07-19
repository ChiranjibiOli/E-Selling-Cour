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
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'payment-service']);
    }

    if ($path === '/api/v1/payments/mine' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT p.id, p.order_id, p.payment_method, p.payment_type, p.transaction_id, p.paid_amount, p.payment_status, '
            . 'p.verified_at, p.uploaded_at, pp.proof_image, pp.note, o.final_amount, o.order_status '
            . 'FROM payments p INNER JOIN orders o ON o.id = p.order_id LEFT JOIN payment_proofs pp ON pp.payment_id = p.id '
            . 'WHERE p.student_id = :student_id ORDER BY p.uploaded_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/payments/manual' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $orderId = (int) ($input['order_id'] ?? 0);
        $transactionId = trim((string) ($input['transaction_id'] ?? ''));
        $proofImage = basename(trim((string) ($input['proof_image'] ?? '')));
        $note = trim((string) ($input['note'] ?? ''));

        if ($orderId < 1) {
            throw new InvalidArgumentException('Choose a valid pending order.');
        }
        if ($transactionId === '' || mb_strlen($transactionId) > 150 || preg_match('/^[A-Za-z0-9._:\/-]{4,150}$/', $transactionId) !== 1) {
            throw new InvalidArgumentException('Enter a valid payment transaction reference.');
        }
        if ($proofImage === '' || mb_strlen($proofImage) > 255 || preg_match('/\.(?:png|jpe?g|webp|pdf)$/i', $proofImage) !== 1) {
            throw new InvalidArgumentException('Provide the validated proof filename created by the media upload flow.');
        }
        if (mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('The payment note is too long.');
        }

        $database->beginTransaction();
        $order = $database->prepare('SELECT id, final_amount, order_status FROM orders WHERE id = :id AND student_id = :student_id FOR UPDATE');
        $order->execute(['id' => $orderId, 'student_id' => $student['id']]);
        $record = $order->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('That order is not available in your account.');
        }
        if ($record['order_status'] !== 'pending') {
            throw new ServiceAuthorizationException('Only a pending order can receive a payment proof.');
        }
        if ((float) $record['final_amount'] <= 0) {
            throw new InvalidArgumentException('This order does not require a manual payment.');
        }

        $existing = $database->prepare('SELECT id, payment_status FROM payments WHERE order_id = :order_id LIMIT 1');
        $existing->execute(['order_id' => $orderId]);
        $previous = $existing->fetch();
        if (is_array($previous)) {
            throw new InvalidArgumentException('A payment has already been submitted for this order.');
        }

        $payment = $database->prepare(
            'INSERT INTO payments (order_id, student_id, payment_method, payment_type, transaction_id, paid_amount, payment_status) '
            . 'VALUES (:order_id, :student_id, \'manual\', \'manual\', :transaction_id, :paid_amount, \'pending\')'
        );
        $payment->execute([
            'order_id' => $orderId,
            'student_id' => $student['id'],
            'transaction_id' => $transactionId,
            'paid_amount' => $record['final_amount'],
        ]);
        $paymentId = (int) $database->lastInsertId();
        $proof = $database->prepare('INSERT INTO payment_proofs (payment_id, proof_image, note) VALUES (:payment_id, :proof_image, :note)');
        $proof->execute(['payment_id' => $paymentId, 'proof_image' => $proofImage, 'note' => $note !== '' ? $note : null]);
        $notification = $database->prepare(
            'INSERT INTO notifications (user_id, title, message, notification_type) '
            . 'SELECT id, \'Payment awaiting review\', :message, \'payment_submitted\' FROM users WHERE role = \'admin\' AND status = \'active\''
        );
        $notification->execute(['message' => 'Manual payment #' . $paymentId . ' requires verification.']);
        $database->commit();
        $respond(['message' => 'Payment proof submitted for administrator verification.', 'data' => ['payment_id' => $paymentId, 'status' => 'pending']], 201);
    }

    if ($path === '/api/v1/payments/pending' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query(
            'SELECT p.id, p.order_id, p.transaction_id, p.paid_amount, p.payment_status, p.uploaded_at, '
            . 'pp.proof_image, pp.note, o.final_amount, u.full_name AS student_name, u.email AS student_email '
            . 'FROM payments p INNER JOIN orders o ON o.id = p.order_id INNER JOIN users u ON u.id = p.student_id '
            . 'LEFT JOIN payment_proofs pp ON pp.payment_id = p.id WHERE p.payment_status = \'pending\' '
            . 'ORDER BY p.uploaded_at ASC, p.id ASC'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/payments/(\d+)/(approve|reject)$#', $path, $matches) === 1 && $method === 'POST') {
        $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
        $paymentId = (int) $matches[1];
        $decision = $matches[2];
        $input = $jsonInput();
        $note = trim((string) ($input['note'] ?? ''));
        if ($decision === 'reject' && $note === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }
        if (mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('The review note is too long.');
        }

        $database->beginTransaction();
        $payment = $database->prepare(
            'SELECT p.*, o.final_amount, o.order_status, o.coupon_id FROM payments p '
            . 'INNER JOIN orders o ON o.id = p.order_id WHERE p.id = :id FOR UPDATE'
        );
        $payment->execute(['id' => $paymentId]);
        $record = $payment->fetch();
        if (!is_array($record)) {
            $respond(['error' => 'Payment not found.'], 404);
        }
        if ($record['payment_status'] !== 'pending') {
            throw new ServiceAuthorizationException('This payment has already been processed.');
        }

        if ($decision === 'reject') {
            $update = $database->prepare('UPDATE payments SET payment_status = \'rejected\', verified_by = :admin_id, verified_at = NOW() WHERE id = :id AND payment_status = \'pending\'');
            $update->execute(['admin_id' => $admin['id'], 'id' => $paymentId]);
            $orderUpdate = $database->prepare('UPDATE orders SET order_status = \'failed\' WHERE id = :order_id AND order_status = \'pending\'');
            $orderUpdate->execute(['order_id' => (int) $record['order_id']]);
            $notify = $database->prepare('INSERT INTO notifications (user_id, title, message, notification_type) VALUES (:user_id, \'Payment rejected\', :message, \'payment_rejected\')');
            $notify->execute(['user_id' => (int) $record['student_id'], 'message' => 'Your payment for order #' . $record['order_id'] . ' was rejected. ' . $note]);
            $database->commit();
            $respond(['message' => 'Payment rejected.', 'status' => 'rejected']);
        }

        if (abs((float) $record['paid_amount'] - (float) $record['final_amount']) > 0.009 || $record['order_status'] !== 'pending') {
            throw new InvalidArgumentException('Payment amount or order state does not match the pending order.');
        }

        $update = $database->prepare('UPDATE payments SET payment_status = \'paid\', verified_by = :admin_id, verified_at = NOW() WHERE id = :id AND payment_status = \'pending\'');
        $update->execute(['admin_id' => $admin['id'], 'id' => $paymentId]);
        $orderUpdate = $database->prepare('UPDATE orders SET order_status = \'paid\' WHERE id = :order_id AND order_status = \'pending\'');
        $orderUpdate->execute(['order_id' => (int) $record['order_id']]);
        if ($update->rowCount() !== 1 || $orderUpdate->rowCount() !== 1) {
            throw new ServiceAuthorizationException('The payment could not be finalized safely.');
        }

        $commissionRate = 20.0;
        $setting = $database->prepare('SELECT setting_value FROM site_settings WHERE setting_key = \'platform_commission_rate\' LIMIT 1');
        $setting->execute();
        $settingValue = $setting->fetchColumn();
        if ($settingValue !== false && is_numeric($settingValue)) {
            $commissionRate = max(0, min(100, (float) $settingValue));
        }

        $items = $database->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id FOR UPDATE');
        $items->execute(['order_id' => (int) $record['order_id']]);
        $orderItems = $items->fetchAll();
        $enrollment = $database->prepare(
            'INSERT INTO enrollments (student_id, course_id, order_id, payment_id, access_type, status, granted_by, granted_at) '
            . 'VALUES (:student_id, :course_id, :order_id, :payment_id, \'lifetime\', \'active\', :granted_by, NOW()) '
            . 'ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), payment_id = VALUES(payment_id), status = \'active\', granted_by = VALUES(granted_by), granted_at = NOW(), revoked_by_admin = NULL, revoked_at = NULL'
        );
        $earning = $database->prepare(
            'INSERT INTO instructor_earnings (instructor_id, course_id, student_id, order_id, order_item_id, payment_id, gross_amount, commission_rate, commission_amount, instructor_amount, earning_status) '
            . 'VALUES (:instructor_id, :course_id, :student_id, :order_id, :order_item_id, :payment_id, :gross_amount, :commission_rate, :commission_amount, :instructor_amount, \'available\') '
            . 'ON DUPLICATE KEY UPDATE gross_amount = VALUES(gross_amount), commission_rate = VALUES(commission_rate), commission_amount = VALUES(commission_amount), instructor_amount = VALUES(instructor_amount), earning_status = IF(earning_status = \'paid\', earning_status, \'available\')'
        );
        $notify = $database->prepare('INSERT INTO notifications (user_id, title, message, notification_type) VALUES (:user_id, :title, :message, :type)');
        foreach ($orderItems as $item) {
            $gross = (float) $item['final_price'];
            $commission = round($gross * $commissionRate / 100, 2);
            $instructorAmount = round($gross - $commission, 2);
            $enrollment->execute([
                'student_id' => (int) $record['student_id'],
                'course_id' => (int) $item['course_id'],
                'order_id' => (int) $record['order_id'],
                'payment_id' => $paymentId,
                'granted_by' => $admin['id'],
            ]);
            $earning->execute([
                'instructor_id' => (int) $item['instructor_id'],
                'course_id' => (int) $item['course_id'],
                'student_id' => (int) $record['student_id'],
                'order_id' => (int) $record['order_id'],
                'order_item_id' => (int) $item['id'],
                'payment_id' => $paymentId,
                'gross_amount' => number_format($gross, 2, '.', ''),
                'commission_rate' => number_format($commissionRate, 2, '.', ''),
                'commission_amount' => number_format($commission, 2, '.', ''),
                'instructor_amount' => number_format($instructorAmount, 2, '.', ''),
            ]);
            $notify->execute([
                'user_id' => (int) $item['instructor_id'],
                'title' => 'New verified enrollment',
                'message' => 'A verified student purchase created an enrollment in your course.',
                'type' => 'new_enrollment',
            ]);
        }
        $notify->execute([
            'user_id' => (int) $record['student_id'],
            'title' => 'Course access activated',
            'message' => 'Payment for order #' . $record['order_id'] . ' was approved. Your purchased courses are now available for life.',
            'type' => 'enrollment_granted',
        ]);
        if ($record['coupon_id'] !== null) {
            $coupon = $database->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id AND (usage_limit IS NULL OR used_count < usage_limit)');
            $coupon->execute(['id' => (int) $record['coupon_id']]);
        }
        $database->commit();
        $respond(['message' => 'Payment approved and lifetime course access granted.', 'status' => 'paid']);
    }

    $respond(['error' => 'Payment route not found.'], 404);
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
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Payment database failure: ' . $exception->getMessage());
    $respond(['error' => $exception->getCode() === '23000' ? 'That transaction reference or order is already registered.' : 'Payment request could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Payment service failure: ' . $exception->getMessage());
    $respond(['error' => 'Payment service is unavailable.'], 503);
}
