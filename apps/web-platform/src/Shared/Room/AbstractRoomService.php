<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Room;

use CourseHub\WebPlatform\Shared\Http\Request;

abstract class AbstractRoomService
{
    public function load(Request $request): array
    {
        return [
            'floor' => static::FLOOR,
            'room' => static::ROOM,
            'title' => static::TITLE,
            'migration_status' => static::MIGRATION_STATUS,
            'backend_service' => static::BACKEND_SERVICE,
            'legacy_source' => static::LEGACY_SOURCE,
            'path' => $request->path,
        ];
    }
}
