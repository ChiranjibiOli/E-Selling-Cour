<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/config/database.php';

[$script, $email, $password, $fullName] = array_pad($argv, 4, null);
$email = trim((string) $email);
$password = (string) $password;
$fullName = trim((string) ($fullName ?: 'CourseHub Admin'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php admin@example.com \"StrongPassword\" \"Admin Name\"\n");
    exit(1);
}

if (
    strlen($password) < 12
    || !preg_match('/[a-z]/', $password)
    || !preg_match('/[A-Z]/', $password)
    || !preg_match('/\d/', $password)
    || !preg_match('/[^A-Za-z0-9]/', $password)
) {
    fwrite(STDERR, "Password must be at least 12 characters with upper, lower, number, and symbol.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';
$status = 'active';

$sql = "
    INSERT INTO users (full_name, email, password, role, status)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        password = VALUES(password),
        role = 'admin',
        status = 'active'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssss', $fullName, $email, $hash, $role, $status);
$stmt->execute();
$stmt->close();

fwrite(STDOUT, "Admin account created or updated for {$email}.\n");

