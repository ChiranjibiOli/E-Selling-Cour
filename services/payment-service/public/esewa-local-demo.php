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

$environment = static function (string $name, string $fallback = ''): string {
    $value = trim((string) getenv($name));
    return $value !== '' ? $value : $fallback;
};

$booleanEnvironment = static function (string $name, bool $fallback): bool {
    $raw = trim((string) getenv($name));
    if ($raw === '') {
        return $fallback;
    }
    $value = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    return is_bool($value) ? $value : $fallback;
};

$localDemoAllowed = strtolower($environment('APP_ENV', 'local')) !== 'production'
    && strtolower($environment('ESEWA_ENV', 'sandbox')) !== 'production'
    && $booleanEnvironment('ESEWA_LOCAL_DEMO', true);

$demoToken = static function (int $orderId, int $studentId, string $amount, string $transactionUuid, string $secret): string {
    return hash_hmac(
        'sha256',
        'order_id=' . $orderId . '&student_id=' . $studentId . '&amount=' . $amount . '&transaction_uuid=' . $transactionUuid,
        $secret,
    );
};

$formatAmount = static function (mixed $amount): string {
    if (!is_numeric($amount)) {
        throw new InvalidArgumentException('The order amount is invalid.');
    }
    $value = round((float) $amount, 2);
    if ($value <= 0) {
        throw new InvalidArgumentException('This order does not require payment.');
    }
    return number_format($value, 2, '.', '');
};

$gatewayEnabled = static function (PDO $database): bool {
    $statement = $database->prepare('SELECT setting_value FROM site_settings WHERE setting_key = \'esewa_enabled\' LIMIT 1');
    $statement->execute();
    return trim((string) ($statement->fetchColumn() ?: '')) === '1';
};

