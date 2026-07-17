<?php

declare(strict_types=1);

namespace CourseHub\Gateway;

final class ServiceRegistry
{
    /** @return array<string, string> */
    public static function routes(): array
    {
        return [
            '/api/v1/auth' => self::env('IDENTITY_SERVICE_URL', 'http://identity-service:8080'),
            '/api/v1/users' => self::env('IDENTITY_SERVICE_URL', 'http://identity-service:8080'),
            '/api/v1/courses' => self::env('CATALOG_SERVICE_URL', 'http://catalog-service:8080'),
            '/api/v1/categories' => self::env('CATALOG_SERVICE_URL', 'http://catalog-service:8080'),
            '/api/v1/learning' => self::env('LEARNING_SERVICE_URL', 'http://learning-service:8080'),
            '/api/v1/progress' => self::env('LEARNING_SERVICE_URL', 'http://learning-service:8080'),
            '/api/v1/cart' => self::env('COMMERCE_SERVICE_URL', 'http://commerce-service:8080'),
            '/api/v1/orders' => self::env('COMMERCE_SERVICE_URL', 'http://commerce-service:8080'),
            '/api/v1/coupons' => self::env('COMMERCE_SERVICE_URL', 'http://commerce-service:8080'),
            '/api/v1/payments' => self::env('PAYMENT_SERVICE_URL', 'http://payment-service:8080'),
            '/api/v1/webhooks/payments' => self::env('PAYMENT_SERVICE_URL', 'http://payment-service:8080'),
            '/api/v1/enrollments' => self::env('ENROLLMENT_SERVICE_URL', 'http://enrollment-service:8080'),
            '/api/v1/notifications' => self::env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8080'),
        ];
    }

    public static function resolve(string $path): ?string
    {
        $routes = self::routes();
        uksort($routes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($routes as $prefix => $serviceUrl) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return rtrim($serviceUrl, '/');
            }
        }

        return null;
    }

    private static function env(string $key, string $fallback): string
    {
        $value = trim((string) getenv($key));
        return $value !== '' ? $value : $fallback;
    }
}
