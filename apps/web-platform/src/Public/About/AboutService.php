<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\About;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class AboutService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'About';
    public const TITLE = 'About CourseHub';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'none';
    public const LEGACY_SOURCE = 'app/views/home/about.php';
}
