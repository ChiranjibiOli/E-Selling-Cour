<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Session;

use DomainException;

final class AuthSession
{
    public static function establish(array $identityResult): void
    {
        $user = $identityResult['user'] ?? null;
        $token = (string) ($identityResult['access_token'] ?? '');
        $role = is_array($user) ? (string) ($user['role'] ?? '') : '';

        if (!is_array($user) || $token === '' || !in_array($role, ['student', 'instructor', 'admin'], true)) {
            throw new DomainException('The identity service returned an invalid session.');
        }

        session_regenerate_id(true);
        $_SESSION['access_token'] = $token;
        $_SESSION['role'] = $role;
        $_SESSION['user'] = $user;
        $_SESSION['expires_in'] = (int) ($identityResult['expires_in'] ?? 0);
        $_SESSION['authenticated_at'] = time();
    }

    public static function synchronizeUser(array $user): void
    {
        $role = (string) ($user['role'] ?? '');
        $id = (int) ($user['id'] ?? 0);
        if ($id < 1 || !in_array($role, ['student', 'instructor', 'admin'], true)) {
            throw new DomainException('The identity service returned an invalid user profile.');
        }

        $current = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
        $_SESSION['user'] = array_merge($current, $user);
        $_SESSION['role'] = $role;
    }

    public static function role(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }

    public static function token(): string
    {
        return (string) ($_SESSION['access_token'] ?? '');
    }

    /** @return array<string, mixed> */
    public static function user(): array
    {
        return is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
    }

    public static function clear(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
