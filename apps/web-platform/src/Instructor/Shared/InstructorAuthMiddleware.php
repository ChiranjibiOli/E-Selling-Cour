<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Shared;

use CourseHub\WebPlatform\Shared\Security\RequireRoleMiddleware;

class InstructorAuthMiddleware extends RequireRoleMiddleware
{
    protected const ROLE = 'instructor';
}
