<?php

declare(strict_types=1);

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

$instructorId = (int) ($_GET['id'] ?? 0);
$type = (string) ($_GET['type'] ?? '');

if ($instructorId <= 0 || !in_array($type, ['document', 'profile'], true)) {
    http_response_code(400);
    exit('Invalid file request.');
}

$stmt = $conn->prepare("
    SELECT profile_image, identity_document
    FROM users
    WHERE id = ? AND role = 'instructor'
    LIMIT 1
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$instructor) {
    http_response_code(404);
    exit('Instructor file not found.');
}

if ($type === 'document') {
    $fileName = (string) ($instructor['identity_document'] ?? '');
    $possibleFolders = [
        __DIR__ . '/../storage/private_uploads/instructor_documents',
        __DIR__ . '/assets/uploads/instructor_documents',
        __DIR__ . '/assets/uploads/identity_documents',
    ];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $fallbackName = 'instructor-document';
} else {
    $fileName = (string) ($instructor['profile_image'] ?? '');
    $possibleFolders = [
        __DIR__ . '/../storage/private_uploads/profile_photos',
        __DIR__ . '/assets/uploads/profile_photos',
        __DIR__ . '/assets/uploads/profiles',
    ];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $fallbackName = 'instructor-profile-photo';
}

$filePath = Security::resolveStoredFile($fileName, $possibleFolders);
if ($filePath === null) {
    http_response_code(404);
    exit('Instructor file not found.');
}

$mimeType = Security::detectMimeType($filePath);
if (!in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(403);
    exit('Unsupported instructor file type.');
}

$downloadName = Security::safeDownloadName($fileName, $fallbackName);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
