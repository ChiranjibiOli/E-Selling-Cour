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
            $id = filter_var($request->body['course_id'] ?? null, FILTER_VALIDATE_INT);
            $decision = (string) ($request->body['decision'] ?? '');
            if ($id === false || $id < 1 || !in_array($decision, ['approve', 'reject'], true)) {
                throw new DomainException('Choose a valid course decision.');
            }
            $result = $client->post('/api/v1/courses/' . $id . '/' . $decision, ['note' => (string) ($request->body['note'] ?? '')]);
            $message = (string) ($result['message'] ?? 'Course reviewed.');
        }
        $courses = $client->get('/api/v1/courses/pending')['data'] ?? [];
    } catch (DomainException $exception) {
        $courses = [];
        $message = $exception->getMessage();
        $success = false;
    }
    return CourseApprovalsPage::render($courses, $message, $success);
};
