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

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$database = null;
try {
    $raw = (string) file_get_contents('php://input');
    $input = json_decode($raw !== '' ? $raw : '{}', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    $database = Database::connect();
    $student = ServiceAuth::requireUser($database, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'student');
    $orderId = filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT);
    $transactionId = trim((string) ($input['transaction_id'] ?? ''));
    $proofImage = trim((string) ($input['proof_image'] ?? ''));
    $note = trim((string) ($input['note'] ?? ''));

    if ($orderId === false || $orderId < 1) {
        throw new InvalidArgumentException('Choose a valid pending order.');
    }
    if (mb_strlen($transactionId) < 3 || mb_strlen($transactionId) > 150 || preg_match('/^[\p{L}\p{N} ._:\/-]+$/u', $transactionId) !== 1) {
        throw new InvalidArgumentException('Enter the real payment transaction reference.');
    }
    if (preg_match('#^private/payment-proofs/[a-f0-9]{40}\.(?:jpg|png|webp|pdf)$#', $proofImage) !== 1) {
        throw new InvalidArgumentException('Upload the actual validated payment screenshot or PDF receipt.');
    }
    if (mb_strlen($note) > 1000 || str_contains($note, "\0")) {
        throw new InvalidArgumentException('The payment note is too long or invalid.');
    }

    $database->beginTransaction();
    $order = $database->prepare('SELECT id,final_amount,order_status FROM orders WHERE id=:id AND student_id=:student_id FOR UPDATE');
    $order->execute(['id' => (int) $orderId, 'student_id' => $student['id']]);
    $record = $order->fetch();
    if (!is_array($record)) {
        throw new ServiceAuthorizationException('That order is not available in your account.');
    }
    if ($record['order_status'] !== 'pending') {
        throw new ServiceAuthorizationException('Only a pending order can receive payment proof.');
    }
    if ((float) $record['final_amount'] <= 0) {
        throw new InvalidArgumentException('This order does not require manual payment.');
    }
    $existing = $database->prepare('SELECT id FROM payments WHERE order_id=:order_id LIMIT 1');
    $existing->execute(['order_id' => (int) $orderId]);
    if ($existing->fetch() !== false) {
        throw new InvalidArgumentException('A payment has already been submitted for this order.');
    }

    $payment = $database->prepare(
        'INSERT INTO payments (order_id,student_id,payment_method,payment_type,transaction_id,paid_amount,payment_status) '
        . 'VALUES (:order_id,:student_id,\'manual\',\'manual\',:transaction_id,:paid_amount,\'pending\')'
    );
    $payment->execute([
        'order_id' => (int) $orderId,
        'student_id' => $student['id'],
        'transaction_id' => $transactionId,
        'paid_amount' => $record['final_amount'],
    ]);
    $paymentId = (int) $database->lastInsertId();
    $proof = $database->prepare('INSERT INTO payment_proofs (payment_id,proof_image,note) VALUES (:payment_id,:proof_image,:note)');
    $proof->execute(['payment_id' => $paymentId, 'proof_image' => $proofImage, 'note' => $note !== '' ? $note : null]);
    $notification = $database->prepare(
        'INSERT INTO notifications (user_id,title,message,notification_type) '
        . 'SELECT id,\'Payment awaiting review\',:message,\'payment_submitted\' FROM users WHERE role=\'admin\' AND status=\'active\''
    );
    $notification->execute(['message' => 'Manual payment #' . $paymentId . ' has a protected receipt ready for verification.']);
    $database->commit();
    $respond(['message' => 'Payment receipt submitted for administrator verification.', 'data' => ['payment_id' => $paymentId, 'status' => 'pending']], 201);
} catch (ServiceAuthenticationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (Throwable $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    error_log('Manual payment failure: ' . $exception->getMessage());
    $respond(['error' => 'The payment receipt could not be submitted.'], 503);
}
