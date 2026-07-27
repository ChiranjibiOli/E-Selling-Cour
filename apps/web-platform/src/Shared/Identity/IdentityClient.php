<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Identity;

use CourseHub\WebPlatform\Shared\Config\Environment;
use DomainException;
use JsonException;

final class IdentityClient
{
    public function login(string $portal, string $email, string $password, array $extra = []): array
    {
        return $this->request('/api/v1/auth/login', [
            'portal' => $portal,
            'email' => $email,
            'password' => $password,
            ...$extra,
        ]);
    }

    public function googleLogin(string $credential): array
    {
        return $this->request('/api/v1/auth/google', [
            'credential' => $credential,
        ]);
    }

    private function request(string $path, array $payload): array
    {
        $baseUrl = rtrim(Environment::string('API_BASE_URL', 'http://127.0.0.1:9000'), '/');
        $handle = curl_init($baseUrl . $path);
        if ($handle === false) {
            throw new DomainException('Unable to initialize the identity connection.');
        }

        $requestId = bin2hex(random_bytes(16));
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Request-ID: ' . $requestId,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $failure = curl_error($handle);
        curl_close($handle);

        if (!is_string($raw)) {
            throw new DomainException('The identity service is unavailable. ' . ($failure !== '' ? 'Check that the API services are running.' : ''));
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('The identity service returned an unreadable response.');
        }

        if (!is_array($decoded) || $status < 200 || $status >= 300) {
            $message = is_array($decoded) ? (string) ($decoded['error'] ?? '') : '';
            throw new DomainException($message !== '' ? $message : 'Authentication failed.');
        }

        return $decoded;
    }
}
