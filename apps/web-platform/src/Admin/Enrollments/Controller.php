<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    try {
        $enrollments = (new ApiClient())->get('/api/v1/enrollments')['data'] ?? [];
        return AdminEnrollmentsPage::render($enrollments);
    } catch (DomainException $exception) {
        return AdminEnrollmentsPage::render([], $exception->getMessage());
    }
};
