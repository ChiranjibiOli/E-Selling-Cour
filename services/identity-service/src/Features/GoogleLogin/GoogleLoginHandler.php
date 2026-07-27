<?php

declare(strict_types=1);

namespace CourseHub\Identity\Features\GoogleLogin;

use DateTimeImmutable;
use JsonException;
use PDO;
use Throwable;

final class GoogleLoginHandler
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo?id_token=';

    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array<string, mixed> */
    public function handle(string $credential, string $ip, string $userAgent): array
    {
        $credential = trim($credential);
        if ($credential === '' || strlen($credential) > 12_000) {
            throw new GoogleLoginValidationException('Google did not return a valid sign-in credential.');
        }

        $clientId = trim((string) getenv('GOOGLE_CLIENT_ID'));
        if ($clientId === '') {
            throw new GoogleLoginConfigurationException('Google sign-in is not configured.');
        }

        $claims = $this->verifyCredential($credential, $clientId);
        $googleId = trim((string) ($claims['sub'] ?? ''));
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $fullName = trim((string) ($claims['name'] ?? ''));
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (preg_match('/^[0-9]{5,191}$/', $googleId) !== 1) {
            throw new GoogleLoginAuthenticationException('The Google account identifier is invalid.');
        }
        if (!$emailVerified || !$this->isGmail($email)) {
            throw new GoogleLoginAuthenticationException('Use a verified Gmail account for Student sign-in.');
        }
        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $fullName = strstr($email, '@', true) ?: 'CourseHub Student';
        }

        try {
            $this->database->beginTransaction();

            $linkedStatement = $this->database->prepare(
                'SELECT u.id, u.full_name, u.email, u.role, u.status, u.email_verified_at '
                . 'FROM oauth_accounts o INNER JOIN users u ON u.id=o.user_id '
                . 'WHERE o.provider=\'google\' AND o.provider_user_id=:provider_user_id LIMIT 1 FOR UPDATE'
            );
            $linkedStatement->execute(['provider_user_id' => $googleId]);
            $user = $linkedStatement->fetch();

            if (!is_array($user)) {
                $emailStatement = $this->database->prepare(
                    'SELECT id, full_name, email, role, status, email_verified_at '
                    . 'FROM users WHERE email=:email LIMIT 1 FOR UPDATE'
                );
                $emailStatement->execute(['email' => $email]);
                $user = $emailStatement->fetch();

                if (is_array($user)) {
                    $this->assertStudentAccountMayUseGoogle($user);
                    $this->attachGoogleAccount((int) $user['id'], $googleId, $email);
                } else {
                    $user = $this->createStudent($fullName, $email);
                    $this->attachGoogleAccount((int) $user['id'], $googleId, $email);
                }
            } else {
                $this->assertStudentAccountMayUseGoogle($user);
            }

            $userId = (int) ($user['id'] ?? 0);
            $activate = $this->database->prepare(
                'UPDATE users SET '
                . 'status=CASE WHEN status=\'inactive\' AND email_verified_at IS NULL THEN \'active\' ELSE status END, '
                . 'email_verified_at=COALESCE(email_verified_at, NOW()), last_login_at=NOW() '
                . 'WHERE id=:id AND role=\'student\''
            );
            $activate->execute(['id' => $userId]);

            $this->database->commit();

            return $this->issueSession([
                'id' => $userId,
                'full_name' => (string) ($user['full_name'] ?? $fullName),
                'email' => (string) ($user['email'] ?? $email),
                'role' => 'student',
            ], $ip, $userAgent);
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function verifyCredential(string $credential, string $clientId): array
    {
        $handle = curl_init(self::TOKENINFO_URL . rawurlencode($credential));
        if ($handle === false) {
            throw new GoogleLoginConfigurationException('Unable to initialise Google sign-in verification.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $failure = curl_error($handle);
        curl_close($handle);

        if (!is_string($raw)) {
            throw new GoogleLoginConfigurationException(
                'Google sign-in verification is unavailable.' . ($failure !== '' ? ' Try again shortly.' : '')
            );
        }

        try {
            $claims = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new GoogleLoginAuthenticationException('Google returned an unreadable sign-in response.');
        }

        if ($status !== 200 || !is_array($claims)) {
            throw new GoogleLoginAuthenticationException('Google could not verify this sign-in attempt.');
        }

        $audience = (string) ($claims['aud'] ?? '');
        $authorisedParty = (string) ($claims['azp'] ?? '');
        $issuer = (string) ($claims['iss'] ?? '');
        $expiresAt = (int) ($claims['exp'] ?? 0);

        if (!hash_equals($clientId, $audience)) {
            throw new GoogleLoginAuthenticationException('The Google credential was issued for another application.');
        }
        if ($authorisedParty !== '' && !hash_equals($clientId, $authorisedParty)) {
            throw new GoogleLoginAuthenticationException('The Google credential has an invalid authorised party.');
        }
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new GoogleLoginAuthenticationException('The Google credential has an invalid issuer.');
        }
        if ($expiresAt <= time()) {
            throw new GoogleLoginAuthenticationException('The Google credential has expired.');
        }

        return $claims;
    }

    /** @param array<string, mixed> $user */
    private function assertStudentAccountMayUseGoogle(array $user): void
    {
        if (!hash_equals('student', (string) ($user['role'] ?? ''))) {
            throw new GoogleLoginAuthenticationException('This email belongs to a protected staff account.');
        }

        $status = (string) ($user['status'] ?? '');
        $isAwaitingEmailVerification = $status === 'inactive' && empty($user['email_verified_at']);
        if ($status !== 'active' && !$isAwaitingEmailVerification) {
            throw new GoogleLoginAuthenticationException('This Student account is not available for sign-in.');
        }
    }

    /** @return array<string, mixed> */
    private function createStudent(string $fullName, string $email): array
    {
        $statement = $this->database->prepare(
            'INSERT INTO users '
            . '(full_name, email, password, role, status, email_verified_at, last_login_at) '
            . 'VALUES (:full_name, :email, :password, \'student\', \'active\', NOW(), NOW())'
        );
        $statement->execute([
            'full_name' => $fullName,
            'email' => $email,
            'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        ]);

        return [
            'id' => (int) $this->database->lastInsertId(),
            'full_name' => $fullName,
            'email' => $email,
            'role' => 'student',
            'status' => 'active',
            'email_verified_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    private function attachGoogleAccount(int $userId, string $googleId, string $email): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO oauth_accounts (user_id, provider, provider_user_id, provider_email) '
            . 'VALUES (:user_id, \'google\', :provider_user_id, :provider_email)'
        );
        $statement->execute([
            'user_id' => $userId,
            'provider_user_id' => $googleId,
            'provider_email' => $email,
        ]);
    }

    /** @param array<string, mixed> $user
     *  @return array<string, mixed>
     */
    private function issueSession(array $user, string $ip, string $userAgent): array
    {
        $token = bin2hex(random_bytes(32));
        $ttl = max(900, min(86_400, (int) (getenv('IDENTITY_SESSION_TTL') ?: 28_800)));
        $expiresAt = (new DateTimeImmutable())->modify('+' . $ttl . ' seconds');

        $insert = $this->database->prepare(
            'INSERT INTO identity_sessions '
            . '(user_id, token_hash, portal, user_agent_hash, ip_hash, expires_at, created_at) '
            . 'VALUES (:user_id, :token_hash, \'student\', :user_agent_hash, :ip_hash, :expires_at, NOW())'
        );
        $insert->execute([
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'user_agent_hash' => hash('sha256', substr($userAgent, 0, 500)),
            'ip_hash' => hash('sha256', substr($ip, 0, 64)),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'message' => 'Google sign-in successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => [
                'id' => (int) $user['id'],
                'name' => (string) $user['full_name'],
                'email' => (string) $user['email'],
                'role' => 'student',
            ],
        ];
    }

    private function isGmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/^[A-Z0-9._%+\-]+@gmail\.com$/i', $email) === 1
            && mb_strlen($email) <= 150;
    }
}

final class GoogleLoginValidationException extends \RuntimeException
{
}

final class GoogleLoginAuthenticationException extends \RuntimeException
{
}

final class GoogleLoginConfigurationException extends \RuntimeException
{
}
