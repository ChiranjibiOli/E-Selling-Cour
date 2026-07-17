<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\CoursePlayer;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CoursePlayerService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'CoursePlayer';
    public const TITLE = 'Course player';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'learning-service + enrollment-service';
    public const LEGACY_SOURCE = 'app/views/student/course_view.php';
}
