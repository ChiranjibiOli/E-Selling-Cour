<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Registration;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class RegistrationService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Registration';
    public const TITLE = 'Student registration';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/auth/register.php';
}
