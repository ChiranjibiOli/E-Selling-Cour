<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static function (Request $request): Response {
    RoomRuntime::authorize(__DIR__, $request);
    return Response::redirect('/instructor/courses?status=draft');
};
