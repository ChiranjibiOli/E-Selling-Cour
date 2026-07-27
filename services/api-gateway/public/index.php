<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    echo json_encode(['status' => 'ok', 'service' => 'api-gateway'], JSON_THROW_ON_ERROR);
    exit;
}

$respond = static function (array $payload, int $status): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

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
    $respond(['error' => 'Gateway route not found.'], 404);
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

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$ch = curl_init($target . $requestUri);
if ($ch === false) {
    $respond(['error' => 'The upstream request could not be initialized.', 'request_id' => $requestId], 502);
}

curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => $headers,
]);
$body = (string) file_get_contents('php://input');
if ($body !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$error = curl_error($ch);
curl_close($ch);

if (!is_string($response)) {
    error_log('Gateway upstream failure [' . $requestId . ']: ' . $error);
    $respond(['error' => 'Upstream service unavailable.', 'request_id' => $requestId], 502);
}

try {
    json_decode($response, true, 64, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    $preview = preg_replace('/\s+/', ' ', strip_tags(mb_substr($response, 0, 500))) ?? '';
    error_log('Gateway invalid upstream JSON [' . $requestId . '] HTTP ' . $status . ': ' . $preview);
    $respond([
        'error' => 'A backend service returned an invalid response. Restart the local services and check the service logs.',
        'request_id' => $requestId,
    ], 502);
}

http_response_code($status > 0 ? $status : 502);
header('X-Request-ID: ' . $requestId);
echo $response;
