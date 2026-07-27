<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Http;

use CourseHub\WebPlatform\Shared\Config\Environment;
use CourseHub\WebPlatform\Shared\Session\AuthSession;
use DomainException;
use JsonException;

final class ApiClient
{
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $payload): array
    {
        return $this->request('POST', $path, $payload);
    }

    public function request(string $method, string $path, ?array $payload = null): array
    {
        if (!str_starts_with($path, '/')) {
            throw new DomainException('Invalid API path.');
        }

        $baseUrl = rtrim(Environment::string('API_BASE_URL', 'http://127.0.0.1:9000'), '/');
        $handle = curl_init($baseUrl . $path);
        if ($handle === false) {
            throw new DomainException('Unable to initialize the API connection.');
        }

        $requestId = bin2hex(random_bytes(16));
        $responseRequestId = '';
        $headers = ['Accept: application/json', 'X-Request-ID: ' . $requestId];
        $token = AuthSession::token();
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseRequestId): int {
                if (str_starts_with(strtolower($header), 'x-request-id:')) {
                    $responseRequestId = trim(substr($header, strlen('x-request-id:')));
                }
                return strlen($header);
            },
        ]);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($raw)) {
            throw new DomainException('The API gateway is unavailable. ' . ($error !== '' ? 'Check that every local service is running.' : ''));
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $reference = $responseRequestId !== '' ? ' Reference: ' . $responseRequestId . '.' : '';
            throw new DomainException('A backend service returned invalid data.' . $reference . ' Restart the local platform and inspect its service logs.');
        }

        if (!is_array($decoded) || $status < 200 || $status >= 300) {
            $message = is_array($decoded) ? trim((string) ($decoded['error'] ?? '')) : '';
            $reference = is_array($decoded) ? trim((string) ($decoded['request_id'] ?? $responseRequestId)) : $responseRequestId;
            if ($message === '') {
                $message = 'The API request failed.';
            }
            if ($reference !== '' && !str_contains($message, $reference)) {
                $message .= ' Reference: ' . $reference . '.';
            }
            throw new DomainException($message);
        }

        return $decoded;
    }
}
