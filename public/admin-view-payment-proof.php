<?php

declare(strict_types=1);

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

$proofId = (int) ($_GET['proof_id'] ?? 0);
if ($proofId <= 0) {
    http_response_code(400);
    exit('Invalid payment-proof request.');
}

$stmt = $conn->prepare("
    SELECT pp.proof_image
    FROM payment_proofs pp
    INNER JOIN payments p ON p.id = pp.payment_id
    INNER JOIN orders o ON o.id = p.order_id
    WHERE pp.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $proofId);
$stmt->execute();
$proof = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$proof) {
    http_response_code(404);
    exit('Payment proof not found.');
}

$fileName = (string) ($proof['proof_image'] ?? '');
$filePath = Security::resolveStoredFile($fileName, [
    __DIR__ . '/../storage/private_uploads/payment_proofs',
    __DIR__ . '/assets/uploads/payment_proofs',
    __DIR__ . '/assets/uploads/payments',
    __DIR__ . '/assets/uploads/proofs',
]);

if ($filePath === null) {
    http_response_code(404);
    exit('Payment proof not found.');
}

$mimeType = Security::detectMimeType($filePath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(403);
    exit('Unsupported payment-proof type.');
}

$downloadName = Security::safeDownloadName($fileName, 'payment-proof');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
