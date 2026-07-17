<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Orders;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class OrdersController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new OrdersPage())->render((new OrdersService())->load($request));
    }
}
