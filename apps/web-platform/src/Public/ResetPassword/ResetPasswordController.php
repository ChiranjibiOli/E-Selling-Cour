<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\ResetPassword;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class ResetPasswordController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new ResetPasswordPage())->render((new ResetPasswordService())->load($request));
    }
}
