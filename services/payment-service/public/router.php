<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($path === '/api/v1/payments/manual' && $method === 'POST') {
    require __DIR__ . '/manual.php';
    exit;
}
require __DIR__ . '/index.php';
