<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Room;

final class RoomRegistry
{
    public static function all(): array
    {
        return require dirname(__DIR__, 2) . '/config/rooms.php';
    }

    public static function fromDirectory(string $directory): array
    {
        $room = basename($directory);
        $floor = basename(dirname($directory));
        $key = $floor . '/' . $room;
        $rooms = self::all();
        if (!isset($rooms[$key])) {
            throw new \RuntimeException('Room is not registered: ' . $key);
        }
        return $rooms[$key] + ['key' => $key, 'floor' => $floor, 'room' => $room];
    }
}
