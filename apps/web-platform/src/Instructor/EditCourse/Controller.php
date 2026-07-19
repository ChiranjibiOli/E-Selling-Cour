<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $courseId = filter_var($request->query['id'] ?? null, FILTER_VALIDATE_INT);
    if ($courseId === false || $courseId < 1) {
        throw new DomainException('Choose a valid course.');
    }
    $client = new ApiClient();
    $message = '';
    $success = true;
    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $result = $client->request('PUT', '/api/v1/courses/' . $courseId, $request->body);
            $message = (string) ($result['message'] ?? 'Course updated.');
        }
        $course = $client->get('/api/v1/courses/' . $courseId . '/edit')['data'] ?? [];
        $categories = $client->get('/api/v1/categories?limit=50')['data'] ?? [];
    } catch (DomainException $exception) {
        $course = $course ?? $request->body;
        $course['id'] = $courseId;
        $categories = $categories ?? [];
        $message = $exception->getMessage();
        $success = false;
    }
    return EditCoursePage::render($course, $categories, $message, $success);
};
