<?php

declare(strict_types=1);

use CourseHub\Identity\Features\Login\LoginAuthenticationException;
use CourseHub\Identity\Features\Login\LoginHandler;
use CourseHub\Identity\Features\Login\LoginRateLimitException;
use CourseHub\Identity\Features\Login\LoginRateLimiter;
use CourseHub\Identity\Features\Login\LoginValidationException;
use CourseHub\Identity\Features\Session\SessionAuthenticationException;
use CourseHub\Identity\Features\Session\SessionHandler;
use CourseHub\Identity\Infrastructure\Database;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Features/Login/LoginRateLimiter.php';
require_once dirname(__DIR__) . '/src/Features/Login/LoginHandler.php';
require_once dirname(__DIR__) . '/src/Features/Session/SessionHandler.php';

header('Content-Type: application/json; charset=utf-8');
$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
header('X-Request-ID: ' . $requestId);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'service' => 'identity-service', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($path === '/api/v1/auth/login' && $method === 'POST') {
        $rawBody = file_get_contents('php://input');
        $input = json_decode($rawBody ?: '{}', true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new LoginValidationException('Request body must be a JSON object.');
        }

        $handler = new LoginHandler($database, new LoginRateLimiter($database));
        $result = $handler->handle(
            $input,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500)
        );

        http_response_code(200);
        echo json_encode($result + ['request_id' => $requestId], JSON_THROW_ON_ERROR);
        exit;
    }

    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $sessionHandler = new SessionHandler($database);

    if ($path === '/api/v1/auth/session' && $method === 'GET') {
        $result = $sessionHandler->verify($authorization);
        http_response_code(200);
        echo json_encode($result + ['request_id' => $requestId], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($path === '/api/v1/auth/logout' && $method === 'POST') {
        $sessionHandler->logout($authorization);
        http_response_code(200);
        echo json_encode(['message' => 'Logged out.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Identity route not found.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (LoginRateLimitException $exception) {
    http_response_code(429);
    header('Retry-After: 900');
    echo json_encode(['error' => $exception->getMessage(), 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (LoginValidationException $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage(), 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (LoginAuthenticationException|SessionAuthenticationException $exception) {
    http_response_code(401);
    echo json_encode(['error' => $exception->getMessage(), 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (JsonException) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed JSON request.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Identity service failure [' . $requestId . ']: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Identity service is unavailable.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
}
