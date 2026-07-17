<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Progress;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class ProgressService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Progress';
    public const TITLE = 'Learning progress';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'learning-service';
    public const LEGACY_SOURCE = 'app/views/student/progress.php';
}
