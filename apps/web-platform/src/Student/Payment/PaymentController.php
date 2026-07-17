<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Payment;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class PaymentController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new PaymentPage())->render((new PaymentService())->load($request));
    }
}
