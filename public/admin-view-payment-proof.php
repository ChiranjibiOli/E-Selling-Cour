<?php

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

$proofId = isset($_GET['proof_id']) ? (int) $_GET['proof_id'] : 0;

if ($proofId <= 0) {
    http_response_code(400);
    exit('Invalid request: proof id missing.');
}

$sql = "
    SELECT proof_image
    FROM payment_proofs
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit('Database query error.');
}

$stmt->bind_param("i", $proofId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    http_response_code(404);
    exit('Payment proof not found.');
}

$proof = $result->fetch_assoc();
$stmt->close();

$fileName = $proof['proof_image'] ?? '';

if ($fileName === '') {
    http_response_code(404);
    exit('Proof file name is empty.');
}

$fileName = basename($fileName);

$possibleFolders = [
    __DIR__ . '/../storage/private_uploads/payment_proofs/',
    __DIR__ . '/assets/uploads/payment_proofs/',
    __DIR__ . '/assets/uploads/payments/',
    __DIR__ . '/assets/uploads/proofs/'
];

$filePath = Security::resolveStoredFile($fileName, $possibleFolders);

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('Proof file not found: ' . htmlspecialchars($fileName));
}

$mimeType = mime_content_type($filePath);

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/jpg',
    'application/pdf'
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(403);
    exit('Unsupported proof type: ' . htmlspecialchars($mimeType));
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
header('Cache-Control: private, no-store, max-age=0');

readfile($filePath);
exit;
