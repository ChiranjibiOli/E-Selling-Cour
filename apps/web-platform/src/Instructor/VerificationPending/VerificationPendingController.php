<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\VerificationPending;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class VerificationPendingController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new VerificationPendingPage())->render((new VerificationPendingService())->load($request));
    }
}
