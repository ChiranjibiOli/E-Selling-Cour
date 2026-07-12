<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Database connection policy
 * --------------------------
 * This project uses MySQL/MariaDB on port 3307.
 * Local development may try the legacy `course_selling` database and the
 * rebuilt `coursehub` database, but it never falls back to port 3306.
 */
$configuredHost = trim((string) env_value('DB_HOST', '127.0.0.1'));
$configuredPort = (int) env_value('DB_PORT', 3307);
$configuredDatabase = trim((string) env_value('DB_DATABASE', 'course_selling'));
$username = (string) env_value('DB_USERNAME', 'root');
$password = (string) env_value('DB_PASSWORD', '');
$allowLocalDatabaseFallback = (bool) env_value('DB_ALLOW_LOCAL_FALLBACK', APP_ENV === 'local');

if ($configuredHost === '') {
    $configuredHost = '127.0.0.1';
}

// The project requirement is explicit: MySQL must run on 3307.
if ($configuredPort !== 3307) {
    error_log('DB_PORT must be 3307 for this project. Received: ' . $configuredPort);
    $configuredPort = 3307;
}

if ($configuredDatabase === '' || preg_match('/^[A-Za-z0-9_]+$/', $configuredDatabase) !== 1) {
    error_log('Database configuration is invalid: DB_DATABASE contains unsupported characters.');
    http_response_code(503);
    exit('The database configuration is invalid.');
}

$hostCandidates = [$configuredHost];
$databaseCandidates = [$configuredDatabase];

if ($allowLocalDatabaseFallback) {
    $hostCandidates[] = '127.0.0.1';
    $hostCandidates[] = 'localhost';
    $databaseCandidates[] = 'course_selling';
    $databaseCandidates[] = 'coursehub';
}

$hostCandidates = array_values(array_unique(array_filter(
    $hostCandidates,
    static fn (string $value): bool => $value !== ''
)));
$databaseCandidates = array_values(array_unique(array_filter(
    $databaseCandidates,
    static fn (string $value): bool => preg_match('/^[A-Za-z0-9_]+$/', $value) === 1
)));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = null;
$connectionAttempts = [];
$lastConnectionException = null;

foreach ($hostCandidates as $host) {
    foreach ($databaseCandidates as $databaseName) {
        $connectionAttempts[] = $host . ':3307/' . $databaseName;

        try {
            $candidate = new mysqli($host, $username, $password, $databaseName, 3307);
            $candidate->set_charset('utf8mb4');
            $candidate->query("SET time_zone = '+05:45'");
            $candidate->query(
                "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, "
                . "'STRICT_TRANS_TABLES', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION')"
            );
            $candidate->query('SELECT 1');

            $conn = $candidate;
            defined('DB_HOST_NAME') || define('DB_HOST_NAME', $host);
            defined('DB_PORT_NUMBER') || define('DB_PORT_NUMBER', 3307);
            defined('DB_DATABASE_NAME') || define('DB_DATABASE_NAME', $databaseName);
            break 2;
        } catch (mysqli_sql_exception $exception) {
            $lastConnectionException = $exception;
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

    if (APP_DEBUG || PHP_SAPI === 'cli') {
        $attemptText = implode(', ', $connectionAttempts);

        if (PHP_SAPI === 'cli') {
            exit(
                'Database connection failed on port 3307.' . PHP_EOL
                . 'Checked: ' . $attemptText . PHP_EOL
                . 'Last error: ' . $technicalMessage . PHP_EOL
                . 'Confirm MySQL is running on 3307 and verify DB_DATABASE, DB_USERNAME, and DB_PASSWORD.' . PHP_EOL
            );
        }

        exit(
            'Database connection failed on port 3307. Checked: '
            . htmlspecialchars($attemptText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '. Confirm MySQL is running on 3307 and verify DB_DATABASE, DB_USERNAME, and DB_PASSWORD.'
        );
    }

    exit('The service is temporarily unavailable. Please try again later.');
}
