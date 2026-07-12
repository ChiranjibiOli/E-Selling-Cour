<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$host = '127.0.0.1';
$port = 3307;
$dbname = 'coursehub';
$username = 'root';
$password = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $dbname, $port);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+05:45'");
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(503);

    if (APP_DEBUG) {
        exit('Database connection failed. Make sure XAMPP MySQL is running on port 3307 and the coursehub database exists.');
    }

    exit('The service is temporarily unavailable. Please try again later.');
}
