<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Courses;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CoursesController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CoursesPage())->render((new CoursesService())->load($request));
    }
}
