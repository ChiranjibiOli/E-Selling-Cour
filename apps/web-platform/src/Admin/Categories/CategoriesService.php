<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Categories;

use CourseHub\WebPlatform\Shared\Room\AbstractRoomService;

final class CategoriesService extends AbstractRoomService
{
    public const FLOOR = 'Admin';
    public const ROOM = 'Categories';
    public const TITLE = 'Course categories';
    public const MIGRATION_STATUS = 'compatibility-backed';
    public const BACKEND_SERVICE = 'catalog-service';
    public const LEGACY_SOURCE = 'app/views/admin/categories.php';
}
