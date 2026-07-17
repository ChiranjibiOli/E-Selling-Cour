<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Students;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class StudentsService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'Students';
    public const TITLE = 'Enrolled students';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'enrollment-service';
    public const LEGACY_SOURCE = 'app/views/instructor/students.php';
}
