<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\ContactMessages;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class ContactMessagesService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'ContactMessages';
    public const TITLE = 'Contact messages';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'notification-service';
    public const LEGACY_SOURCE = 'app/views/admin/contact_messages.php';
}
