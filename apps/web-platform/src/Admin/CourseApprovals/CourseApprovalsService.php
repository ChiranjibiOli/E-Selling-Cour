<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\CourseApprovals;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CourseApprovalsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'CourseApprovals';
    public const TITLE = 'Course approvals';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service';
    public const LEGACY_SOURCE = 'app/views/admin/courses.php';
}
