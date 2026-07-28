<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);

    try {
        $data = (new ApiClient())->get('/api/v1/reports/instructor-students')['data'] ?? [];
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $students = is_array($data['students'] ?? null) ? $data['students'] : [];
        return InstructorStudentsPage::render($summary, $students);
    } catch (DomainException $exception) {
        return InstructorStudentsPage::render([], [], $exception->getMessage());
    }
};
