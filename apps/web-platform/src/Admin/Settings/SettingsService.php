<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Settings;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class SettingsService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Settings';
    public const TITLE = 'Platform settings';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'reporting-service';
    public const LEGACY_SOURCE = 'app/views/admin/settings.php';
}
