<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (preg_match('#^/api/v1/learning/courses/\d+/player$#', $path) === 1 && $method === 'GET') {
    require __DIR__ . '/player.php';
    exit;
}

$curriculumMutation = ($method === 'POST' && preg_match('#^/api/v1/learning/(?:courses/\d+/sections|sections/\d+/lessons)$#', $path) === 1)
    || (in_array($method, ['PATCH', 'PUT', 'DELETE'], true) && preg_match('#^/api/v1/learning/(?:sections|lessons)/\d+$#', $path) === 1);
if ($curriculumMutation) {
    require __DIR__ . '/curriculum.php';
    exit;
}

require __DIR__ . '/index.php';
