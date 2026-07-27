<?php

declare(strict_types=1);

final class GatewayClient
{
    public static function postJson(string $url, array $payload, array $headers = []): array
    {
        return self::request('POST', $url, $payload, $headers);
    }

    public static function getJson(string $url, array $headers = []): array
    {
        return self::request('GET', $url, null, $headers);
    }

    public static function signFields(array $payload, array $fields, string $secret): string
    {
        if ($secret === '') {
            throw new RuntimeException('The payment gateway secret is not configured.');
        }

        $parts = [];
        foreach ($fields as $field) {
            if (!is_string($field) || preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1 || !array_key_exists($field, $payload)) {
                throw new InvalidArgumentException('The payment signature fields are invalid.');
            }
            $parts[] = $field . '=' . self::scalarToString($payload[$field]);
        }

        return base64_encode(hash_hmac('sha256', implode(',', $parts), $secret, true));
    }

    public static function verifySignedPayload(array $payload, string $secret): bool
    {
        $signedFields = trim((string) ($payload['signed_field_names'] ?? ''));
        $signature = trim((string) ($payload['signature'] ?? ''));
        if ($signedFields === '' || $signature === '' || strlen($signedFields) > 500 || strlen($signature) > 200) {
            return false;
        }

        $fields = array_map('trim', explode(',', $signedFields));
        if ($fields === [] || count($fields) !== count(array_unique($fields))) {
            return false;
        }

        try {
            $expected = self::signFields($payload, $fields, $secret);
        } catch (Throwable) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    private static function request(string $method, string $url, ?array $payload, array $headers): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || trim((string) ($parts['host'] ?? '')) === '') {
            throw new RuntimeException('The payment gateway URL is invalid.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the payment gateway connection.');
        }

        $httpHeaders = array_merge(['Accept: application/json'], $headers);
        if ($payload !== null) {
            $httpHeaders[] = 'Content-Type: application/json';
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $httpHeaders,
        ];
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($handle, $options);

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($raw)) {
            error_log('Payment gateway connection failure: ' . $error);
            throw new RuntimeException('The payment gateway could not be reached.');
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The payment gateway returned an unreadable response.');
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('The payment gateway returned an invalid response.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['detail'] ?? $decoded['error_message'] ?? $decoded['message'] ?? $decoded['error_key'] ?? 'The payment gateway rejected the request.';
            if (is_array($message)) {
                $message = implode(' ', array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $message));
            }
            throw new DomainException(mb_substr(trim((string) $message), 0, 300));
        }

        return $decoded;
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_string($value) || is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        throw new InvalidArgumentException('Signed payment values must be scalar.');
    }
}
