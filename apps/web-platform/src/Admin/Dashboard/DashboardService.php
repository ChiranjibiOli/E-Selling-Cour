<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Dashboard;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class DashboardService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Dashboard';
    public const TITLE = 'Admin dashboard';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'reporting-service';
    public const LEGACY_SOURCE = 'app/views/admin/dashboard.php';
}
