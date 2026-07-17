<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Instructors;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class InstructorsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Instructors';
    public const TITLE = 'Manage instructors';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/admin/instructors.php';
}
