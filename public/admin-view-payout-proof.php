<?php

declare(strict_types=1);

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

$payoutId = (int) ($_GET['payout_id'] ?? 0);
if ($payoutId <= 0) {
    http_response_code(400);
    exit('Invalid payout-proof request.');
}

$stmt = $conn->prepare("
    SELECT proof_image
    FROM payouts
    WHERE id = ?
      AND payout_status = 'paid'
    LIMIT 1
");
$stmt->bind_param('i', $payoutId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

$fileName = (string) ($row['proof_image'] ?? '');
$filePath = Security::resolveStoredFile($fileName, [
    __DIR__ . '/../storage/private_uploads/payout_proofs',
]);

if ($filePath === null) {
    http_response_code(404);
    exit('Payout proof not found.');
}

$mimeType = Security::detectMimeType($filePath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(403);
    exit('Unsupported payout-proof type.');
}

$downloadName = Security::safeDownloadName($fileName, 'payout-proof');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
