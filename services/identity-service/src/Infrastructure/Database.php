<?php

declare(strict_types=1);

namespace CourseHub\Identity\Infrastructure;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '3307');
        $database = self::env('DB_DATABASE', 'coursehub');
        $username = self::env('DB_USERNAME', 'root');
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
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Identity database is unavailable.', 0, $exception);
        }
    }

    private static function env(string $name, string $fallback): string
    {
        $value = trim((string) getenv($name));
        return $value !== '' ? $value : $fallback;
    }
}
