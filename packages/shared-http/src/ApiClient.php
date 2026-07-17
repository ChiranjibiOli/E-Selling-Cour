<?php

declare(strict_types=1);

namespace CourseHub\SharedHttp;

final class ApiClient
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    /** @param array<string, mixed> $payload */
    public function post(string $path, array $payload): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 500, 'data' => ['error' => 'The cURL PHP extension is required.']];
        }

        $requestId = bin2hex(random_bytes(16));
        $handle = curl_init(rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/'));

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Request-ID: ' . $requestId,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $error !== '') {
            return ['ok' => false, 'status' => 502, 'data' => ['error' => 'API gateway is unavailable.', 'request_id' => $requestId]];
        }

        $decoded = json_decode($body, true);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => is_array($decoded) ? $decoded : ['error' => 'Invalid API response.', 'request_id' => $requestId],
        ];
    }
}
