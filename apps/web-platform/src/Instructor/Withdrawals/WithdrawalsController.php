<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Withdrawals;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class WithdrawalsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new WithdrawalsPage())->render((new WithdrawalsService())->load($request));
    }
}
