<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Orders;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class OrdersService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Orders';
    public const TITLE = 'Orders';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'commerce-service';
    public const LEGACY_SOURCE = 'order workflow';
}
