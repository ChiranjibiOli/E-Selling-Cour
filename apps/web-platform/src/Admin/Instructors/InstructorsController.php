<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Instructors;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class InstructorsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new InstructorsPage())->render((new InstructorsService())->load($request));
    }
}
