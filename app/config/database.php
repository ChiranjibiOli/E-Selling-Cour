<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Database connection policy
 * --------------------------
 * Production obeys the explicit DB_* values exactly.
 * Local development may try well-known project defaults so an older XAMPP
 * database named `course_selling` and the rebuilt `coursehub` schema both work.
 */
$configuredHost = trim((string) env_value('DB_HOST', '127.0.0.1'));
$configuredPort = (int) env_value('DB_PORT', 3307);
$configuredDatabase = trim((string) env_value('DB_DATABASE', ''));
$username = (string) env_value('DB_USERNAME', 'root');
$password = (string) env_value('DB_PASSWORD', '');
$allowLocalFallback = (bool) env_value('DB_ALLOW_LOCAL_FALLBACK', APP_ENV === 'local');

if ($configuredHost === '') {
    $configuredHost = '127.0.0.1';
}

if ($configuredPort < 1 || $configuredPort > 65535) {
    $configuredPort = 3307;
}

if ($configuredDatabase !== '' && preg_match('/^[A-Za-z0-9_]+$/', $configuredDatabase) !== 1) {
    error_log('Database configuration is invalid: DB_DATABASE contains unsupported characters.');
    http_response_code(503);
    exit('The database configuration is invalid.');
}

$hostCandidates = [$configuredHost];
$portCandidates = [$configuredPort];
$databaseCandidates = $configuredDatabase !== '' ? [$configuredDatabase] : [];

if ($allowLocalFallback) {
    $hostCandidates[] = '127.0.0.1';
    $hostCandidates[] = 'localhost';
    $portCandidates[] = 3307;
    $portCandidates[] = 3306;

    // The user's original XAMPP dump uses course_selling. The rebuilt schema
    // uses coursehub. Try both only in local development.
    $databaseCandidates[] = 'course_selling';
    $databaseCandidates[] = 'coursehub';
}

if ($databaseCandidates === []) {
    $databaseCandidates[] = 'coursehub';
}

$hostCandidates = array_values(array_unique(array_filter($hostCandidates, static fn (string $value): bool => $value !== '')));
$portCandidates = array_values(array_unique(array_filter($portCandidates, static fn (int $value): bool => $value > 0 && $value <= 65535)));
$databaseCandidates = array_values(array_unique(array_filter(
    $databaseCandidates,
    static fn (string $value): bool => preg_match('/^[A-Za-z0-9_]+$/', $value) === 1
)));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = null;
$connectionAttempts = [];
$lastConnectionException = null;

foreach ($hostCandidates as $host) {
    foreach ($portCandidates as $port) {
        foreach ($databaseCandidates as $databaseName) {
            $connectionAttempts[] = $host . ':' . $port . '/' . $databaseName;

            try {
                $candidate = new mysqli($host, $username, $password, $databaseName, $port);
                $candidate->set_charset('utf8mb4');
                $candidate->query("SET time_zone = '+05:45'");
                $candidate->query(
                    "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, "
                    . "'STRICT_TRANS_TABLES', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION')"
                );
                $candidate->query('SELECT 1');

                $conn = $candidate;
                defined('DB_HOST_NAME') || define('DB_HOST_NAME', $host);
                defined('DB_PORT_NUMBER') || define('DB_PORT_NUMBER', $port);
                defined('DB_DATABASE_NAME') || define('DB_DATABASE_NAME', $databaseName);
                break 3;
            } catch (mysqli_sql_exception $exception) {
                $lastConnectionException = $exception;
            }
        }
    }
}

if (!$conn instanceof mysqli) {
    $technicalMessage = $lastConnectionException instanceof Throwable
        ? $lastConnectionException->getMessage()
        : 'No database candidate could be reached.';

    error_log(
        'Database connection failed. Attempts: '
        . implode(', ', $connectionAttempts)
        . '. Last error: '
        . $technicalMessage
    );

    http_response_code(503);

    if (APP_DEBUG) {
        exit(
            'Database connection failed. Checked: '
            . htmlspecialchars(implode(', ', $connectionAttempts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '. Confirm MySQL is running, check .env, and verify the database name.'
        );
    }

    exit('The service is temporarily unavailable. Please try again later.');
}
