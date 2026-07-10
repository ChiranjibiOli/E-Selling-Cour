<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

final class Security
{
    private const CSRF_KEY = '_csrf_token';
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');

            $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);

            session_name((string) env_value('APP_SESSION_NAME', 'coursehub_session'));
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secureCookie,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        self::sendHeaders();
        self::token();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            self::verifyRequest();
        }

        if (PHP_SAPI !== 'cli' && ob_get_level() === 0) {
            ob_start([self::class, 'injectCsrfFields']);
        }
    }

    public static function token(): string
    {
        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    public static function verifyRequest(): void
    {
        $submittedToken = $_POST['_csrf_token'] ?? '';

        if (!is_string($submittedToken) || !hash_equals(self::token(), $submittedToken)) {
            http_response_code(419);
            exit('Your form session expired. Go back, refresh the page, and try again.');
        }
    }

    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method not allowed.');
        }
    }

    public static function injectCsrfFields(string $buffer): string
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                return $buffer;
            }
        }

        return (string) preg_replace_callback(
            '/<form\b([^>]*)>/i',
            static function (array $matches): string {
                if (!preg_match('/\bmethod\s*=\s*([\'"]?)post\1/i', $matches[1])) {
                    return $matches[0];
                }

                return $matches[0] . self::field();
            },
            $buffer
        );
    }

    public static function sanitizeRichText(?string $html): string
    {
        $html = (string) $html;
        $html = (string) preg_replace(
            '#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is',
            '',
            $html
        );

        $allowedTags = '<h1><h2><h3><h4><h5><p><br><strong><b><em><i><ul><ol><li><span>';
        $html = strip_tags($html, $allowedTags);

        return (string) preg_replace(
            '/<(h[1-5]|p|br|strong|b|em|i|ul|ol|li|span)\b[^>]*>/i',
            '<$1>',
            $html
        );
    }

    public static function resolveStoredFile(string $fileName, array $directories): ?string
    {
        $safeName = basename($fileName);

        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            return null;
        }

        foreach ($directories as $directory) {
            $realDirectory = realpath((string) $directory);

            if ($realDirectory === false) {
                continue;
            }

            $candidate = realpath($realDirectory . DIRECTORY_SEPARATOR . $safeName);

            if (
                $candidate !== false
                && is_file($candidate)
                && str_starts_with($candidate, $realDirectory . DIRECTORY_SEPARATOR)
            ) {
                return $candidate;
            }
        }

        return null;
    }

    private static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self' https:; connect-src 'self'");

        if (!empty($_SESSION['auth_user'])) {
            header('Cache-Control: no-store, private');
            header('Pragma: no-cache');
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Security::field();
    }
}
