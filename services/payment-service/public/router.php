<?php

declare(strict_types=1);
use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$rawInput = (string) file_get_contents('php://input');

/*
 * Payment finalization lives in index.php and exits after writing JSON. A
 * shutdown callback therefore queues the Instructor payout only after a
 * successful verification/approval response and only when the database says
 * the payment is actually paid. AutomaticPayout is idempotent per earning.
 */
$payoutPaymentId = 0;
$payoutOrderId = 0;
$shouldDispatchPayout = false;

if ($method === 'POST' && preg_match('#^/api/v1/payments/(\d+)/approve$#', $path, $match) === 1) {
    $payoutPaymentId = (int) $match[1];
    $shouldDispatchPayout = $payoutPaymentId > 0;
} elseif ($method === 'POST' && $path === '/api/v1/payments/esewa/verify') {
    try {
        $input = json_decode($rawInput !== '' ? $rawInput : '{}', true, 32, JSON_THROW_ON_ERROR);
        $encoded = is_array($input) ? trim((string) ($input['data'] ?? '')) : '';
        $decoded = $encoded !== '' ? base64_decode($encoded, true) : false;
        $payload = is_string($decoded) ? json_decode($decoded, true, 32, JSON_THROW_ON_ERROR) : [];
        $uuid = is_array($payload) ? trim((string) ($payload['transaction_uuid'] ?? '')) : '';
        if (preg_match('/^CH-(\d+)-[A-Za-z0-9-]+$/', $uuid, $match) === 1) {
            $payoutOrderId = (int) $match[1];
            $shouldDispatchPayout = true;
        }
    } catch (Throwable) {
        // The verification endpoint will return its own validation error.
    }
} elseif ($method === 'POST' && $path === '/api/v1/payments/esewa/demo-complete') {
    try {
        $input = json_decode($rawInput !== '' ? $rawInput : '{}', true, 32, JSON_THROW_ON_ERROR);
        $payoutOrderId = is_array($input) ? (int) ($input['order_id'] ?? 0) : 0;
        $shouldDispatchPayout = $payoutOrderId > 0;
    } catch (Throwable) {
        // The local simulator endpoint will return its own validation error.
    }
} elseif ($method === 'POST' && $path === '/api/v1/payments/khalti/verify') {
    try {
        $input = json_decode($rawInput !== '' ? $rawInput : '{}', true, 32, JSON_THROW_ON_ERROR);
        if (is_array($input)) {
            $payoutOrderId = (int) ($input['order_id'] ?? 0);
            if ($payoutOrderId < 1
                && preg_match('/^COURSEHUB-(\d+)-[A-Za-z0-9]+$/', trim((string) ($input['purchase_order_id'] ?? '')), $match) === 1
            ) {
                $payoutOrderId = (int) $match[1];
            }
            $shouldDispatchPayout = $payoutOrderId > 0;
        }
    } catch (Throwable) {
        // The verification endpoint will return its own validation error.
    }
}

if ($shouldDispatchPayout) {
    register_shutdown_function(static function () use ($payoutPaymentId, $payoutOrderId): void {
        $status = http_response_code();
        if ($status >= 400) {
            return;
        }

        try {
            require_once dirname(__DIR__, 2) . '/_shared/Database.php';
            require_once dirname(__DIR__) . '/src/GatewayClient.php';
            require_once dirname(__DIR__) . '/src/AutomaticPayout.php';

            $database = Database::connect();
            $paymentId = $payoutPaymentId;
            if ($paymentId < 1 && $payoutOrderId > 0) {
                $statement = $database->prepare("SELECT id FROM payments WHERE order_id=:order_id AND payment_status='paid' LIMIT 1");
                $statement->execute(['order_id' => $payoutOrderId]);
                $paymentId = (int) ($statement->fetchColumn() ?: 0);
            }
            if ($paymentId > 0) {
                $paid = $database->prepare("SELECT id FROM payments WHERE id=:id AND payment_status='paid' LIMIT 1");
                $paid->execute(['id' => $paymentId]);
                if ($paid->fetchColumn() !== false) {
                    AutomaticPayout::settleForPayment($database, $paymentId);
                }
            }
        } catch (Throwable $exception) {
            error_log('Verified-payment payout dispatch failed: ' . $exception->getMessage());
        }
    });
}

if (($path === '/api/v1/payments/options' && $method === 'GET')
    || ($path === '/api/v1/payments/admin/gateways' && in_array($method, ['GET', 'POST'], true))
) {
    require __DIR__ . '/gateway-settings.php';
    exit;
}

if (in_array($path, ['/api/v1/payments/esewa/initiate', '/api/v1/payments/khalti/initiate'], true) && $method === 'POST') {
    require_once dirname(__DIR__, 2) . '/_shared/Database.php';
    require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        $database = Database::connect();
        ServiceAuth::requireUser($database, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'student');
        $provider = str_contains($path, '/esewa/') ? 'esewa' : 'khalti';
        $statement = $database->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1');
        $statement->execute(['key' => $provider . '_enabled']);
        if (trim((string) ($statement->fetchColumn() ?: '')) !== '1') {
            http_response_code(422);
            echo json_encode(['error' => ucfirst($provider) . ' payments are not enabled by the platform administrator.'], JSON_THROW_ON_ERROR);
            exit;
        }
    } catch (ServiceAuthenticationException $exception) {
        http_response_code(401);
        echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
        exit;
    } catch (ServiceAuthorizationException $exception) {
        http_response_code(403);
        echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
        exit;
    } catch (Throwable $exception) {
        error_log('Payment gateway availability check failed: ' . $exception->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'Payment gateway availability could not be verified.'], JSON_THROW_ON_ERROR);
        exit;
    }
}

$localDemoFlag = trim((string) getenv('ESEWA_LOCAL_DEMO'));
$localDemoEnabled = $localDemoFlag === ''
    ? true
    : (filter_var($localDemoFlag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true);
$useLocalEsewaDemo = strtolower(trim((string) (getenv('APP_ENV') ?: 'local'))) !== 'production'
    && strtolower(trim((string) (getenv('ESEWA_ENV') ?: 'sandbox'))) !== 'production'
    && $localDemoEnabled;

if ($useLocalEsewaDemo
    && $method === 'POST'
    && in_array($path, ['/api/v1/payments/esewa/initiate', '/api/v1/payments/esewa/demo-complete'], true)
) {
    require __DIR__ . '/esewa-local-demo.php';
    exit;
}

if ($path === '/api/v1/payments/manual' && $method === 'POST') {
    require __DIR__ . '/manual.php';
    exit;
}

require __DIR__ . '/index.php';
