<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Http;

use InvalidArgumentException;

final class Request
{
    private const MAX_QUERY_FIELDS = 80;
    private const MAX_BODY_FIELDS = 250;
    private const MAX_DEPTH = 4;
    private const MAX_QUERY_VALUE_BYTES = 8_000;
    private const MAX_BODY_VALUE_BYTES = 100_000;

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
            try {
                $decoded = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new InvalidArgumentException('The request contains malformed JSON.', 0, $exception);
            }
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('The JSON request body must be an object.');
            }
            $body = $decoded;
        }

        $queryCount = 0;
        $bodyCount = 0;
        $query = self::validatePayload($_GET, 0, $queryCount, self::MAX_QUERY_FIELDS, self::MAX_QUERY_VALUE_BYTES, 'query');
        $validatedBody = self::validatePayload($body, 0, $bodyCount, self::MAX_BODY_FIELDS, self::MAX_BODY_VALUE_BYTES, 'form');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            rtrim($path, '/') ?: '/',
            $query,
            $validatedBody,
            $_SERVER,
        );
    }

    private static function validatePayload(
        array $payload,
        int $depth,
        int &$fieldCount,
        int $maxFields,
        int $maxValueBytes,
        string $source,
    ): array {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('The ' . $source . ' contains too many nested levels.');
        }

        $validated = [];
        foreach ($payload as $key => $value) {
            $fieldCount++;
            if ($fieldCount > $maxFields) {
                throw new InvalidArgumentException('The ' . $source . ' contains too many fields.');
            }

            $key = (string) $key;
            if ($key === '' || strlen($key) > 100 || preg_match('/^[A-Za-z0-9_.:-]+$/', $key) !== 1) {
                throw new InvalidArgumentException('The ' . $source . ' contains an invalid field name.');
            }

            if (is_array($value)) {
                $validated[$key] = self::validatePayload(
                    $value,
                    $depth + 1,
                    $fieldCount,
                    $maxFields,
                    $maxValueBytes,
                    $source,
                );
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('The ' . $source . ' contains an unsupported value type.');
            }

            $string = (string) $value;
            if (strlen($string) > $maxValueBytes) {
                throw new InvalidArgumentException('A ' . $source . ' value exceeds the permitted size.');
            }
            if (str_contains($string, "\0")) {
                throw new InvalidArgumentException('The ' . $source . ' contains a null byte.');
            }
            if (function_exists('mb_check_encoding') && !mb_check_encoding($string, 'UTF-8')) {
                throw new InvalidArgumentException('The ' . $source . ' contains invalid text encoding.');
            }

            $validated[$key] = $string;
        }

        return $validated;
    }
}
