<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if ($path === '/api/v1/reports/instructor-students') {
    require __DIR__ . '/instructor-students.php';
    exit;
}

if (str_starts_with($path, '/api/v1/reports/admin-console/')) {
    require __DIR__ . '/admin-console.php';
    exit;
}

require __DIR__ . '/index.php';
