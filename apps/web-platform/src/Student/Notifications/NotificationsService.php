<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Notifications;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class NotificationsService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Notifications';
    public const TITLE = 'Notifications';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'notification-service';
    public const LEGACY_SOURCE = 'app/views/student/notifications.php';
}
