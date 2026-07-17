<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$publicFile = __DIR__ . $path;

if ($path !== '/' && is_file($publicFile)) {
    return false;
}

// Media is stored outside the web root. Only whitelisted folders and file extensions are exposed.
if (preg_match('#^/media/(course-thumbnails|profile-photos)/([A-Za-z0-9._-]+)$#', $path, $matches) === 1) {
    $folder = $matches[1];
    $filename = $matches[2];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        http_response_code(404);
        exit;
    }
    $root = dirname(__DIR__, 3);
    $file = $root . '/storage/media/' . $folder . '/' . $filename;
    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }
    $mime = match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}

require __DIR__ . '/index.php';
