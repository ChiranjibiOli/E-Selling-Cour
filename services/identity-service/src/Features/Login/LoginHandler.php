<?php

declare(strict_types=1);

namespace CourseHub\Identity\Features\Login;

use DateTimeImmutable;
use PDO;

final class LoginHandler
{
    /** @var list<string> */
    private const PORTALS = ['student', 'instructor', 'admin'];

    public function __construct(
        private readonly PDO $database,
        private readonly LoginRateLimiter $rateLimiter
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(array $input, string $ip, string $userAgent): array
    {
        $portal = strtolower(trim((string) ($input['portal'] ?? '')));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if (!in_array($portal, self::PORTALS, true)) {
            throw new LoginValidationException('A valid portal is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            throw new LoginValidationException('Enter a valid email address.');
        }
        if ($password === '' || strlen($password) > 200) {
            throw new LoginValidationException('Enter a valid password.');
        }

        $this->rateLimiter->assertAllowed($email, $ip);

        $statement = $this->database->prepare(
            'SELECT id, full_name, email, password, role, status FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        $valid = is_array($user)
            && password_verify($password, (string) ($user['password'] ?? ''))
            && hash_equals($portal, (string) ($user['role'] ?? ''))
            && hash_equals('active', (string) ($user['status'] ?? ''));

        if (!$valid) {
            $this->rateLimiter->recordFailure($email, $ip);
            throw new LoginAuthenticationException('Invalid credentials or portal access.');
        }

        $this->rateLimiter->clear($email, $ip);
        $token = bin2hex(random_bytes(32));
        $ttl = max(900, min(86_400, (int) (getenv('IDENTITY_SESSION_TTL') ?: 28_800)));
        $expiresAt = (new DateTimeImmutable())->modify('+' . $ttl . ' seconds');

        $insert = $this->database->prepare(
            'INSERT INTO identity_sessions '
            . '(user_id, token_hash, portal, user_agent_hash, ip_hash, expires_at, created_at) '
            . 'VALUES (:user_id, :token_hash, :portal, :user_agent_hash, :ip_hash, :expires_at, NOW())'
        );
        $insert->execute([
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'portal' => $portal,
            'user_agent_hash' => hash('sha256', $userAgent),
            'ip_hash' => hash('sha256', $ip),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => [
                'id' => (int) $user['id'],
                'name' => (string) $user['full_name'],
                'email' => (string) $user['email'],
                'role' => (string) $user['role'],
            ],
        ];
    }
}

final class LoginValidationException extends \RuntimeException
{
}

final class LoginAuthenticationException extends \RuntimeException
{
}
