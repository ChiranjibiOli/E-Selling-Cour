<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($name) === false) {
            putenv($name . '=' . trim($value, "\"'"));
        }
    }
}

require_once $root . '/services/_shared/Database.php';

$email = strtolower(trim((string) getenv('COURSEHUB_ADMIN_EMAIL')));
$password = (string) getenv('COURSEHUB_ADMIN_PASSWORD');
$name = trim((string) getenv('COURSEHUB_ADMIN_NAME')) ?: 'CourseHub Administrator';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set COURSEHUB_ADMIN_EMAIL to a valid email address.\n");
    exit(1);
}
if (strlen($password) < 12 || strlen($password) > 200) {
    fwrite(STDERR, "Set COURSEHUB_ADMIN_PASSWORD to a password containing at least 12 characters.\n");
    exit(1);
}

$database = Database::connect();
$statement = $database->prepare(
    'INSERT INTO users (full_name, email, password, role, status) VALUES (:name, :email, :password, \'admin\', \'active\') '
    . 'ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), password=VALUES(password), role=\'admin\', status=\'active\''
);
$statement->execute(['name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrator account is ready for {$email}.\n");
