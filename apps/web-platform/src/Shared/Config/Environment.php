<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Config;

final class Environment
{
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $name) !== 1 || getenv($name) !== false) {
                continue;
            }

            $value = trim($value, "\"'");
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    public static function string(string $name, string $fallback = ''): string
    {
        $value = trim((string) getenv($name));
        return $value !== '' ? $value : $fallback;
    }

    /** @return list<string> */
    public static function csv(string $name): array
    {
        $value = self::string($name);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public static function adminLoginPath(): string
    {
        $path = self::string('ADMIN_LOGIN_PATH', '/control-room/entry-9d4f');
        return preg_match('#^/[A-Za-z0-9/_-]{8,120}$#', $path) === 1
            ? rtrim($path, '/')
            : '/control-room/entry-9d4f';
    }
}
