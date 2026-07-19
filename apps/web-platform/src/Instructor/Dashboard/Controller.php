<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    try {
        $data = (new ApiClient())->get('/api/v1/reports/instructor-dashboard')['data'] ?? [];
        return InstructorDashboardPage::render($data);
    } catch (DomainException $exception) {
        return InstructorDashboardPage::render([], $exception->getMessage());
    }
};
