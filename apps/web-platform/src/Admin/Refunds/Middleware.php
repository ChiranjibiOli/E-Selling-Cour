<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static function (Request $request): void {
    RoomRuntime::authorize(__DIR__, $request);
};
