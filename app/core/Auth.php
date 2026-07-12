<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Security.php';

final class Auth
{
    private const ALLOWED_ROLES = ['student', 'instructor', 'admin'];

    public static function start(): void
    {
        Security::boot();
    }

    public static function login(array $user): void
    {
        self::start();

        $id = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        $status = (string) ($user['status'] ?? '');

        if ($id <= 0 || !in_array($role, self::ALLOWED_ROLES, true) || $status !== 'active') {
            throw new RuntimeException('Invalid authentication state.');
        }

        session_regenerate_id(true);
        Security::rotateToken();

        $_SESSION['auth_user'] = [
            'id' => $id,
            'full_name' => (string) ($user['full_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => $role,
            'status' => $status,
        ];
    }

    public static function check(): bool
    {
        self::start();

        $user = $_SESSION['auth_user'] ?? null;
        if (!is_array($user)) {
            return false;
        }

        return (int) ($user['id'] ?? 0) > 0
            && in_array((string) ($user['role'] ?? ''), self::ALLOWED_ROLES, true)
            && (string) ($user['status'] ?? '') === 'active';
    }

    public static function user(): ?array
    {
        self::start();
        return self::check() ? $_SESSION['auth_user'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return isset($user['role']) ? (string) $user['role'] : null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            self::redirect('login.php');
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        $user = self::user();

        if (($user['role'] ?? '') !== $role) {
            http_response_code(403);
            exit('You do not have permission to access this page.');
        }
    }

    public static function guestOnly(): void
    {
        if (self::check()) {
            self::redirectBasedOnRole();
        }
    }

    public static function redirectStudentAwayFromPublic(): void
    {
        if (self::check() && self::role() === 'student') {
            self::redirect('student-dashboard.php');
        }
    }

    public static function redirectBasedOnRole(): void
    {
        $destination = match (self::role()) {
            'student' => 'student-dashboard.php',
            'instructor' => 'instructor-dashboard.php',
            'admin' => 'admin-dashboard.php',
            default => 'login.php',
        };

        self::redirect($destination);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::redirect('login.php');
    }

    public static function redirect(string $path): never
    {
        $safePath = Security::safeInternalPath($path) ?? 'index.php';
        header('Location: ' . $safePath, true, 302);
        exit;
    }
}
