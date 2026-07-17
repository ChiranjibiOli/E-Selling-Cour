<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\CreateCourse;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CreateCourseService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'CreateCourse';
    public const TITLE = 'Create course';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service + learning-service + media-service';
    public const LEGACY_SOURCE = 'app/views/instructor/create_course.php';
}
