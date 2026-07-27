<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';
require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';
require_once dirname(__DIR__) . '/src/GatewayClient.php';

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

$publicAppUrl = static function () use ($environment): string {
    $url = rtrim($environment('APP_URL'), '/');
    $parts = parse_url($url);
    if (!is_array($parts)) {
        throw new RuntimeException('APP_URL is not configured with a valid public application URL.');
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || trim((string) ($parts['host'] ?? '')) === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
        throw new RuntimeException('APP_URL is not configured with a valid public application URL.');
    }
    return $url;
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

$ownedOrder = static function (PDO $database, int $orderId, int $studentId): array {
    $statement = $database->prepare(
        'SELECT o.id, o.student_id, o.final_amount, o.order_status, u.full_name, u.email, u.phone '
        . 'FROM orders o INNER JOIN users u ON u.id = o.student_id '
        . 'WHERE o.id = :id AND o.student_id = :student_id LIMIT 1'
    );
    $statement->execute(['id' => $orderId, 'student_id' => $studentId]);
    $order = $statement->fetch();
    if (!is_array($order)) {
        throw new ServiceAuthorizationException('That order is not available in your account.');
    }
    if ($order['order_status'] !== 'pending') {
        throw new InvalidArgumentException('Only a pending order can be paid.');
    }
    return $order;
};

$upsertAutomaticPayment = static function (
    PDO $database,
    int $orderId,
    int $studentId,
    string $provider,
    string $reference,
    string $amount,
): int {
    if (!in_array($provider, ['esewa', 'khalti'], true) || preg_match('/^[A-Za-z0-9._:-]{4,150}$/', $reference) !== 1) {
        throw new InvalidArgumentException('The gateway payment reference is invalid.');
    }

    $database->beginTransaction();
    $order = $database->prepare('SELECT id, final_amount, order_status FROM orders WHERE id = :id AND student_id = :student_id FOR UPDATE');
    $order->execute(['id' => $orderId, 'student_id' => $studentId]);
    $record = $order->fetch();
    if (!is_array($record)) {
        throw new ServiceAuthorizationException('That order is not available in your account.');
    }
    if ($record['order_status'] !== 'pending' || abs((float) $record['final_amount'] - (float) $amount) > 0.009) {
        throw new InvalidArgumentException('The order amount or state changed before payment could begin.');
    }

    $existing = $database->prepare('SELECT id, payment_type, payment_status FROM payments WHERE order_id = :order_id FOR UPDATE');
    $existing->execute(['order_id' => $orderId]);
    $payment = $existing->fetch();
    if (is_array($payment)) {
        if ($payment['payment_status'] === 'paid') {
            throw new InvalidArgumentException('This order has already been paid.');
        }
        if ($payment['payment_type'] === 'manual' && $payment['payment_status'] === 'pending') {
            throw new InvalidArgumentException('A manual payment is already awaiting administrator verification.');
        }
        $update = $database->prepare(
            'UPDATE payments SET payment_method = :payment_method, payment_type = \'automatic\', transaction_id = :transaction_id, '
            . 'paid_amount = :paid_amount, payment_status = \'pending\', verified_by = NULL, verified_at = NULL '
            . 'WHERE id = :id'
        );
        $update->execute([
            'payment_method' => $provider,
            'transaction_id' => $reference,
            'paid_amount' => $amount,
            'id' => (int) $payment['id'],
        ]);
        $paymentId = (int) $payment['id'];
    } else {
        $insert = $database->prepare(
            'INSERT INTO payments (order_id, student_id, payment_method, payment_type, transaction_id, paid_amount, payment_status) '
            . 'VALUES (:order_id, :student_id, :payment_method, \'automatic\', :transaction_id, :paid_amount, \'pending\')'
        );
        $insert->execute([
            'order_id' => $orderId,
            'student_id' => $studentId,
            'payment_method' => $provider,
            'transaction_id' => $reference,
            'paid_amount' => $amount,
        ]);
        $paymentId = (int) $database->lastInsertId();
    }

    $database->commit();
    return $paymentId;
};

$completePayment = static function (
    PDO $database,
    array $record,
    int $paymentId,
    string $transactionId,
    ?int $verifiedBy,
    string $verificationSource,
): array {
    if (!$database->inTransaction()) {
        throw new RuntimeException('Payment finalization requires an active database transaction.');
    }
    if (preg_match('/^[A-Za-z0-9._:-]{3,150}$/', $transactionId) !== 1) {
        throw new InvalidArgumentException('The verified gateway transaction reference is invalid.');
    }
    if ($record['payment_status'] !== 'pending' || $record['order_status'] !== 'pending') {
        throw new ServiceAuthorizationException('This payment has already been processed.');
    }
    if (abs((float) $record['paid_amount'] - (float) $record['final_amount']) > 0.009) {
        throw new InvalidArgumentException('Payment amount does not match the pending order.');
    }

    $update = $database->prepare(
        'UPDATE payments SET payment_status = \'paid\', transaction_id = :transaction_id, verified_by = :verified_by, verified_at = NOW() '
        . 'WHERE id = :id AND payment_status = \'pending\''
    );
    $update->execute(['transaction_id' => $transactionId, 'verified_by' => $verifiedBy, 'id' => $paymentId]);
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
    if ($orderItems === []) {
        throw new InvalidArgumentException('The paid order does not contain any courses.');
    }

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
            'granted_by' => $verifiedBy,
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
        'message' => 'Payment for order #' . $record['order_id'] . ' was verified through ' . $verificationSource . '. Your purchased courses are now available for life.',
        'type' => 'enrollment_granted',
    ]);
    if ($record['coupon_id'] !== null) {
        $coupon = $database->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id AND (usage_limit IS NULL OR used_count < usage_limit)');
        $coupon->execute(['id' => (int) $record['coupon_id']]);
    }

    $database->commit();
    return ['payment_id' => $paymentId, 'order_id' => (int) $record['order_id'], 'status' => 'paid'];
};

