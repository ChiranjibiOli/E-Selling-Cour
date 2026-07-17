<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\VerifyOtp;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class VerifyOtpService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'VerifyOtp';
    public const TITLE = 'Verify OTP';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service + notification-service';
    public const LEGACY_SOURCE = 'app/views/auth/verify_otp.php';
}
