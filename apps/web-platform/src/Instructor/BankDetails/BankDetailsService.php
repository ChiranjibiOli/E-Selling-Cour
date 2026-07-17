<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\BankDetails;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class BankDetailsService extends AbstractRoomService
{
    public const FLOOR = 'Instructor';
    public const ROOM = 'BankDetails';
    public const TITLE = 'Bank details';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'reporting-service';
    public const LEGACY_SOURCE = 'app/views/instructor/bank_details.php';
}
