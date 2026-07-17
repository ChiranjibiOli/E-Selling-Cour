<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Contact;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class ContactService extends AbstractRoomService
{
    public const FLOOR = 'Public';
    public const ROOM = 'Contact';
    public const TITLE = 'Contact CourseHub';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'notification-service';
    public const LEGACY_SOURCE = 'app/views/home/contact.php';
}
