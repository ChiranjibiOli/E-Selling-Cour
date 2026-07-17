<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Coupons;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CouponsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Coupons';
    public const TITLE = 'Coupon administration';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'commerce-service';
    public const LEGACY_SOURCE = 'app/views/admin/coupons.php';
}
