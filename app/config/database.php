<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$host = trim((string) env_value('DB_HOST', '127.0.0.1'));
$port = (int) env_value('DB_PORT', 3307);
$dbname = trim((string) env_value('DB_DATABASE', 'coursehub'));
$username = (string) env_value('DB_USERNAME', 'root');
$password = (string) env_value('DB_PASSWORD', '');

if ($host === '' || $dbname === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbname)) {
    error_log('Database configuration is invalid.');
    http_response_code(503);
    exit('The service is temporarily unavailable.');
}

if ($port < 1 || $port > 65535) {
    $port = 3307;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $dbname, $port);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+05:45'");
    $conn->query("SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'STRICT_TRANS_TABLES', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION')");
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(503);

    if (APP_DEBUG) {
        exit('Database connection failed. Check the DB_* values in .env and confirm MySQL is running.');
    }

    exit('The service is temporarily unavailable. Please try again later.');
}
