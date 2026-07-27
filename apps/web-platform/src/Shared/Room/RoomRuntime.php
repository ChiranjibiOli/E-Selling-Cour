<?php

declare(strict_types=1);
namespace CourseHub\WebPlatform\Shared\Room;

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\AdminConsole;
use CourseHub\WebPlatform\Shared\Ui\PanelFactory;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class RoomRuntime
{
    public static function metadata(string $directory): array
    {
        return RoomRegistry::fromDirectory($directory);
    }

    public static function authorize(string $directory, Request $request): void
    {
        $metadata = self::metadata($directory);
        $requiredRole = (string) ($metadata['role'] ?? 'guest');
        if ($requiredRole === 'guest') {
            return;
        }
        if ((string) ($_SESSION['role'] ?? '') !== $requiredRole) {
            throw new \DomainException('Access denied. Use the correct portal entrance.');
        }
    }

    public static function load(string $directory, Request $request): array
    {
        $metadata = self::metadata($directory);
        $role = (string) ($metadata['role'] ?? 'guest');
        $room = (string) ($metadata['room'] ?? '');
        if ($role === 'admin' && AdminConsole::supports($room)) {
            return AdminConsole::load($metadata, $request);
        }
        return ['metadata' => $metadata, 'method' => $request->method, 'query' => $request->query, 'input' => $request->body];
    }

    public static function render(string $directory, array $model): Response
    {
        $metadata = self::metadata($directory);
        $role = (string) ($metadata['role'] ?? 'guest');
        if (in_array($role, ['student', 'instructor', 'admin'], true)) {
            $room = (string) ($metadata['room'] ?? '');
            $panel = $role === 'admin' && AdminConsole::supports($room)
                ? AdminConsole::build($metadata, $model)
                : PanelFactory::build($metadata, $model);
            return PortalPage::render(
                $role,
                (string) ($metadata['title'] ?? $metadata['room']),
                $panel['content'],
                $panel['action'],
            );
        }

        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $escape($metadata['title'] ?? $metadata['room']);
        $floor = $escape($metadata['floor']);
        $room = $escape($metadata['room']);
        $assetBase = '/room-assets/' . rawurlencode((string) $metadata['floor']) . '/' . rawurlencode((string) $metadata['room']);

        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . ' | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="' . $assetBase . '/page.css"></head><body>'
            . '<header class="house-header"><a href="/">CourseHub</a><nav><a href="/courses">Courses</a><a href="/learn/sign-in">Student</a><a href="/teach/studio-access">Instructor</a></nav></header>'
            . '<main class="room-page" data-floor="' . $floor . '" data-room="' . $room . '"><span class="floor-label">' . $floor . ' floor</span><h1>' . $title . '</h1>'
            . '<dl><div><dt>Status</dt><dd>' . $escape($metadata['status']) . '</dd></div><div><dt>Backend owner</dt><dd>' . $escape($metadata['service']) . '</dd></div></dl>'
            . '<p>This feature is being implemented inside its owned room and backend domain.</p></main><script src="/assets/js/app.js" defer></script><script src="' . $assetBase . '/page.js" defer></script></body></html>';

        return Response::html($body);
    }
}
