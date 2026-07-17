<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Shared;

use CourseHub\WebPlatform\Shared\Security\RequireRoleMiddleware;

class AdminAuthMiddleware extends RequireRoleMiddleware
{
    protected const ROLE = 'admin';
}
