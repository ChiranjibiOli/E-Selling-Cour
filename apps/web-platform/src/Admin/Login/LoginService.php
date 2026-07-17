<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Login;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class LoginService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Login';
    public const TITLE = 'Administrator login';
    public const MIGRATION_STATUS = 'identity-api-ready';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/auth/login.php';
}
