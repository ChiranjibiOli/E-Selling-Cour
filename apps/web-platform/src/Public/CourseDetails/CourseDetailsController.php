<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\CourseDetails;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CourseDetailsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CourseDetailsPage())->render((new CourseDetailsService())->load($request));
    }
}
