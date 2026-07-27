<?php

declare(strict_types=1);

use CourseHub\Identity\Features\GoogleLogin\GoogleLoginAuthenticationException;
use CourseHub\Identity\Features\GoogleLogin\GoogleLoginConfigurationException;
use CourseHub\Identity\Features\GoogleLogin\GoogleLoginHandler;
use CourseHub\Identity\Features\GoogleLogin\GoogleLoginValidationException;
use CourseHub\Identity\Infrastructure\Database;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Features/GoogleLogin/GoogleLoginHandler.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
header('X-Request-ID: ' . $requestId);

$respond = static function (array $payload, int $status = 200) use ($requestId): never {
    http_response_code($status);
    echo json_encode($payload + ['request_id' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        $respond(['error' => 'Method not allowed.'], 405);
    }

    $raw = (string) file_get_contents('php://input');
    $input = json_decode($raw !== '' ? $raw : '{}', true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new GoogleLoginValidationException('Request body must be a JSON object.');
    }

    $credential = (string) ($input['credential'] ?? '');
    $database = Database::connect();
    $handler = new GoogleLoginHandler($database);
    $result = $handler->handle(
        $credential,
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500),
    );

    $respond($result);
} catch (GoogleLoginValidationException $exception) {
    $respond(['error' => $exception->getMessage()], 422);
} catch (GoogleLoginAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (GoogleLoginConfigurationException $exception) {
    error_log('Google login configuration failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => $exception->getMessage()], 503);
} catch (JsonException) {
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (Throwable $exception) {
    error_log('Google login failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'Google sign-in is temporarily unavailable.'], 503);
}
