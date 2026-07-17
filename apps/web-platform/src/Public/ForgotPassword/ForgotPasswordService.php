<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\ForgotPassword;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class ForgotPasswordService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'ForgotPassword';
    public const TITLE = 'Forgot password';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service + notification-service';
    public const LEGACY_SOURCE = 'app/views/auth/forgot_password.php';
}
