<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Withdrawals;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class WithdrawalsService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'Withdrawals';
    public const TITLE = 'Withdrawal requests';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'reporting-service + payment-service';
    public const LEGACY_SOURCE = 'app/views/instructor/payment_requests.php';
}
