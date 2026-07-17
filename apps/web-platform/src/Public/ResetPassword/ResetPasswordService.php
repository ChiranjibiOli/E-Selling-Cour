<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\ResetPassword;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class ResetPasswordService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'ResetPassword';
    public const TITLE = 'Reset password';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/auth/reset_password.php';
}
