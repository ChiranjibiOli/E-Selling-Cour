<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $message = '';
    $success = true;

    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $courseId = FormInput::integer($request->body, 'course_id', 'Course', 1, PHP_INT_MAX);
            $action = FormInput::enum($request->body, 'action', 'Course action', ['submit_course', 'request_edit']);
            if ($action === 'request_edit') {
                $reason = FormInput::multiline($request->body, 'reason', 'Edit reason', 20, 1000);
                $result = $client->post('/api/v1/courses/' . $courseId . '/edit-permission/request', ['reason' => $reason]);
            } else {
                $result = $client->post('/api/v1/courses/' . $courseId . '/submit', []);
            }
            $message = (string) ($result['message'] ?? 'Course updated.');
        }
        $courses = $client->get('/api/v1/courses/mine')['data'] ?? [];
    } catch (DomainException $exception) {
        $courses = [];
        try {
            $courses = $client->get('/api/v1/courses/mine')['data'] ?? [];
        } catch (DomainException) {
        }
        $message = $exception->getMessage();
        $success = false;
    }

    return InstructorCoursesPage::render(is_array($courses) ? $courses : [], $message, $success);
};
