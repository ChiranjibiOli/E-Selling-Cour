<?php

declare(strict_types=1);

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'")))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

if (!function_exists('env_value')) {
    function env_value(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 2));
load_env_file(ROOT_PATH . DIRECTORY_SEPARATOR . '.env');

defined('APP_NAME') || define('APP_NAME', (string) env_value('APP_NAME', 'CourseHub'));
defined('APP_ENV') || define('APP_ENV', (string) env_value('APP_ENV', 'production'));
defined('APP_DEBUG') || define('APP_DEBUG', (bool) env_value('APP_DEBUG', false));
defined('STORAGE_PATH') || define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
defined('PUBLIC_PATH') || define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');

$configuredUrl = rtrim((string) env_value('APP_URL', ''), '/');

if ($configuredUrl === '') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $scriptDirectory = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');
    $configuredUrl = $scheme . '://' . ($host ?: 'localhost') . $scriptDirectory;
}

defined('BASE_URL') || define('BASE_URL', $configuredUrl);

$timezone = (string) env_value('APP_TIMEZONE', 'Asia/Kathmandu');

if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'UTC';
}

date_default_timezone_set($timezone);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
