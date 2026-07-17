<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Users;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class UsersService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Users';
    public const TITLE = 'All users';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'identity-service';
    public const LEGACY_SOURCE = 'app/views/admin/users.php';
}
