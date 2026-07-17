<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Payments;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class PaymentsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Payments';
    public const TITLE = 'Payment verification';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'payment-service + enrollment-service';
    public const LEGACY_SOURCE = 'app/views/admin/payments.php';
}
