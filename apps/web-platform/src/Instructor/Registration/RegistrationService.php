<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Registration;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class RegistrationService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'Registration';
    public const TITLE = 'Instructor application';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service + media-service';
    public const LEGACY_SOURCE = 'app/views/auth/register.php';
}
