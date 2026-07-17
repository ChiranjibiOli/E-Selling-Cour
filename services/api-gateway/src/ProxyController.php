<?php

declare(strict_types=1);

namespace CourseHub\Gateway;

final class ProxyController
{
    public function handle(): void
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $requestId = $this->requestId();

        header('Content-Type: application/json; charset=utf-8');
        header('X-Request-ID: ' . $requestId);

        if ($path === '/health') {
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'service' => 'api-gateway', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
            return;
        }

        $upstream = ServiceRegistry::resolve($path);
        if ($upstream === null) {
            http_response_code(404);
            echo json_encode(['error' => 'API route not found.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
            return;
        }

        if (!function_exists('curl_init')) {
            http_response_code(500);
            echo json_encode(['error' => 'Gateway cURL support is unavailable.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
            return;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 2_097_152) {
            http_response_code(413);
            echo json_encode(['error' => 'Request body is too large.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
            return;
        }

        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $target = $upstream . $path . ($query !== '' ? '?' . $query : '');
        $body = file_get_contents('php://input');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $headers = [
            'Accept: application/json',
            'Content-Type: ' . (string) ($_SERVER['CONTENT_TYPE'] ?? 'application/json'),
            'X-Request-ID: ' . $requestId,
        ];

        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($authorization !== '') {
            $headers[] = 'Authorization: ' . $authorization;
        }

        $handle = curl_init($target);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? ($body ?: '') : null,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $error !== '') {
            http_response_code(502);
            echo json_encode(['error' => 'Upstream service is unavailable.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
            return;
        }

        $responseBody = substr($response, $headerSize);
        http_response_code($status > 0 ? $status : 502);
        echo $responseBody !== '' ? $responseBody : json_encode(['request_id' => $requestId], JSON_THROW_ON_ERROR);
    }

    private function requestId(): string
    {
        $provided = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        if ($provided !== '' && preg_match('/^[A-Za-z0-9._-]{8,100}$/', $provided) === 1) {
            return $provided;
        }

        return bin2hex(random_bytes(16));
    }
}
