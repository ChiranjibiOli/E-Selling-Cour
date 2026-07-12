<?php

declare(strict_types=1);

require_once '../app/core/Auth.php';
require_once '../app/config/database.php';

Auth::requireLogin();

$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);
$role = (string) ($user['role'] ?? '');
$requestedInstructorId = (int) ($_GET['instructor_id'] ?? 0);

if ($role === 'instructor') {
    $instructorId = $userId;
} elseif ($role === 'admin' && $requestedInstructorId > 0) {
    $instructorId = $requestedInstructorId;
} else {
    http_response_code(403);
    exit('You do not have permission to view this payout QR.');
}

$stmt = $conn->prepare("
    SELECT ibd.qr_image
    FROM instructor_bank_details ibd
    INNER JOIN users u ON u.id = ibd.instructor_id
    WHERE ibd.instructor_id = ?
      AND u.role = 'instructor'
    LIMIT 1
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

$fileName = (string) ($row['qr_image'] ?? '');
$filePath = Security::resolveStoredFile($fileName, [
    __DIR__ . '/../storage/private_uploads/instructor_qr',
    __DIR__ . '/assets/uploads/instructor_qr',
]);

if ($filePath === null) {
    http_response_code(404);
    exit('Payout QR not found.');
}

$mimeType = Security::detectMimeType($filePath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(403);
    exit('Unsupported payout QR type.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="payout-qr.' . pathinfo($filePath, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
