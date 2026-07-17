<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Cart;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CartController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CartPage())->render((new CartService())->load($request));
    }
}
