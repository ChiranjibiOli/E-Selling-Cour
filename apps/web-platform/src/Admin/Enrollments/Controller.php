<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $message = '';
    $success = true;

    try {
        $enrollments = $client->get('/api/v1/enrollments')['data'] ?? [];
    } catch (DomainException $exception) {
        $enrollments = [];
        $message = $exception->getMessage();
        $success = false;
    }

    return AdminEnrollmentsPage::render(
        is_array($enrollments) ? $enrollments : [],
        $message,
        $success,
    );
};
