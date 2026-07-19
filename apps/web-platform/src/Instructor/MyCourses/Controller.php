<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $message = '';
    $success = true;
    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $courseId = filter_var($request->body['course_id'] ?? null, FILTER_VALIDATE_INT);
            if ($courseId === false || $courseId < 1) {
                throw new DomainException('Choose a valid course.');
            }
            $result = $client->post('/api/v1/courses/' . $courseId . '/submit', []);
            $message = (string) ($result['message'] ?? 'Course submitted.');
        }
        $courses = $client->get('/api/v1/courses/mine')['data'] ?? [];
    } catch (DomainException $exception) {
        $courses = [];
        $message = $exception->getMessage();
        $success = false;
    }
    return InstructorCoursesPage::render($courses, $message, $success);
};
