<?php

declare(strict_types=1);

namespace CourseHub\Identity\Features\Login;

use DateTimeImmutable;
use PDO;

final class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public function __construct(private readonly PDO $database)
    {
    }

    public function assertAllowed(string $email, string $ip): void
    {
        $record = $this->find($email, $ip);
        if ($record === null || empty($record['locked_until'])) {
            return;
        }

        $lockedUntil = new DateTimeImmutable((string) $record['locked_until']);
        if ($lockedUntil > new DateTimeImmutable()) {
            throw new LoginRateLimitException('Too many login attempts. Try again later.');
        }
    }

    public function recordFailure(string $email, string $ip): void
    {
        $emailHash = hash('sha256', strtolower(trim($email)));
        $ipHash = hash('sha256', $ip);
        $record = $this->find($email, $ip);
        $attempts = ((int) ($record['attempts'] ?? 0)) + 1;
        $lockedUntil = $attempts >= self::MAX_ATTEMPTS
            ? (new DateTimeImmutable())->modify('+' . self::LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s')
            : null;

        $statement = $this->database->prepare(
            'INSERT INTO identity_login_attempts (email_hash, ip_hash, attempts, locked_until, last_attempt_at) '
            . 'VALUES (:email_hash, :ip_hash, :attempts, :locked_until, NOW()) '
            . 'ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until), last_attempt_at = NOW()'
        );
        $statement->execute([
            'email_hash' => $emailHash,
            'ip_hash' => $ipHash,
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ]);
    }

    public function clear(string $email, string $ip): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM identity_login_attempts WHERE email_hash = :email_hash AND ip_hash = :ip_hash'
        );
        $statement->execute([
            'email_hash' => hash('sha256', strtolower(trim($email))),
            'ip_hash' => hash('sha256', $ip),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function find(string $email, string $ip): ?array
    {
        $statement = $this->database->prepare(
            'SELECT attempts, locked_until FROM identity_login_attempts '
            . 'WHERE email_hash = :email_hash AND ip_hash = :ip_hash LIMIT 1'
        );
        $statement->execute([
            'email_hash' => hash('sha256', strtolower(trim($email))),
            'ip_hash' => hash('sha256', $ip),
        ]);
        $record = $statement->fetch();

        return is_array($record) ? $record : null;
    }
}

final class LoginRateLimitException extends \RuntimeException
{
}
