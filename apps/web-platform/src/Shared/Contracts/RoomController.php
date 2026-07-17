<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Contracts;

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

interface RoomController
{
    public function handle(Request $request): Response;
}
