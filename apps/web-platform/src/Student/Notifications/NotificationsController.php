<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Notifications;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class NotificationsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new NotificationsPage())->render((new NotificationsService())->load($request));
    }
}
