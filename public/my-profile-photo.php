<?php

declare(strict_types=1);

require_once '../app/core/Auth.php';
require_once '../app/config/database.php';

Auth::requireLogin();

$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

$stmt = $conn->prepare('SELECT profile_image FROM users WHERE id = ? AND status = \'active\' LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

$fileName = basename((string) ($row['profile_image'] ?? ''));
if ($fileName === '' || $fileName === '.' || $fileName === '..') {
    http_response_code(404);
    exit('Profile photo not found.');
}

$filePath = Security::resolveStoredFile($fileName, [
    __DIR__ . '/../storage/private_uploads/profile_photos',
    __DIR__ . '/assets/uploads/profile_photos',
    __DIR__ . '/assets/uploads/profiles',
]);

if ($filePath === null) {
    http_response_code(404);
    exit('Profile photo not found.');
}

$mimeType = Security::detectMimeType($filePath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(403);
    exit('Unsupported profile photo type.');
}

$downloadName = Security::safeDownloadName($fileName, 'profile-photo');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
