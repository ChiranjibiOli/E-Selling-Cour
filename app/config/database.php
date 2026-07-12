<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Database connection policy
 * --------------------------
 * This project uses the `coursehub` database on MySQL/MariaDB port 3307.
 * Local development may retry localhost when 127.0.0.1 is configured, but it
 * never changes the database name and never falls back to port 3306.
 */
$configuredHost = trim((string) env_value('DB_HOST', '127.0.0.1'));
$configuredPort = (int) env_value('DB_PORT', 3307);
$configuredDatabase = trim((string) env_value('DB_DATABASE', 'coursehub'));
$username = (string) env_value('DB_USERNAME', 'root');
$password = (string) env_value('DB_PASSWORD', '');
$allowLocalHostFallback = (bool) env_value('DB_ALLOW_LOCAL_FALLBACK', APP_ENV === 'local');

if ($configuredHost === '') {
    $configuredHost = '127.0.0.1';
}

if ($configuredPort !== 3307) {
    error_log('DB_PORT must be 3307 for this project. Received: ' . $configuredPort);
    $configuredPort = 3307;
}

if ($configuredDatabase === '' || preg_match('/^[A-Za-z0-9_]+$/', $configuredDatabase) !== 1) {
    error_log('Database configuration is invalid: DB_DATABASE contains unsupported characters.');
    http_response_code(503);
    exit('The database configuration is invalid.');
}

if ($configuredDatabase !== 'coursehub') {
    error_log('DB_DATABASE must be coursehub for this project. Received: ' . $configuredDatabase);
    $configuredDatabase = 'coursehub';
}

$hostCandidates = [$configuredHost];

if ($allowLocalHostFallback) {
    $hostCandidates[] = '127.0.0.1';
    $hostCandidates[] = 'localhost';
}

$hostCandidates = array_values(array_unique(array_filter(
    $hostCandidates,
    static fn (string $value): bool => $value !== ''
)));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = null;
$connectionAttempts = [];
$lastConnectionException = null;

foreach ($hostCandidates as $host) {
    $connectionAttempts[] = $host . ':3307/coursehub';

    try {
        $candidate = new mysqli($host, $username, $password, 'coursehub', 3307);
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
        defined('DB_DATABASE_NAME') || define('DB_DATABASE_NAME', 'coursehub');
        break;
    } catch (mysqli_sql_exception $exception) {
        $lastConnectionException = $exception;
    }
}

if (!$conn instanceof mysqli) {
    $technicalMessage = $lastConnectionException instanceof Throwable
        ? $lastConnectionException->getMessage()
        : 'The coursehub database could not be reached.';

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
                'Database connection failed.' . PHP_EOL
                . 'Expected database: coursehub' . PHP_EOL
                . 'Expected port: 3307' . PHP_EOL
                . 'Checked: ' . $attemptText . PHP_EOL
                . 'Last error: ' . $technicalMessage . PHP_EOL
                . 'Confirm MySQL is running and verify DB_USERNAME and DB_PASSWORD.' . PHP_EOL
            );
        }

        exit(
            'Database connection failed. Expected coursehub on port 3307. Checked: '
            . htmlspecialchars($attemptText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '. Confirm MySQL is running and verify DB_USERNAME and DB_PASSWORD.'
        );
    }

    exit('The service is temporarily unavailable. Please try again later.');
}
