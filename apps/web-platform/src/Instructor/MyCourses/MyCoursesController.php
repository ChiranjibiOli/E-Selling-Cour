<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\MyCourses;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class MyCoursesController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new MyCoursesPage())->render((new MyCoursesService())->load($request));
    }
}
