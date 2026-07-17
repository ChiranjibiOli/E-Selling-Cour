<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Checkout;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CheckoutController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CheckoutPage())->render((new CheckoutService())->load($request));
    }
}
