<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\InstructorApprovals;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class InstructorApprovalsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'InstructorApprovals';
    public const TITLE = 'Instructor approvals';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service + media-service';
    public const LEGACY_SOURCE = 'app/views/admin/instructors.php';
}
