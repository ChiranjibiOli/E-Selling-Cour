<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\ForgotPassword;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class ForgotPasswordController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new ForgotPasswordPage())->render((new ForgotPasswordService())->load($request));
    }
}
