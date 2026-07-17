<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Landing;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class LandingController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new LandingPage())->render((new LandingService())->load($request));
    }
}
