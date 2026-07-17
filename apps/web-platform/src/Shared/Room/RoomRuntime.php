<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Room;

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class RoomRuntime
{
    /** @return array<string, mixed> */
    public static function metadata(string $directory): array
    {
        return RoomRegistry::fromDirectory($directory);
    }

    public static function authorize(string $directory, Request $request): void
    {
        $metadata = self::metadata($directory);
        $requiredRole = $metadata['role'] ?? null;

        if ($requiredRole === null || $requiredRole === 'guest') {
            return;
        }

        $role = (string) ($_SESSION['role'] ?? '');
        if ($role !== $requiredRole) {
            throw new \RuntimeException('Access denied for this floor.');
        }
    }

    /** @return array<string, mixed> */
    public static function load(string $directory, Request $request): array
    {
        $metadata = self::metadata($directory);
        return [
            'metadata' => $metadata,
            'method' => $request->method(),
            'query' => $request->query(),
            'input' => $request->input(),
        ];
    }

    /** @param array<string, mixed> $model */
    public static function render(string $directory, array $model): Response
    {
        $metadata = self::metadata($directory);
        $title = htmlspecialchars((string) ($metadata['title'] ?? $metadata['room']), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string) ($metadata['status'] ?? 'planned'), ENT_QUOTES, 'UTF-8');
        $service = htmlspecialchars((string) ($metadata['service'] ?? 'none'), ENT_QUOTES, 'UTF-8');

        $body = '<main class="room-page"><h1>' . $title . '</h1>'
            . '<p>Room status: <strong>' . $status . '</strong></p>'
            . '<p>Backend service: <code>' . $service . '</code></p></main>';

        return Response::html($body);
    }
}
