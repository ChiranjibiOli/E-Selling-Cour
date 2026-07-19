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
    $courseId = (int) ($request->query['course'] ?? $request->body['course_id'] ?? 0);
    $lessonId = (int) ($request->query['lesson'] ?? $request->body['lesson_id'] ?? 0);
    $message = '';
    $success = true;

    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $result = $client->post('/api/v1/progress/lessons/' . $lessonId . '/complete', []);
            $message = (string) ($result['message'] ?? 'Lesson marked complete.');
        }
        if ($courseId < 1) {
            $courses = $client->get('/api/v1/enrollments/mine')['data'] ?? [];
            $courseId = (int) ($courses[0]['course_id'] ?? 0);
        }
        $course = $courseId > 0 ? ($client->get('/api/v1/learning/courses/' . $courseId . '/player')['data'] ?? []) : [];
    } catch (DomainException $exception) {
        $course = [];
        $message = $exception->getMessage();
        $success = false;
    }

    return StudentCoursePlayerPage::render($course, $lessonId, $message, $success);
};
