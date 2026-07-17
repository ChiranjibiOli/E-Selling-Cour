<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\StudentRegistration;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class StudentRegistrationService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'StudentRegistration';
    public const TITLE = 'Create student account';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/auth/register.php';
}
