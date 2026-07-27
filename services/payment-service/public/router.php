<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($path === '/api/v1/payments/options' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'data' => [
            'manual' => [
                'available' => true,
                'verification' => 'admin',
                'proof_required' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($path === '/api/v1/payments/admin/gateways' && in_array($method, ['GET', 'POST'], true))
    || (preg_match('#^/api/v1/payments/(esewa|khalti)/(initiate|verify)$#', $path) === 1 && $method === 'POST')
) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code(410);
    echo json_encode([
        'error' => 'Automatic payment gateways are not available. Use manual payment proof and Admin verification.',
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($path === '/api/v1/payments/manual' && $method === 'POST') {
    require __DIR__ . '/manual.php';
    exit;
}

require __DIR__ . '/index.php';
