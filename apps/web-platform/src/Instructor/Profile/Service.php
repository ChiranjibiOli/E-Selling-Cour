<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static fn (Request $request): array => RoomRuntime::load(__DIR__, $request);
