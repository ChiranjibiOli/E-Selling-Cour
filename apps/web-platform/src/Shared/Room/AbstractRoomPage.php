<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Room;

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\View\HouseLayout;

abstract class AbstractRoomPage
{
    public function render(array $data): Response
    {
        $content = '<section class="room-card">'
            . '<span class="room-floor">' . htmlspecialchars((string) $data['floor']) . '</span>'
            . '<h1>' . htmlspecialchars((string) $data['title']) . '</h1>'
            . '<p>Room: <strong>' . htmlspecialchars((string) $data['room']) . '</strong></p>'
            . '<p>Backend: <strong>' . htmlspecialchars((string) $data['backend_service']) . '</strong></p>'
            . '<p>Migration: <strong>' . htmlspecialchars((string) $data['migration_status']) . '</strong></p>'
            . '</section>';

        return Response::html(HouseLayout::render((string) $data['title'], $content));
    }
}
