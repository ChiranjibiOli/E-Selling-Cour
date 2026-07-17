<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/packages/shared-ui/src/PortalShell.php';
require_once $root . '/packages/shared-http/src/ApiClient.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'CourseHub\\PublicWeb\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/' . $relative . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
