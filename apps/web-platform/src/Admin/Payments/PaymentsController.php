<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Payments;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class PaymentsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new PaymentsPage())->render((new PaymentsService())->load($request));
    }
}
