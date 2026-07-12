<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

final class Security
{
    private const CSRF_KEY = '_csrf_token';
    private const SESSION_CREATED_KEY = '_security_session_created_at';
    private const SESSION_SEEN_KEY = '_security_session_seen_at';
    private const SESSION_REGENERATED_KEY = '_security_session_regenerated_at';
    private const RATE_LIMIT_SESSION_KEY = '_security_rate_limits';
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

            $secureCookie = self::isHttps();

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

        self::enforceSessionLifecycle();
        self::sendHeaders();
        self::token();

        if (self::isUnsafeMethod()) {
            self::verifySameOrigin();
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

    public static function rotateToken(): string
    {
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
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
                if (!preg_match('/\bmethod\s*=\s*([\'\"]?)post\1/i', $matches[1])) {
                    return $matches[0];
                }

                if (stripos($matches[0], 'name="_csrf_token"') !== false) {
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

    public static function detectMimeType(string $path, string $fallback = 'application/octet-stream'): string
    {
        if (!is_file($path)) {
            return $fallback;
        }

        if (class_exists('finfo')) {
            try {
                $detector = new finfo(FILEINFO_MIME_TYPE);
                $mime = $detector->file($path);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            } catch (Throwable $exception) {
                error_log('MIME detection fallback used: ' . $exception->getMessage());
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }

        return $fallback;
    }

    public static function safeDownloadName(string $name, string $fallback = 'download'): string
    {
        $name = basename(str_replace(["\r", "\n", "\0", '"', "'"], '', $name));
        $name = (string) preg_replace('/[^A-Za-z0-9._-]/', '-', $name);
        $name = trim($name, '.-_');

        return $name !== '' ? substr($name, 0, 180) : $fallback;
    }

    public static function safeInternalPath(string $path, array $allowedScripts = []): ?string
    {
        $path = trim(str_replace(["\r", "\n", "\0", '\\'], '', $path));

        if ($path === '' || str_starts_with($path, '//') || str_contains($path, '..')) {
            return null;
        }

        $parts = parse_url($path);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $script = ltrim((string) ($parts['path'] ?? ''), '/');
        if (!preg_match('/^[A-Za-z0-9_-]+\.php$/', $script)) {
            return null;
        }

        if ($allowedScripts !== [] && !in_array($script, $allowedScripts, true)) {
            return null;
        }

        $query = (string) ($parts['query'] ?? '');
        if ($query !== '' && !preg_match('/^[A-Za-z0-9_=&%+.\-]*$/', $query)) {
            return null;
        }

        return $script . ($query !== '' ? '?' . $query : '');
    }

    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function rateLimitRetryAfter(
        string $scope,
        string $identity,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds
    ): int {
        $state = self::readRateLimitState($scope, $identity, $windowSeconds);
        $now = time();

        if (($state['blocked_until'] ?? 0) > $now) {
            return (int) $state['blocked_until'] - $now;
        }

        if (count($state['attempts'] ?? []) >= max(1, $maxAttempts)) {
            return max(1, $blockSeconds);
        }

        return 0;
    }

    public static function recordRateLimitFailure(
        string $scope,
        string $identity,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds
    ): int {
        $now = time();
        $state = self::readRateLimitState($scope, $identity, $windowSeconds);
        $attempts = $state['attempts'] ?? [];
        $attempts[] = $now;
        $blockedUntil = (int) ($state['blocked_until'] ?? 0);

        if (count($attempts) >= max(1, $maxAttempts)) {
            $blockedUntil = max($blockedUntil, $now + max(1, $blockSeconds));
        }

        self::writeRateLimitState($scope, $identity, [
            'attempts' => $attempts,
            'blocked_until' => $blockedUntil,
        ]);

        return max(0, $blockedUntil - $now);
    }

    public static function clearRateLimit(string $scope, string $identity): void
    {
        $path = self::rateLimitPath($scope, $identity);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }

        $key = self::rateLimitKey($scope, $identity);
        unset($_SESSION[self::RATE_LIMIT_SESSION_KEY][$key]);
    }

    private static function enforceSessionLifecycle(): void
    {
        $now = time();
        $createdAt = (int) ($_SESSION[self::SESSION_CREATED_KEY] ?? $now);
        $lastSeenAt = (int) ($_SESSION[self::SESSION_SEEN_KEY] ?? $now);
        $regeneratedAt = (int) ($_SESSION[self::SESSION_REGENERATED_KEY] ?? $now);
        $idleTimeout = max(300, (int) env_value('SESSION_IDLE_TIMEOUT', 7200));
        $absoluteTimeout = max($idleTimeout, (int) env_value('SESSION_ABSOLUTE_TIMEOUT', 43200));
        $regenerateInterval = max(300, (int) env_value('SESSION_REGENERATE_INTERVAL', 900));
        $authenticated = isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user']);

        if ($authenticated && (($now - $lastSeenAt) > $idleTimeout || ($now - $createdAt) > $absoluteTimeout)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION[self::SESSION_CREATED_KEY] = $now;
            $_SESSION[self::SESSION_SEEN_KEY] = $now;
            $_SESSION[self::SESSION_REGENERATED_KEY] = $now;
            self::rotateToken();
            return;
        }

        if ($authenticated && ($now - $regeneratedAt) >= $regenerateInterval) {
            session_regenerate_id(true);
            $_SESSION[self::SESSION_REGENERATED_KEY] = $now;
        }

        $_SESSION[self::SESSION_CREATED_KEY] = $createdAt;
        $_SESSION[self::SESSION_SEEN_KEY] = $now;
        $_SESSION[self::SESSION_REGENERATED_KEY] = (int) ($_SESSION[self::SESSION_REGENERATED_KEY] ?? $regeneratedAt);
    }

    private static function verifySameOrigin(): void
    {
        $fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($fetchSite === 'cross-site') {
            http_response_code(403);
            exit('Cross-site form submission blocked.');
        }

        $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
        if ($source === '') {
            return;
        }

        $sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
        $requestHostHeader = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        $requestHost = strtolower((string) preg_replace('/:\d+$/', '', trim($requestHostHeader, '[]')));

        if ($sourceHost === '' || $requestHost === '' || !hash_equals($requestHost, $sourceHost)) {
            http_response_code(403);
            exit('Cross-origin form submission blocked.');
        }
    }

    private static function isUnsafeMethod(): bool
    {
        return in_array(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    }

    private static function rateLimitKey(string $scope, string $identity): string
    {
        $scope = (string) preg_replace('/[^A-Za-z0-9_-]/', '-', strtolower($scope));
        return $scope . '-' . hash('sha256', $identity);
    }

    private static function rateLimitPath(string $scope, string $identity): ?string
    {
        $directory = STORAGE_PATH . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . 'rate_limits';

        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            return null;
        }

        return $directory . DIRECTORY_SEPARATOR . self::rateLimitKey($scope, $identity) . '.json';
    }

    private static function readRateLimitState(string $scope, string $identity, int $windowSeconds): array
    {
        $now = time();
        $cutoff = $now - max(1, $windowSeconds);
        $key = self::rateLimitKey($scope, $identity);
        $state = null;
        $path = self::rateLimitPath($scope, $identity);

        if ($path !== null && is_file($path)) {
            $handle = @fopen($path, 'rb');
            if ($handle !== false) {
                if (flock($handle, LOCK_SH)) {
                    $json = stream_get_contents($handle);
                    $decoded = is_string($json) ? json_decode($json, true) : null;
                    if (is_array($decoded)) {
                        $state = $decoded;
                    }
                    flock($handle, LOCK_UN);
                }
                fclose($handle);
            }
        }

        if (!is_array($state)) {
            $state = is_array($_SESSION[self::RATE_LIMIT_SESSION_KEY][$key] ?? null)
                ? $_SESSION[self::RATE_LIMIT_SESSION_KEY][$key]
                : [];
        }

        $attempts = array_values(array_filter(
            is_array($state['attempts'] ?? null) ? $state['attempts'] : [],
            static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff
        ));

        return [
            'attempts' => $attempts,
            'blocked_until' => max(0, (int) ($state['blocked_until'] ?? 0)),
        ];
    }

    private static function writeRateLimitState(string $scope, string $identity, array $state): void
    {
        $key = self::rateLimitKey($scope, $identity);
        $_SESSION[self::RATE_LIMIT_SESSION_KEY][$key] = $state;
        $path = self::rateLimitPath($scope, $identity);

        if ($path === null) {
            return;
        }

        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return;
        }

        if (flock($handle, LOCK_EX)) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($state, JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        }

        fclose($handle);
        @chmod($path, 0640);
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
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self' https:; connect-src 'self'");

        if (!empty($_SESSION['auth_user'])) {
            header('Cache-Control: no-store, private');
            header('Pragma: no-cache');
        }

        if (self::isHttps()) {
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
