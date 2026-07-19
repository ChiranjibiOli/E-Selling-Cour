<?php

declare(strict_types=1);

namespace CourseHub\Services\Shared;

use DomainException;
use PDO;

final class ServiceAuth
{
    /** @return array{id:int,name:string,email:string,role:string} */
    public static function requireUser(PDO $database, string $authorization, ?string $role = null): array
    {
        if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', trim($authorization), $matches) !== 1) {
            throw new ServiceAuthenticationException('A valid session is required.');
        }

        $statement = $database->prepare(
            'SELECT u.id, u.full_name, u.email, u.role, u.status, s.portal '
            . 'FROM identity_sessions s INNER JOIN users u ON u.id = s.user_id '
            . 'WHERE s.token_hash = :token_hash AND s.revoked_at IS NULL AND s.expires_at > NOW() LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $matches[1])]);
        $user = $statement->fetch();

        if (!is_array($user)
            || !hash_equals('active', (string) ($user['status'] ?? ''))
            || !hash_equals((string) ($user['portal'] ?? ''), (string) ($user['role'] ?? ''))
        ) {
            throw new ServiceAuthenticationException('The session is invalid or expired.');
        }

        if ($role !== null && !hash_equals($role, (string) $user['role'])) {
            throw new ServiceAuthorizationException('This action is not available in your portal.');
        }

        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['full_name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
    }
}

final class ServiceAuthenticationException extends DomainException
{
}

final class ServiceAuthorizationException extends DomainException
{
}
