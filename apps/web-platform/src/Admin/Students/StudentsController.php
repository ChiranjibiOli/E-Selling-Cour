<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Students;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class StudentsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new StudentsPage())->render((new StudentsService())->load($request));
    }
}
