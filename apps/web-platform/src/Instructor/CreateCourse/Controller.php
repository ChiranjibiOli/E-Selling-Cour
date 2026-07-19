<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    try {
        $categories = $client->get('/api/v1/categories?limit=50')['data'] ?? [];
        if ($request->method === 'GET') {
            return CreateCoursePage::render($categories);
        }
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $created = $client->post('/api/v1/courses', $request->body);
        $courseId = (int) ($created['id'] ?? 0);
        if ($courseId < 1) {
            throw new DomainException('The catalog service did not return the new course identifier.');
        }
        if (($request->body['intent'] ?? 'draft') === 'curriculum') {
            return Response::redirect('/instructor/lessons?course=' . $courseId);
        }
        return CreateCoursePage::render($categories, [], (string) ($created['message'] ?? 'Course saved as a private draft.'), true);
    } catch (DomainException $exception) {
        return CreateCoursePage::render($categories ?? [], $request->body, $exception->getMessage(), false);
    }
};
