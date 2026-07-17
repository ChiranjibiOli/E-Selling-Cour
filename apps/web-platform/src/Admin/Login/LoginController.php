<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Login;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class LoginController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new LoginPage())->render((new LoginService())->load($request));
    }
}
