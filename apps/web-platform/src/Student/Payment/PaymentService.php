<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Payment;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class PaymentService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Payment';
    public const TITLE = 'Submit payment';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'payment-service + media-service';
    public const LEGACY_SOURCE = 'app/views/payments/manual_payment.php';
}
