<?php

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

$instructorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$type = $_GET['type'] ?? '';

if ($instructorId <= 0) {
    http_response_code(400);
    exit('Invalid request: instructor id is missing.');
}

if ($type !== 'document' && $type !== 'profile') {
    http_response_code(400);
    exit('Invalid request: file type is missing.');
}

$sql = "
    SELECT profile_image, identity_document
    FROM users
    WHERE id = ? AND role = 'instructor'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit('Database query error.');
}

$stmt->bind_param("i", $instructorId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    http_response_code(404);
    exit('Instructor not found.');
}

$instructor = $result->fetch_assoc();
$stmt->close();

if ($type === 'document') {
    $fileName = $instructor['identity_document'] ?? '';

    $possibleFolders = [
        __DIR__ . '/../storage/private_uploads/instructor_documents/',
        __DIR__ . '/assets/uploads/instructor_documents/',
        __DIR__ . '/assets/uploads/identity_documents/'
    ];
} else {
    $fileName = $instructor['profile_image'] ?? '';

    $possibleFolders = [
        __DIR__ . '/../storage/private_uploads/profile_photos/',
        __DIR__ . '/assets/uploads/profile_photos/',
        __DIR__ . '/assets/uploads/profiles/'
    ];
}

if ($fileName === '') {
    http_response_code(404);
    exit('File name is empty in database.');
}

$fileName = basename($fileName);
$filePath = Security::resolveStoredFile($fileName, $possibleFolders);

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('File not found: ' . htmlspecialchars($fileName));
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
    exit('Unsupported file type: ' . htmlspecialchars($mimeType));
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
header('Cache-Control: private, no-store, max-age=0');

readfile($filePath);
exit;
