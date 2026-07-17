<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Lessons;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class LessonsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new LessonsPage())->render((new LessonsService())->load($request));
    }
}
