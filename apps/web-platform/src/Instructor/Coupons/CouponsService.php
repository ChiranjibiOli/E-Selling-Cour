<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Coupons;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CouponsService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'Coupons';
    public const TITLE = 'Course coupons';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'commerce-service';
    public const LEGACY_SOURCE = 'app/views/instructor/coupons.php';
}
