<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\InstructorRegistration;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class InstructorRegistrationService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'InstructorRegistration';
    public const TITLE = 'Apply as instructor';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service + media-service';
    public const LEGACY_SOURCE = 'app/views/auth/register.php';
}
