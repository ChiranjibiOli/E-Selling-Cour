<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\CourseDetails;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CourseDetailsService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'CourseDetails';
    public const TITLE = 'Course details';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service + review-service';
    public const LEGACY_SOURCE = 'app/views/courses/details.php';
}
