<?php

declare(strict_types=1);

namespace CourseHub\Services;

final class ServiceShell
{
    public static function run(string $serviceName, array $ownedPrefixes): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
            $requestId = bin2hex(random_bytes(16));
        }
        header('X-Request-ID: ' . $requestId);

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        if ($path === '/health') {
            http_response_code(200);
            echo json_encode([
                'status' => 'ok',
                'service' => $serviceName,
                'owned_prefixes' => array_values($ownedPrefixes),
                'request_id' => $requestId,
            ], JSON_THROW_ON_ERROR);
            return;
        }

        $ownsPath = false;
        foreach ($ownedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/')) {
                $ownsPath = true;
                break;
            }
        }

        http_response_code($ownsPath ? 501 : 404);
        echo json_encode([
            'error' => $ownsPath
                ? 'This service boundary exists, but the requested feature has not been migrated yet.'
                : 'Service route not found.',
            'service' => $serviceName,
            'request_id' => $requestId,
        ], JSON_THROW_ON_ERROR);
    }
}
