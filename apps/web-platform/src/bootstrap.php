<?php

declare(strict_types=1);

define('COURSEHUB_REPOSITORY_ROOT', dirname(__DIR__, 3));
define('COURSEHUB_WEB_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'CourseHub\\WebPlatform\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = COURSEHUB_WEB_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

CourseHub\WebPlatform\Shared\Config\Environment::load(COURSEHUB_REPOSITORY_ROOT . '/.env');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('coursehub_portal');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => filter_var(getenv('SESSION_COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
