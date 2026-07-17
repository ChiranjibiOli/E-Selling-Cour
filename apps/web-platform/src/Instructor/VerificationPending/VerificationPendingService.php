<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\VerificationPending;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class VerificationPendingService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'VerificationPending';
    public const TITLE = 'Instructor verification pending';
    public const MIGRATION_STATUS = 'new-room';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'instructor status workflow';
}
