<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Http;

use InvalidArgumentException;
use RuntimeException;

final class Response
{
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
        private readonly ?string $filePath = null,
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
        self::assertContentType($contentType);
        return new self($body, $status, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($body),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public static function file(string $path, string $contentType, ?string $filename = null): self
    {
        self::assertContentType($contentType);
        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw new InvalidArgumentException('The response file is unavailable.');
        }
        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];
        if ($filename !== null && trim($filename) !== '') {
            $safeName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename($filename)) ?: 'course-resource';
            $headers['Content-Disposition'] = 'inline; filename="' . str_replace('"', '', $safeName) . '"';
        }
        return new self('', 200, $headers, $real);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        if (!str_starts_with($location, '/') || str_starts_with($location, '//')) {
            throw new InvalidArgumentException('Unsafe redirect location.');
        }
        return new self('', $status, ['Location' => $location, 'Cache-Control' => 'no-store']);
    }

    public function send(): void
    {
        if ($this->filePath !== null) {
            $this->sendFile();
            return;
        }
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

    private function sendFile(): void
    {
        $size = filesize($this->filePath);
        if (!is_int($size) || $size < 0) {
            throw new RuntimeException('The response file size is unavailable.');
        }
        $start = 0;
        $end = max(0, $size - 1);
        $status = $this->status;
        $range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
        if ($range !== '') {
            if (preg_match('/^bytes=(\d*)-(\d*)$/', $range, $match) !== 1) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return;
            }
            $left = $match[1];
            $right = $match[2];
            if ($left === '' && $right === '') {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return;
            }
            if ($left === '') {
                $suffix = min($size, max(0, (int) $right));
                $start = max(0, $size - $suffix);
            } else {
                $start = (int) $left;
            }
            if ($right !== '') {
                $end = min($end, (int) $right);
            }
            if ($start < 0 || $start >= $size || $end < $start) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return;
            }
            $status = 206;
        }
        $length = $size === 0 ? 0 : $end - $start + 1;
        http_response_code($status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        header('Content-Length: ' . $length);
        if ($status === 206) {
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD' || $length === 0) {
            return;
        }

        $handle = fopen($this->filePath, 'rb');
        if (!is_resource($handle)) {
            throw new RuntimeException('The response file could not be opened.');
        }
        try {
            if ($start > 0) {
                fseek($handle, $start);
            }
            $remaining = $length;
            while ($remaining > 0 && !feof($handle)) {
                $chunk = fread($handle, min(1024 * 1024, $remaining));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                if (function_exists('fastcgi_finish_request')) {
                    flush();
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private static function assertContentType(string $contentType): void
    {
        if (preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $contentType) !== 1) {
            throw new InvalidArgumentException('Invalid binary response content type.');
        }
    }
}
