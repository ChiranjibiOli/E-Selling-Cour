<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\PaymentHistory;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class PaymentHistoryController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new PaymentHistoryPage())->render((new PaymentHistoryService())->load($request));
    }
}
