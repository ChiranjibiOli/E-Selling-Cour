<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\VerifyOtp;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class VerifyOtpController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new VerifyOtpPage())->render((new VerifyOtpService())->load($request));
    }
}
