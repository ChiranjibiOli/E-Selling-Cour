<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\MyCourses;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class MyCoursesService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'MyCourses';
    public const TITLE = 'Instructor courses';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service';
    public const LEGACY_SOURCE = 'app/views/instructor/courses.php';
}
