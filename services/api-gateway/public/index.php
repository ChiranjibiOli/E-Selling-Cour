<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    echo json_encode(['status' => 'ok', 'service' => 'api-gateway'], JSON_THROW_ON_ERROR);
    exit;
}

$serviceUrl = static fn (string $environment, string $fallback): string => rtrim(trim((string) getenv($environment)) ?: $fallback, '/');
$routes = [
    '/api/v1/auth' => $serviceUrl('IDENTITY_SERVICE_URL', 'http://identity-service:8080'),
    '/api/v1/users' => $serviceUrl('IDENTITY_SERVICE_URL', 'http://identity-service:8080'),
    '/api/v1/courses' => $serviceUrl('CATALOG_SERVICE_URL', 'http://catalog-service:8080'),
    '/api/v1/categories' => $serviceUrl('CATALOG_SERVICE_URL', 'http://catalog-service:8080'),
    '/api/v1/learning' => $serviceUrl('LEARNING_SERVICE_URL', 'http://learning-service:8080'),
    '/api/v1/progress' => $serviceUrl('LEARNING_SERVICE_URL', 'http://learning-service:8080'),
    '/api/v1/commerce' => $serviceUrl('COMMERCE_SERVICE_URL', 'http://commerce-service:8080'),
    '/api/v1/cart' => $serviceUrl('COMMERCE_SERVICE_URL', 'http://commerce-service:8080'),
    '/api/v1/orders' => $serviceUrl('COMMERCE_SERVICE_URL', 'http://commerce-service:8080'),
    '/api/v1/payments' => $serviceUrl('PAYMENT_SERVICE_URL', 'http://payment-service:8080'),
    '/api/v1/enrollments' => $serviceUrl('ENROLLMENT_SERVICE_URL', 'http://enrollment-service:8080'),
    '/api/v1/media' => $serviceUrl('MEDIA_SERVICE_URL', 'http://media-service:8080'),
    '/api/v1/notifications' => $serviceUrl('NOTIFICATION_SERVICE_URL', 'http://notification-service:8080'),
    '/api/v1/reviews' => $serviceUrl('REVIEW_SERVICE_URL', 'http://review-service:8080'),
    '/api/v1/reports' => $serviceUrl('REPORTING_SERVICE_URL', 'http://reporting-service:8080'),
];
$target = null;
uksort($routes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
foreach ($routes as $prefix => $url) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        $target = $url;
        break;
    }
}
if ($target === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Gateway route not found.'], JSON_THROW_ON_ERROR);
    exit;
}

$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
$headers = [
    'Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'application/json'),
    'Accept: application/json',
    'Authorization: ' . ($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
    'X-Request-ID: ' . $requestId,
    'X-Forwarded-For: ' . trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
];
$ch = curl_init($target . ($_SERVER['REQUEST_URI'] ?? '/'));
curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'] ?? 'GET', CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
$body = file_get_contents('php://input');
if ($body !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
if ($response === false) {
    error_log('Gateway upstream failure [' . $requestId . ']: ' . curl_error($ch));
    http_response_code(502);
    echo json_encode(['error' => 'Upstream service unavailable.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
    exit;
}
curl_close($ch);
http_response_code($status ?: 200);
header('X-Request-ID: ' . $requestId);
echo $response;
