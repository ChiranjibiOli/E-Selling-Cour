<?php

declare(strict_types=1);

$host = trim((string) getenv('DB_HOST')) ?: '127.0.0.1';
$port = trim((string) getenv('DB_PORT')) ?: '3307';
$database = trim((string) getenv('DB_DATABASE')) ?: 'coursehub';
$username = trim((string) getenv('DB_USERNAME')) ?: 'root';
$password = (string) getenv('DB_PASSWORD');
$repositoryRoot = dirname(__DIR__);

$pdo = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ],
        );
        break;
    } catch (PDOException $exception) {
        if ($attempt === 30) {
            fwrite(STDERR, "Database did not become ready: {$exception->getMessage()}\n");
            exit(1);
        }
        sleep(2);
    }
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Unable to initialize migration database connection.\n");
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations ('
    . 'version VARCHAR(120) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
    . ') ENGINE=InnoDB'
);

$migrations = [
    '001_identity_sessions' => $repositoryRoot . '/services/identity-service/database/migrations/001_identity_sessions.sql',
    '002_learning_progress' => $repositoryRoot . '/database/migrations/002_learning_progress.sql',
    '003_instructor_application_and_password_reset' => $repositoryRoot . '/database/migrations/003_instructor_application_and_password_reset.sql',
    '004_course_authoring_details' => $repositoryRoot . '/database/migrations/004_course_authoring_details.sql',
    '005_normalize_commission_setting' => $repositoryRoot . '/database/migrations/005_normalize_commission_setting.sql',
    '006_instructor_profile_photo_cooldown' => $repositoryRoot . '/database/migrations/006_instructor_profile_photo_cooldown.sql',
    '007_student_email_verification' => $repositoryRoot . '/database/migrations/007_student_email_verification.sql',
];

$exists = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = :version LIMIT 1');
$record = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');

foreach ($migrations as $version => $path) {
    $exists->execute(['version' => $version]);
    if ($exists->fetch() !== false) {
        echo "SKIP {$version}\n";
        continue;
    }
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        fwrite(STDERR, "Migration file is missing or empty: {$path}\n");
        exit(1);
    }
    try {
        $pdo->exec($sql);
        $record->execute(['version' => $version]);
        echo "APPLIED {$version}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "Migration {$version} failed: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "MIGRATIONS COMPLETE\n";
