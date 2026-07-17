<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $body = $_POST;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), rtrim($path, '/') ?: '/', $_GET, $body, $_SERVER);
    }
}
