<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Unsubscribe;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class UnsubscribeService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'UnsubscribeRequest';
    public const TITLE = 'Course unsubscribe request';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'enrollment-service';
    public const LEGACY_SOURCE = 'app/views/student/unsubscribe_request.php';
}
