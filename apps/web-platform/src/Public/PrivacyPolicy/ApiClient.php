<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static fn (): array => RoomRuntime::metadata(__DIR__);
