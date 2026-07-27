<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Http;

use InvalidArgumentException;

final class Response
{
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(array $payload, int $status = 200): self
    {
        return new self(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function binary(string $body, string $contentType, int $status = 200): self
    {
        if (preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $contentType) !== 1) {
            throw new InvalidArgumentException('Invalid binary response content type.');
        }

        return new self($body, $status, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($body),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        // Redirects are restricted to local absolute paths to prevent open redirects.
        if (!str_starts_with($location, '/') || str_starts_with($location, '//')) {
            throw new InvalidArgumentException('Unsafe redirect location.');
        }
        return new self('', $status, ['Location' => $location, 'Cache-Control' => 'no-store']);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
