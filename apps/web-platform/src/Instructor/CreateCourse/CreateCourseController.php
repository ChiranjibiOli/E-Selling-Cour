<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\CreateCourse;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CreateCourseController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CreateCoursePage())->render((new CreateCourseService())->load($request));
    }
}
