<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Login;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class LoginService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Login';
    public const TITLE = 'Student login';
    public const MIGRATION_STATUS = 'identity-api-ready';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/auth/login.php';
}
