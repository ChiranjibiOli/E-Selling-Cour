<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$authoringRoute = ($path === '/api/v1/courses/authoring' && $method === 'POST')
    || ($path === '/api/v1/courses/mine' && $method === 'GET')
    || ($path === '/api/v1/courses/edit-permissions' && $method === 'GET')
    || ($path === '/api/v1/courses/revisions/pending' && $method === 'GET')
    || preg_match('#^/api/v1/courses/\d+/authoring$#', $path) === 1
    || preg_match('#^/api/v1/courses/\d+/edit-permission/(?:request|grant|deny)$#', $path) === 1
    || preg_match('#^/api/v1/courses/revisions/\d+/(?:approve|reject)$#', $path) === 1
    || preg_match('#^/api/v1/courses/\d+/change-log$#', $path) === 1
    || (preg_match('#^/api/v1/courses/\d+$#', $path) === 1 && in_array($method, ['PUT', 'PATCH'], true));

if ($authoringRoute) {
    require __DIR__ . '/authoring.php';
    exit;
}

require __DIR__ . '/index.php';
