<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Lessons;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class LessonsService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'Lessons';
    public const TITLE = 'Course lessons';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'learning-service + media-service';
    public const LEGACY_SOURCE = 'app/views/instructor/lessons.php';
}