try {
    if (!$localDemoAllowed) {
        $respond(['error' => 'The local eSewa simulator is disabled outside the local sandbox environment.'], 404);
    }
    if ($method !== 'POST') {
        $respond(['error' => 'Local eSewa simulator route not found.'], 404);
    }

    $database = Database::connect();
    $student = ServiceAuth::requireUser($database, $authorization, 'student');
    if (!$gatewayEnabled($database)) {
        throw new InvalidArgumentException('eSewa payments are not enabled by the platform administrator.');
    }

    $input = $jsonInput();
    $secretKey = $environment('ESEWA_SECRET_KEY');
    $productCode = $environment('ESEWA_PRODUCT_CODE', 'EPAYTEST');
    if ($secretKey === '' || $productCode === '') {
        throw new RuntimeException('eSewa local demo credentials are not configured.');
    }

    if ($path === '/api/v1/payments/esewa/initiate') {
        $orderId = (int) ($input['order_id'] ?? 0);
        if ($orderId < 1) {
            throw new InvalidArgumentException('Choose a valid pending order.');
        }

        $database->beginTransaction();
        $orderQuery = $database->prepare(
            'SELECT o.id,o.student_id,o.final_amount,o.order_status FROM orders o '
            . 'WHERE o.id=:id AND o.student_id=:student_id FOR UPDATE'
        );
        $orderQuery->execute(['id' => $orderId, 'student_id' => (int) $student['id']]);
        $order = $orderQuery->fetch();
        if (!is_array($order)) {
            throw new ServiceAuthorizationException('That order is not available in your account.');
        }
        if ($order['order_status'] !== 'pending') {
            throw new InvalidArgumentException('Only a pending order can be paid.');
        }
        $amount = $formatAmount($order['final_amount']);
        $transactionUuid = 'CH-' . $orderId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

        $existingQuery = $database->prepare('SELECT id,payment_type,payment_status FROM payments WHERE order_id=:order_id FOR UPDATE');
        $existingQuery->execute(['order_id' => $orderId]);
        $existing = $existingQuery->fetch();
        if (is_array($existing)) {
            if ($existing['payment_status'] === 'paid') {
                throw new InvalidArgumentException('This order has already been paid.');
            }
            if ($existing['payment_type'] === 'manual' && $existing['payment_status'] === 'pending') {
                throw new InvalidArgumentException('A manual payment is already awaiting administrator verification.');
            }
            $paymentUpdate = $database->prepare(
                'UPDATE payments SET payment_method=\'esewa\',payment_type=\'automatic\',transaction_id=:transaction_id,'
                . 'paid_amount=:paid_amount,payment_status=\'pending\',verified_by=NULL,verified_at=NULL WHERE id=:id'
            );
            $paymentUpdate->execute([
                'transaction_id' => $transactionUuid,
                'paid_amount' => $amount,
                'id' => (int) $existing['id'],
            ]);
            $paymentId = (int) $existing['id'];
        } else {
            $paymentInsert = $database->prepare(
                'INSERT INTO payments (order_id,student_id,payment_method,payment_type,transaction_id,paid_amount,payment_status) '
                . 'VALUES (:order_id,:student_id,\'esewa\',\'automatic\',:transaction_id,:paid_amount,\'pending\')'
            );
            $paymentInsert->execute([
                'order_id' => $orderId,
                'student_id' => (int) $student['id'],
                'transaction_id' => $transactionUuid,
                'paid_amount' => $amount,
            ]);
            $paymentId = (int) $database->lastInsertId();
        }

        $token = $demoToken($orderId, (int) $student['id'], $amount, $transactionUuid, $secretKey);
        $database->commit();
        $respond([
            'data' => [
                'mode' => 'local-demo',
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'amount' => $amount,
                'transaction_uuid' => $transactionUuid,
                'demo_token' => $token,
                'product_code' => $productCode,
                'test_account' => '9711111111',
                'test_password' => 'Nepal@123',
                'test_token' => '123456',
            ],
        ], 201);
    }

    if ($path === '/api/v1/payments/esewa/demo-complete') {
        $orderId = (int) ($input['order_id'] ?? 0);
        $transactionUuid = trim((string) ($input['transaction_uuid'] ?? ''));
        $providedDemoToken = strtolower(trim((string) ($input['demo_token'] ?? '')));
        $esewaId = trim((string) ($input['esewa_id'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $otp = trim((string) ($input['token'] ?? ''));

        if ($orderId < 1 || preg_match('/^CH-' . $orderId . '-[A-Za-z0-9-]+$/', $transactionUuid) !== 1) {
            throw new InvalidArgumentException('The local eSewa payment reference is invalid.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $providedDemoToken) !== 1) {
            throw new InvalidArgumentException('The local eSewa payment token is invalid.');
        }
        $acceptedIds = ['9711111111', '9711111112', '9711111113', '9806800001', '9806800002', '9806800003', '9806800004', '9806800005'];
        if (!in_array($esewaId, $acceptedIds, true) || !hash_equals('Nepal@123', $password) || !hash_equals('123456', $otp)) {
            throw new InvalidArgumentException('Use the displayed eSewa sandbox ID, password and token.');
        }

        $database->beginTransaction();
        $snapshot = $database->prepare(
            'SELECT p.*,o.final_amount,o.order_status,o.coupon_id FROM payments p '
            . 'INNER JOIN orders o ON o.id=p.order_id '
            . 'WHERE p.order_id=:order_id AND p.student_id=:student_id FOR UPDATE'
        );
        $snapshot->execute(['order_id' => $orderId, 'student_id' => (int) $student['id']]);
        $record = $snapshot->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('The local eSewa payment attempt was not found.');
        }
        if ($record['payment_status'] === 'paid' && $record['order_status'] === 'paid') {
            $database->commit();
            $respond(['message' => 'Local eSewa payment was already completed.', 'data' => ['payment_id' => (int) $record['id'], 'order_id' => $orderId, 'status' => 'paid']]);
        }
        if ($record['payment_method'] !== 'esewa'
            || $record['payment_type'] !== 'automatic'
            || !hash_equals((string) $record['transaction_id'], $transactionUuid)
            || $record['payment_status'] !== 'pending'
            || $record['order_status'] !== 'pending'
        ) {
            throw new ServiceAuthorizationException('The local eSewa response does not match the active payment attempt.');
        }
        $amount = $formatAmount($record['final_amount']);
        if (abs((float) $record['paid_amount'] - (float) $amount) > 0.009) {
            throw new InvalidArgumentException('Payment amount does not match the pending order.');
        }
        $expectedDemoToken = $demoToken($orderId, (int) $student['id'], $amount, $transactionUuid, $secretKey);
        if (!hash_equals($expectedDemoToken, $providedDemoToken)) {
            throw new ServiceAuthorizationException('The local eSewa payment token could not be verified.');
        }

        $verifiedReference = 'DEMO-' . strtoupper(bin2hex(random_bytes(5)));
        $paymentUpdate = $database->prepare(
            'UPDATE payments SET payment_status=\'paid\',transaction_id=:transaction_id,verified_by=NULL,verified_at=NOW() '
            . 'WHERE id=:id AND payment_status=\'pending\''
        );
        $paymentUpdate->execute(['transaction_id' => $verifiedReference, 'id' => (int) $record['id']]);
        $orderUpdate = $database->prepare('UPDATE orders SET order_status=\'paid\' WHERE id=:order_id AND order_status=\'pending\'');
        $orderUpdate->execute(['order_id' => $orderId]);
        if ($paymentUpdate->rowCount() !== 1 || $orderUpdate->rowCount() !== 1) {
            throw new ServiceAuthorizationException('The local demo payment could not be finalized safely.');
        }

        $commissionRate = 20.0;
        $commissionSetting = $database->prepare('SELECT setting_value FROM site_settings WHERE setting_key=\'platform_commission_rate\' LIMIT 1');
        $commissionSetting->execute();
        $commissionValue = $commissionSetting->fetchColumn();
        if ($commissionValue !== false && is_numeric($commissionValue)) {
            $commissionRate = max(0, min(100, (float) $commissionValue));
        }

        $itemsQuery = $database->prepare('SELECT * FROM order_items WHERE order_id=:order_id ORDER BY id FOR UPDATE');
        $itemsQuery->execute(['order_id' => $orderId]);
        $items = $itemsQuery->fetchAll();
        if ($items === []) {
            throw new InvalidArgumentException('The paid order does not contain any courses.');
        }

        $enrollment = $database->prepare(
            'INSERT INTO enrollments (student_id,course_id,order_id,payment_id,access_type,status,granted_by,granted_at) '
            . 'VALUES (:student_id,:course_id,:order_id,:payment_id,\'lifetime\',\'active\',NULL,NOW()) '
            . 'ON DUPLICATE KEY UPDATE order_id=VALUES(order_id),payment_id=VALUES(payment_id),access_type=\'lifetime\','
            . 'status=\'active\',granted_by=NULL,granted_at=NOW(),revoked_by_admin=NULL,revoked_at=NULL'
        );
        $earning = $database->prepare(
            'INSERT INTO instructor_earnings (instructor_id,course_id,student_id,order_id,order_item_id,payment_id,gross_amount,commission_rate,commission_amount,instructor_amount,earning_status) '
            . 'VALUES (:instructor_id,:course_id,:student_id,:order_id,:order_item_id,:payment_id,:gross_amount,:commission_rate,:commission_amount,:instructor_amount,\'available\') '
            . 'ON DUPLICATE KEY UPDATE gross_amount=VALUES(gross_amount),commission_rate=VALUES(commission_rate),commission_amount=VALUES(commission_amount),'
            . 'instructor_amount=VALUES(instructor_amount),earning_status=IF(earning_status=\'paid\',earning_status,\'available\')'
        );
        $notify = $database->prepare('INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:user_id,:title,:message,:type)');

        foreach ($items as $item) {
            $gross = (float) $item['final_price'];
            $commission = round($gross * $commissionRate / 100, 2);
            $instructorAmount = round($gross - $commission, 2);
            $enrollment->execute([
                'student_id' => (int) $record['student_id'],
                'course_id' => (int) $item['course_id'],
                'order_id' => $orderId,
                'payment_id' => (int) $record['id'],
            ]);
            $earning->execute([
                'instructor_id' => (int) $item['instructor_id'],
                'course_id' => (int) $item['course_id'],
                'student_id' => (int) $record['student_id'],
                'order_id' => $orderId,
                'order_item_id' => (int) $item['id'],
                'payment_id' => (int) $record['id'],
                'gross_amount' => number_format($gross, 2, '.', ''),
                'commission_rate' => number_format($commissionRate, 2, '.', ''),
                'commission_amount' => number_format($commission, 2, '.', ''),
                'instructor_amount' => number_format($instructorAmount, 2, '.', ''),
            ]);
            $notify->execute([
                'user_id' => (int) $item['instructor_id'],
                'title' => 'New verified enrollment',
                'message' => 'A local sandbox Student purchase created an enrollment in your course.',
                'type' => 'new_enrollment',
            ]);
        }

        $notify->execute([
            'user_id' => (int) $record['student_id'],
            'title' => 'Course access activated',
            'message' => 'The local eSewa sandbox payment for order #' . $orderId . ' was verified. Your purchased courses are now available for life.',
            'type' => 'enrollment_granted',
        ]);
        if ($record['coupon_id'] !== null) {
            $coupon = $database->prepare('UPDATE coupons SET used_count=used_count+1 WHERE id=:id AND (usage_limit IS NULL OR used_count<usage_limit)');
            $coupon->execute(['id' => (int) $record['coupon_id']]);
        }

        $database->commit();
        $respond([
            'message' => 'Local eSewa sandbox payment completed. Course access is now active.',
            'data' => ['payment_id' => (int) $record['id'], 'order_id' => $orderId, 'status' => 'paid'],
        ]);
    }

    $respond(['error' => 'Local eSewa simulator route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
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
    error_log('Local eSewa demo database failure: ' . $exception->getMessage());
    $respond(['error' => 'Local eSewa demo payment could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Local eSewa demo failure: ' . $exception->getMessage());
    $respond(['error' => 'The local eSewa demo is unavailable.'], 503);
}
