<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Faq;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class FaqService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'FAQ';
    public const TITLE = 'Frequently asked questions';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'none';
    public const LEGACY_SOURCE = 'app/views/home/faq.php';
}
