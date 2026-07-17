<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static fn (array $model) => RoomRuntime::render(__DIR__, $model);
