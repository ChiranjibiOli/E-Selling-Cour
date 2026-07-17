<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Dashboard;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class DashboardService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'Dashboard';
    public const TITLE = 'Instructor dashboard';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service + reporting-service';
    public const LEGACY_SOURCE = 'app/views/instructor/dashboard.php';
}
