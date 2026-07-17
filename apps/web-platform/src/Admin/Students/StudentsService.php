<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Students;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class StudentsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Students';
    public const TITLE = 'Manage students';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/admin/students.php';
}
