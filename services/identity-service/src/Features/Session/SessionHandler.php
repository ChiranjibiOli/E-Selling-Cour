<?php

declare(strict_types=1);

namespace CourseHub\Identity\Features\Session;

use PDO;

final class SessionHandler
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array<string, mixed> */
    public function verify(string $authorization): array
    {
        $token = $this->bearerToken($authorization);
        $statement = $this->database->prepare(
            'SELECT s.id AS session_id, s.portal, s.expires_at, '
            . 'u.id, u.full_name, u.email, u.role, u.status, u.profile_image '
            . 'FROM identity_sessions s '
            . 'INNER JOIN users u ON u.id = s.user_id '
            . 'WHERE s.token_hash = :token_hash '
            . 'AND s.revoked_at IS NULL AND s.expires_at > NOW() '
            . 'LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $session = $statement->fetch();

        if (!is_array($session)
            || !hash_equals('active', (string) ($session['status'] ?? ''))
            || !hash_equals((string) ($session['portal'] ?? ''), (string) ($session['role'] ?? ''))
        ) {
            throw new SessionAuthenticationException('The session is invalid or expired.');
        }

        return [
            'authenticated' => true,
            'portal' => (string) $session['portal'],
            'expires_at' => (string) $session['expires_at'],
            'user' => [
                'id' => (int) $session['id'],
                'name' => (string) $session['full_name'],
                'email' => (string) $session['email'],
                'role' => (string) $session['role'],
                'profile_image' => (string) ($session['profile_image'] ?? ''),
            ],
        ];
    }

    public function logout(string $authorization): void
    {
        $token = $this->bearerToken($authorization);
        $statement = $this->database->prepare(
            'UPDATE identity_sessions SET revoked_at = NOW() '
            . 'WHERE token_hash = :token_hash AND revoked_at IS NULL'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
    }

    private function bearerToken(string $authorization): string
    {
        if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', trim($authorization), $matches) !== 1) {
            throw new SessionAuthenticationException('A valid bearer token is required.');
        }

        return $matches[1];
    }
}

final class SessionAuthenticationException extends \RuntimeException
{
}
