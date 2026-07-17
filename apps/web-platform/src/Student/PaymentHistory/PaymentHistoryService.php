<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\PaymentHistory;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class PaymentHistoryService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'PaymentHistory';
    public const TITLE = 'Payment history';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'payment-service';
    public const LEGACY_SOURCE = 'app/views/payments/payment_history.php';
}
