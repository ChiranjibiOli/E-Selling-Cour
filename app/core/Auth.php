<?php

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Security.php';

class Auth
{
    public static function start(): void
    {
        Security::boot();
    }

    public static function login(array $user): void
    {
        self::start();

        session_regenerate_id(true);

        $_SESSION['auth_user'] = [
            'id'        => (int) ($user['id'] ?? 0),
            'full_name' => (string) ($user['full_name'] ?? ''),
            'email'     => (string) ($user['email'] ?? ''),
            'role'      => (string) ($user['role'] ?? ''),
            'status'    => (string) ($user['status'] ?? '')
        ];
    }

    public static function check(): bool
    {
        self::start();

        return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user']);
    }

    public static function user(): ?array
    {
        self::start();

        if (self::check()) {
            return $_SESSION['auth_user'];
        }

        return null;
    }

    public static function role(): ?string
    {
        $user = self::user();

        return $user['role'] ?? null;
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

        if (($user['role'] ?? '') !== $role || ($user['status'] ?? '') !== 'active') {
            self::redirect('login.php');
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
    $role = self::role();

    if ($role === 'student') {
        self::redirect('student-dashboard.php');
    }

    if ($role === 'instructor') {
        self::redirect('instructor-dashboard.php');
    }

    if ($role === 'admin') {
        self::redirect('admin-dashboard.php');
    }

    self::redirect('login.php');
}

    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        self::redirect('login.php');
    }

    public static function redirect(string $path): void
    {
        $path = preg_replace('/[\r\n]+/', '', ltrim($path, '/')) ?: 'index.php';

        header("Location: " . rtrim(BASE_URL, '/') . '/' . $path);
        exit;
    }
}
