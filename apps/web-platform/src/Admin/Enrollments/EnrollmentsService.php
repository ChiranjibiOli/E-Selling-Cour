<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Enrollments;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class EnrollmentsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Enrollments';
    public const TITLE = 'Manage enrollments';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'enrollment-service';
    public const LEGACY_SOURCE = 'app/views/admin/enrollments.php';
}
