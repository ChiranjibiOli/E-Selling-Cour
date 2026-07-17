<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\About;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class AboutController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new AboutPage())->render((new AboutService())->load($request));
    }
}
