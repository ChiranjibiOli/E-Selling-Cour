<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\CourseSearch;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CourseSearchService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'CourseSearch';
    public const TITLE = 'Search courses';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service';
    public const LEGACY_SOURCE = 'app/views/courses/search.php';
}
