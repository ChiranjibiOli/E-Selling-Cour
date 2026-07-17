<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\EditCourse;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class EditCourseService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'EditCourse';
    public const TITLE = 'Edit course';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service + learning-service + media-service';
    public const LEGACY_SOURCE = 'app/views/instructor/edit_course.php';
}
