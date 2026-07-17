<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Profile;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class ProfileService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'Profile';
    public const TITLE = 'Instructor profile';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service + media-service';
    public const LEGACY_SOURCE = 'app/views/instructor/profile.php';
}
