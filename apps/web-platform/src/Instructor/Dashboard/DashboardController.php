<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Dashboard;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class DashboardController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new DashboardPage())->render((new DashboardService())->load($request));
    }
}
