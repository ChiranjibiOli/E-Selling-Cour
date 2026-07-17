<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Dashboard;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class DashboardService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Dashboard';
    public const TITLE = 'Student dashboard';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'enrollment-service + learning-service + notification-service';
    public const LEGACY_SOURCE = 'app/views/student/dashboard.php';
}
