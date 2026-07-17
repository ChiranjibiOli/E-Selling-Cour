<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\MyCourses;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class MyCoursesService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'MyCourses';
    public const TITLE = 'My courses';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'enrollment-service + catalog-service';
    public const LEGACY_SOURCE = 'app/views/student/my_courses.php';
}
