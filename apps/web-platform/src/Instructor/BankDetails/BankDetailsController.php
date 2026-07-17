<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\BankDetails;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class BankDetailsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new BankDetailsPage())->render((new BankDetailsService())->load($request));
    }
}
