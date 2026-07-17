<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Landing;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class LandingService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'Landing';
    public const TITLE = 'CourseHub home';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service + reporting-service';
    public const LEGACY_SOURCE = 'app/views/home/landing.php';
}