$lockedPayment = static function (PDO $database, int $orderId, int $studentId): array {
    $statement = $database->prepare(
        'SELECT p.*, o.final_amount, o.order_status, o.coupon_id FROM payments p '
        . 'INNER JOIN orders o ON o.id = p.order_id '
        . 'WHERE p.order_id = :order_id AND p.student_id = :student_id FOR UPDATE'
    );
    $statement->execute(['order_id' => $orderId, 'student_id' => $studentId]);
    $record = $statement->fetch();
    if (!is_array($record)) {
        throw new ServiceAuthorizationException('The payment attempt was not found in your account.');
    }
    return $record;
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

    if ($path === '/api/v1/payments/esewa/initiate' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $orderId = (int) ($input['order_id'] ?? 0);
        if ($orderId < 1) {
            throw new InvalidArgumentException('Choose a valid pending order.');
        }
        $order = $ownedOrder($database, $orderId, (int) $student['id']);
        $amount = $formatAmount($order['final_amount']);
        $productCode = $environment('ESEWA_PRODUCT_CODE');
        $secretKey = $environment('ESEWA_SECRET_KEY');
        if ($productCode === '' || $secretKey === '') {
            throw new RuntimeException('eSewa credentials are not configured.');
        }
        $transactionUuid = 'CH-' . $orderId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $signaturePayload = ['total_amount' => $amount, 'transaction_uuid' => $transactionUuid, 'product_code' => $productCode];
        $signature = GatewayClient::signFields($signaturePayload, ['total_amount', 'transaction_uuid', 'product_code'], $secretKey);
        $upsertAutomaticPayment($database, $orderId, (int) $student['id'], 'esewa', $transactionUuid, $amount);

        $production = strtolower($environment('ESEWA_ENV', 'sandbox')) === 'production';
        $paymentUrl = $environment('ESEWA_PAYMENT_URL', $production ? 'https://epay.esewa.com.np/api/epay/main/v2/form' : 'https://rc-epay.esewa.com.np/api/epay/main/v2/form');
        $host = strtolower((string) (parse_url($paymentUrl, PHP_URL_HOST) ?: ''));
        if (!in_array($host, ['rc-epay.esewa.com.np', 'epay.esewa.com.np'], true)) {
            throw new RuntimeException('The configured eSewa payment URL is not trusted.');
        }
        $callback = $publicAppUrl() . '/student/payment';
        $respond(['data' => [
            'action' => $paymentUrl,
            'method' => 'POST',
            'fields' => [
                'amount' => $amount,
                'tax_amount' => '0',
                'total_amount' => $amount,
                'transaction_uuid' => $transactionUuid,
                'product_code' => $productCode,
                'product_service_charge' => '0',
                'product_delivery_charge' => '0',
                'success_url' => $callback,
                'failure_url' => $callback . '?gateway=esewa&result=failure&order=' . $orderId,
                'signed_field_names' => 'total_amount,transaction_uuid,product_code',
                'signature' => $signature,
            ],
        ]], 201);
    }

    if ($path === '/api/v1/payments/esewa/verify' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $encoded = trim((string) ($input['data'] ?? ''));
        if ($encoded === '' || strlen($encoded) > 8192) {
            throw new InvalidArgumentException('eSewa returned an invalid payment response.');
        }
        $raw = base64_decode($encoded, true);
        if (!is_string($raw)) {
            throw new InvalidArgumentException('eSewa returned an invalid payment response.');
        }
        $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('eSewa returned an invalid payment response.');
        }
        $secretKey = $environment('ESEWA_SECRET_KEY');
        $productCode = $environment('ESEWA_PRODUCT_CODE');
        if (!GatewayClient::verifySignedPayload($payload, $secretKey)) {
            throw new InvalidArgumentException('eSewa response signature verification failed.');
        }
        if (!hash_equals('COMPLETE', strtoupper(trim((string) ($payload['status'] ?? '')))) || !hash_equals($productCode, trim((string) ($payload['product_code'] ?? '')))) {
            throw new InvalidArgumentException('eSewa did not report a completed payment.');
        }
        $transactionUuid = trim((string) ($payload['transaction_uuid'] ?? ''));
        if (preg_match('/^CH-(\d+)-[A-Za-z0-9-]+$/', $transactionUuid, $match) !== 1) {
            throw new InvalidArgumentException('eSewa returned an unknown order reference.');
        }
        $orderId = (int) $match[1];
        $snapshot = $database->prepare(
            'SELECT p.*, o.final_amount, o.order_status, o.coupon_id FROM payments p INNER JOIN orders o ON o.id = p.order_id '
            . 'WHERE p.order_id = :order_id AND p.student_id = :student_id LIMIT 1'
        );
        $snapshot->execute(['order_id' => $orderId, 'student_id' => (int) $student['id']]);
        $record = $snapshot->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('The eSewa payment attempt was not found.');
        }
        if ($record['payment_status'] === 'paid' && $record['order_status'] === 'paid' && $record['payment_method'] === 'esewa') {
            $respond(['message' => 'eSewa payment was already verified.', 'data' => ['payment_id' => (int) $record['id'], 'order_id' => $orderId, 'status' => 'paid']]);
        }
        if ($record['payment_method'] !== 'esewa' || $record['payment_type'] !== 'automatic' || !hash_equals((string) $record['transaction_id'], $transactionUuid)) {
            throw new ServiceAuthorizationException('The eSewa response does not match the active payment attempt.');
        }
        $expectedAmount = $formatAmount($record['final_amount']);
        $callbackAmount = number_format((float) str_replace(',', '', (string) ($payload['total_amount'] ?? '0')), 2, '.', '');
        if (!hash_equals($expectedAmount, $callbackAmount)) {
            throw new InvalidArgumentException('eSewa payment amount does not match the order.');
        }

        $production = strtolower($environment('ESEWA_ENV', 'sandbox')) === 'production';
        $statusBase = $environment('ESEWA_STATUS_URL', $production ? 'https://esewa.com.np/api/epay/transaction/status/' : 'https://rc.esewa.com.np/api/epay/transaction/status/');
        $status = GatewayClient::getJson(rtrim($statusBase, '/') . '/?' . http_build_query([
            'product_code' => $productCode,
            'total_amount' => $expectedAmount,
            'transaction_uuid' => $transactionUuid,
        ], '', '&', PHP_QUERY_RFC3986));
        $statusAmount = number_format((float) str_replace(',', '', (string) ($status['total_amount'] ?? '0')), 2, '.', '');
        if (strtoupper(trim((string) ($status['status'] ?? ''))) !== 'COMPLETE'
            || !hash_equals($productCode, trim((string) ($status['product_code'] ?? '')))
            || !hash_equals($transactionUuid, trim((string) ($status['transaction_uuid'] ?? '')))
            || !hash_equals($expectedAmount, $statusAmount)
        ) {
            throw new InvalidArgumentException('eSewa could not verify this payment as complete.');
        }
        $verifiedReference = trim((string) ($payload['transaction_code'] ?? $status['ref_id'] ?? ''));

        $database->beginTransaction();
        $record = $lockedPayment($database, $orderId, (int) $student['id']);
        if ($record['payment_status'] === 'paid' && $record['order_status'] === 'paid') {
            $database->commit();
            $respond(['message' => 'eSewa payment was already verified.', 'data' => ['payment_id' => (int) $record['id'], 'order_id' => $orderId, 'status' => 'paid']]);
        }
        if ($record['payment_method'] !== 'esewa' || !hash_equals((string) $record['transaction_id'], $transactionUuid)) {
            throw new ServiceAuthorizationException('The active eSewa payment changed before verification completed.');
        }
        $result = $completePayment($database, $record, (int) $record['id'], $verifiedReference, null, 'eSewa');
        $respond(['message' => 'eSewa payment verified. Course access is now active.', 'data' => $result]);
    }

    if ($path === '/api/v1/payments/khalti/initiate' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $orderId = (int) ($input['order_id'] ?? 0);
        if ($orderId < 1) {
            throw new InvalidArgumentException('Choose a valid pending order.');
        }
        $order = $ownedOrder($database, $orderId, (int) $student['id']);
        $amount = $formatAmount($order['final_amount']);
        $amountPaisa = (int) round((float) $amount * 100);
        $secretKey = $environment('KHALTI_SECRET_KEY');
        if ($secretKey === '') {
            throw new RuntimeException('Khalti credentials are not configured.');
        }
        $production = strtolower($environment('KHALTI_ENV', 'sandbox')) === 'production';
        $apiBase = rtrim($environment('KHALTI_API_URL', $production ? 'https://khalti.com/api/v2' : 'https://dev.khalti.com/api/v2'), '/');
        $appUrl = $publicAppUrl();
        $websiteUrl = rtrim($environment('KHALTI_WEBSITE_URL', $appUrl), '/');
        $purchaseOrderId = 'COURSEHUB-' . $orderId . '-' . bin2hex(random_bytes(5));
        $customer = ['name' => (string) $order['full_name'], 'email' => (string) $order['email']];
        $phone = trim((string) ($order['phone'] ?? ''));
        if (preg_match('/^[0-9+ -]{7,20}$/', $phone) === 1) {
            $customer['phone'] = $phone;
        }
        $gateway = GatewayClient::postJson($apiBase . '/epayment/initiate/', [
            'return_url' => $appUrl . '/student/payment',
            'website_url' => $websiteUrl,
            'amount' => $amountPaisa,
            'purchase_order_id' => $purchaseOrderId,
            'purchase_order_name' => 'CourseHub order #' . $orderId,
            'customer_info' => $customer,
        ], ['Authorization: Key ' . $secretKey]);
        $pidx = trim((string) ($gateway['pidx'] ?? ''));
        $paymentUrl = trim((string) ($gateway['payment_url'] ?? ''));
        $paymentHost = strtolower((string) (parse_url($paymentUrl, PHP_URL_HOST) ?: ''));
        if (preg_match('/^[A-Za-z0-9_-]{6,150}$/', $pidx) !== 1 || !in_array($paymentHost, ['test-pay.khalti.com', 'pay.khalti.com'], true) || strtolower((string) parse_url($paymentUrl, PHP_URL_SCHEME)) !== 'https') {
            throw new RuntimeException('Khalti returned an invalid checkout response.');
        }
        $upsertAutomaticPayment($database, $orderId, (int) $student['id'], 'khalti', $pidx, $amount);
        $respond(['data' => ['payment_url' => $paymentUrl, 'pidx' => $pidx, 'expires_at' => $gateway['expires_at'] ?? null]], 201);
    }

    if ($path === '/api/v1/payments/khalti/verify' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $pidx = trim((string) ($input['pidx'] ?? ''));
        $orderId = (int) ($input['order_id'] ?? 0);
        $purchaseOrderId = trim((string) ($input['purchase_order_id'] ?? ''));
        if ($orderId < 1 && preg_match('/^COURSEHUB-(\d+)-[A-Za-z0-9]+$/', $purchaseOrderId, $match) === 1) {
            $orderId = (int) $match[1];
        }
        if ($orderId < 1 || preg_match('/^[A-Za-z0-9_-]{6,150}$/', $pidx) !== 1) {
            throw new InvalidArgumentException('Khalti returned an invalid payment reference.');
        }

        $snapshot = $database->prepare(
            'SELECT p.*, o.final_amount, o.order_status, o.coupon_id FROM payments p INNER JOIN orders o ON o.id = p.order_id '
            . 'WHERE p.order_id = :order_id AND p.student_id = :student_id LIMIT 1'
        );
        $snapshot->execute(['order_id' => $orderId, 'student_id' => (int) $student['id']]);
        $record = $snapshot->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('The Khalti payment attempt was not found.');
        }
        if ($record['payment_status'] === 'paid' && $record['order_status'] === 'paid' && $record['payment_method'] === 'khalti') {
            $respond(['message' => 'Khalti payment was already verified.', 'data' => ['payment_id' => (int) $record['id'], 'order_id' => $orderId, 'status' => 'paid']]);
        }
        if ($record['payment_method'] !== 'khalti' || $record['payment_type'] !== 'automatic' || !hash_equals((string) $record['transaction_id'], $pidx)) {
            throw new ServiceAuthorizationException('The Khalti response does not match the active payment attempt.');
        }

        $secretKey = $environment('KHALTI_SECRET_KEY');
        if ($secretKey === '') {
            throw new RuntimeException('Khalti credentials are not configured.');
        }
        $production = strtolower($environment('KHALTI_ENV', 'sandbox')) === 'production';
        $apiBase = rtrim($environment('KHALTI_API_URL', $production ? 'https://khalti.com/api/v2' : 'https://dev.khalti.com/api/v2'), '/');
        $lookup = GatewayClient::postJson($apiBase . '/epayment/lookup/', ['pidx' => $pidx], ['Authorization: Key ' . $secretKey]);
        $expectedPaisa = (int) round((float) $record['final_amount'] * 100);
        $verifiedTransaction = trim((string) ($lookup['transaction_id'] ?? ''));
        if (trim((string) ($lookup['status'] ?? '')) !== 'Completed'
            || (int) ($lookup['total_amount'] ?? 0) !== $expectedPaisa
            || preg_match('/^[A-Za-z0-9._:-]{3,150}$/', $verifiedTransaction) !== 1
        ) {
            throw new InvalidArgumentException('Khalti could not verify this payment as completed.');
        }

        $database->beginTransaction();
        $record = $lockedPayment($database, $orderId, (int) $student['id']);
        if ($record['payment_status'] === 'paid' && $record['order_status'] === 'paid') {
            $database->commit();
            $respond(['message' => 'Khalti payment was already verified.', 'data' => ['payment_id' => (int) $record['id'], 'order_id' => $orderId, 'status' => 'paid']]);
        }
        if ($record['payment_method'] !== 'khalti' || !hash_equals((string) $record['transaction_id'], $pidx)) {
            throw new ServiceAuthorizationException('The active Khalti payment changed before verification completed.');
        }
        $result = $completePayment($database, $record, (int) $record['id'], $verifiedTransaction, null, 'Khalti');
        $respond(['message' => 'Khalti payment verified. Course access is now active.', 'data' => $result]);
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

        $existing = $database->prepare('SELECT id, payment_type, payment_status FROM payments WHERE order_id = :order_id FOR UPDATE');
        $existing->execute(['order_id' => $orderId]);
        $previous = $existing->fetch();
        if (is_array($previous)) {
            if ($previous['payment_type'] !== 'automatic' || $previous['payment_status'] !== 'pending') {
                throw new InvalidArgumentException('A payment has already been submitted for this order.');
            }
            $payment = $database->prepare(
                'UPDATE payments SET payment_method = \'manual\', payment_type = \'manual\', transaction_id = :transaction_id, '
                . 'paid_amount = :paid_amount, payment_status = \'pending\', verified_by = NULL, verified_at = NULL WHERE id = :id'
            );
            $payment->execute([
                'transaction_id' => $transactionId,
                'paid_amount' => $record['final_amount'],
                'id' => (int) $previous['id'],
            ]);
            $paymentId = (int) $previous['id'];
        } else {
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
        }
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
            . 'LEFT JOIN payment_proofs pp ON pp.payment_id = p.id WHERE p.payment_status = \'pending\' AND p.payment_type = \'manual\' '
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
        if ($record['payment_type'] !== 'manual') {
            throw new ServiceAuthorizationException('Automatic gateway payments cannot be manually approved or rejected.');
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

        $result = $completePayment($database, $record, $paymentId, (string) $record['transaction_id'], (int) $admin['id'], 'administrator review');
        $respond(['message' => 'Payment approved and lifetime course access granted.', 'data' => $result]);
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
} catch (DomainException $exception) {
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
    error_log('Payment database failure: ' . $exception->getMessage());
    $respond(['error' => $exception->getCode() === '23000' ? 'That transaction reference or order is already registered.' : 'Payment request could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Payment service failure: ' . $exception->getMessage());
    $respond(['error' => 'Payment service is unavailable.'], 503);
}
