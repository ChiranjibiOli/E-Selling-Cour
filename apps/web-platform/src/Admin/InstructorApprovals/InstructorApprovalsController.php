<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\InstructorApprovals;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class InstructorApprovalsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new InstructorApprovalsPage())->render((new InstructorApprovalsService())->load($request));
    }
}
