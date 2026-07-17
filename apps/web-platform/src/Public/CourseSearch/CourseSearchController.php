<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\CourseSearch;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CourseSearchController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CourseSearchPage())->render((new CourseSearchService())->load($request));
    }
}
