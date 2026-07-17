<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\CourseApprovals;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CourseApprovalsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CourseApprovalsPage())->render((new CourseApprovalsService())->load($request));
    }
}
