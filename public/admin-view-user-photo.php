<?php

declare(strict_types=1);

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    exit('Invalid user photo request.');
}

$stmt = $conn->prepare("
    SELECT profile_image
    FROM users
    WHERE id = ?
      AND role IN ('student', 'instructor')
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

$fileName = (string) ($row['profile_image'] ?? '');
$filePath = Security::resolveStoredFile($fileName, [
    __DIR__ . '/../storage/private_uploads/profile_photos',
    __DIR__ . '/assets/uploads/profile_photos',
    __DIR__ . '/assets/uploads/profiles',
]);

if ($filePath === null) {
    http_response_code(404);
    exit('User profile photo not found.');
}

$mimeType = Security::detectMimeType($filePath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(403);
    exit('Unsupported profile photo type.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="user-profile-photo.' . pathinfo($filePath, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
