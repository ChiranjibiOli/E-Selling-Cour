<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\EditCourse;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class EditCourseController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new EditCoursePage())->render((new EditCourseService())->load($request));
    }
}
