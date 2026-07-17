<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Reports;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class ReportsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Reports';
    public const TITLE = 'Reports';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'reporting-service';
    public const LEGACY_SOURCE = 'app/views/admin/reports.php';
}
