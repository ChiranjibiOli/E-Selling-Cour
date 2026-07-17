<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Coupons;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CouponsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CouponsPage())->render((new CouponsService())->load($request));
    }
}
