<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Checkout;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CheckoutService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Checkout';
    public const TITLE = 'Checkout';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'commerce-service + payment-service';
    public const LEGACY_SOURCE = 'app/views/cart/checkout.php';
}
