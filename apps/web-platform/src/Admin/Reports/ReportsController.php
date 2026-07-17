<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Reports;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class ReportsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new ReportsPage())->render((new ReportsService())->load($request));
    }
}
