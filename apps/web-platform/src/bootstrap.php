<?php

declare(strict_types=1);

define('COURSEHUB_REPOSITORY_ROOT', dirname(__DIR__, 3));
define('COURSEHUB_WEB_ROOT', dirname(__DIR__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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
