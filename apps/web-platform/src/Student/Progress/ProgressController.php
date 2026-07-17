<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Progress;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class ProgressController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new ProgressPage())->render((new ProgressService())->load($request));
    }
}
