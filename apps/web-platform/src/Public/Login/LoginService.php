<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Login;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class LoginService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'LoginSelector';
    public const TITLE = 'Choose your portal';
    public const MIGRATION_STATUS = 'new-room';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/auth/login.php';
}
