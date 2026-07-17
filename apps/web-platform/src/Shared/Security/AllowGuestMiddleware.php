<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Security;

use CourseHub\WebPlatform\Shared\Contracts\RoomMiddleware;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

class AllowGuestMiddleware implements RoomMiddleware
{
    public function check(Request $request): ?Response
    {
        return null;
    }
}
