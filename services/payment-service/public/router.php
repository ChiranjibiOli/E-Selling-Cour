<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

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

if ($path === '/api/v1/payments/manual' && $method === 'POST') {
    require __DIR__ . '/manual.php';
    exit;
}

require __DIR__ . '/index.php';
