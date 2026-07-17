<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Enrollments;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class EnrollmentsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new EnrollmentsPage())->render((new EnrollmentsService())->load($request));
    }
}
