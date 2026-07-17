<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Cart;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CartService extends AbstractRoomService
{
    public const FLOOR = 'Student';
    public const ROOM = 'Cart';
    public const TITLE = 'Shopping cart';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'commerce-service';
    public const LEGACY_SOURCE = 'app/views/cart/index.php';
}
