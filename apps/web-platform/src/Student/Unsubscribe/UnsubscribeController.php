<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Unsubscribe;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class UnsubscribeController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new UnsubscribePage())->render((new UnsubscribeService())->load($request));
    }
}
