<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Courses;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CoursesService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'Courses';
    public const TITLE = 'Browse courses';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service';
    public const LEGACY_SOURCE = 'app/views/courses/list.php';
}
