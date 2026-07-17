<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Users;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class UsersController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new UsersPage())->render((new UsersService())->load($request));
    }
}
