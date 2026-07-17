<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    return RoomRuntime::render(__DIR__, RoomRuntime::load(__DIR__, $request));
};
