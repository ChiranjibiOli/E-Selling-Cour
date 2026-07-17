<?php

declare(strict_types=1);

namespace CourseHub\Services\Shared;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        $host = trim((string) getenv('DB_HOST')) ?: '127.0.0.1';
        $port = trim((string) getenv('DB_PORT')) ?: '3307';
        $database = trim((string) getenv('DB_DATABASE')) ?: 'coursehub';
        $username = trim((string) getenv('DB_USERNAME')) ?: 'root';
        $password = (string) getenv('DB_PASSWORD');

        try {
            return new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Service database connection failed.', 0, $exception);
        }
    }
}
