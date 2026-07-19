<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    try {
        $client = new ApiClient();
        $instructors = $client->get('/api/v1/users/instructor-applications')['data'] ?? [];
        $courses = $client->get('/api/v1/courses/pending')['data'] ?? [];
        return AdminDashboardPage::render(count($instructors), count($courses));
    } catch (DomainException $exception) {
        return AdminDashboardPage::render(0, 0, $exception->getMessage());
    }
};
