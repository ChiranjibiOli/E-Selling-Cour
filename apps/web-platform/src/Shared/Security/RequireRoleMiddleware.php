<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Security;

use CourseHub\WebPlatform\Shared\Contracts\RoomMiddleware;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

abstract class RequireRoleMiddleware implements RoomMiddleware
{
    protected const ROLE = '';

    public function check(Request $request): ?Response
    {
        $role = (string) ($_SESSION['user']['role'] ?? '');
        if ($role === static::ROLE) {
            return null;
        }

        return Response::html('<h1>Unauthorized floor</h1><p>This entrance requires the ' . htmlspecialchars(static::ROLE) . ' role.</p>', 401);
    }
}
