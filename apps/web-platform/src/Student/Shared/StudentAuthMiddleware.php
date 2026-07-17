<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Shared;

use CourseHub\WebPlatform\Shared\Security\RequireRoleMiddleware;

class StudentAuthMiddleware extends RequireRoleMiddleware
{
    protected const ROLE = 'student';
}
