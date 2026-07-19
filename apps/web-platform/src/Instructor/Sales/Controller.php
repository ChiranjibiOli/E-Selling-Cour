<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    try {
        $sales = (new ApiClient())->get('/api/v1/reports/instructor-sales')['data'] ?? [];
        return InstructorSalesPage::render($sales);
    } catch (DomainException $exception) {
        return InstructorSalesPage::render([], $exception->getMessage());
    }
};
