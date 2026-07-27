<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST' && $path === '/api/v1/auth/google') {
    require __DIR__ . '/google-login.php';
    exit;
}

if ($method === 'POST' && $path === '/api/v1/auth/register/instructor') {
    require __DIR__ . '/instructor-registration.php';
    exit;
}

if (in_array($method, ['GET', 'POST'], true) && $path === '/api/v1/users/account-profile') {
    require __DIR__ . '/account-profile.php';
    exit;
}

if (in_array($method, ['GET', 'POST'], true) && $path === '/api/v1/users/instructor-profile') {
    require __DIR__ . '/instructor-profile.php';
    exit;
}

if ($method === 'POST' && preg_match('#^/api/v1/users/instructor-applications/\d+/(approve|reject)$#', $path) === 1) {
    require __DIR__ . '/instructor-decision.php';
    exit;
}

require __DIR__ . '/index.php';
