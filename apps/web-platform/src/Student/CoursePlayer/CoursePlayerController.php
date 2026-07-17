<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\CoursePlayer;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CoursePlayerController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CoursePlayerPage())->render((new CoursePlayerService())->load($request));
    }
}
