<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Session;

use CourseHub\WebPlatform\Shared\Config\Environment;
use CourseHub\WebPlatform\Shared\Http\ApiClient;
use DomainException;

final class SessionGuard
{
    /** @return array<string,mixed> */
    public static function verify(string $requiredRole): array
    {
        if (!in_array($requiredRole, ['student', 'instructor', 'admin'], true)) {
            throw new DomainException('A valid protected portal role is required.');
        }

        $localRole = AuthSession::role();
        $token = AuthSession::token();
        $loginPath = self::loginPath($requiredRole);

        if ($token === '' || !hash_equals($requiredRole, $localRole)) {
            self::clearLocalSession();
            throw new SessionEndedException($loginPath, 'Your CourseHub session has ended. Sign in again.');
        }

        try {
            $session = (new ApiClient())->get('/api/v1/auth/session');
        } catch (DomainException $exception) {
            if (self::isAuthenticationFailure($exception->getMessage())) {
                self::clearLocalSession();
                throw new SessionEndedException($loginPath, 'This session was revoked, expired, or is no longer allowed.');
            }

            throw new SessionValidationUnavailableException(
                'CourseHub could not verify the session right now. Please try again shortly.',
                0,
                $exception,
            );
        }

        $remoteRole = (string) ($session['user']['role'] ?? '');
        $authenticated = ($session['authenticated'] ?? false) === true;
        if (!$authenticated || !hash_equals($requiredRole, $remoteRole)) {
            self::clearLocalSession();
            throw new SessionEndedException($loginPath, 'This session is no longer valid for the requested portal.');
        }

        if (is_array($session['user'] ?? null)) {
            AuthSession::synchronizeUser($session['user']);
        }

        return $session;
    }

    public static function loginPath(string $role): string
    {
        if ($role === 'student') {
            return '/learn/sign-in';
        }
        if ($role === 'instructor') {
            return '/teach/studio-access';
        }
        if ($role === 'admin') {
            $path = rtrim(Environment::string('ADMIN_LOGIN_PATH', '/control-room/entry-9d4f'), '/');
            return preg_match('#^/[A-Za-z0-9/_-]{8,120}$#', $path) === 1
                ? $path
                : '/control-room/entry-9d4f';
        }

        return '/login';
    }

    private static function isAuthenticationFailure(string $message): bool
    {
        $message = mb_strtolower($message);
        return (str_contains($message, 'session') && (str_contains($message, 'invalid') || str_contains($message, 'expired')))
            || str_contains($message, 'bearer token')
            || str_contains($message, 'authentication is required');
    }

    private static function clearLocalSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            AuthSession::clear();
        }
    }
}

final class SessionEndedException extends \RuntimeException
{
    public function __construct(
        private readonly string $loginPath,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function loginPath(): string
    {
        return $this->loginPath;
    }
}

final class SessionValidationUnavailableException extends \RuntimeException
{
}
