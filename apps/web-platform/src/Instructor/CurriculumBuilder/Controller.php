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
    $courseId = (int) ($request->query['course'] ?? $request->body['course_id'] ?? 0);

    try {
        $courses = $client->get('/api/v1/courses/mine')['data'] ?? [];
        if ($courseId < 1 && $courses !== []) {
            $courseId = (int) ($courses[0]['id'] ?? 0);
        }
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $action = (string) ($request->body['action'] ?? '');
            $payload = [
                'title' => trim((string) ($request->body['title'] ?? '')),
                'sort_order' => (int) ($request->body['sort_order'] ?? 1),
                'content_type' => trim((string) ($request->body['content_type'] ?? 'text')),
                'content_url' => trim((string) ($request->body['content_url'] ?? '')),
                'content_text' => trim((string) ($request->body['content_text'] ?? '')),
                'duration_minutes' => (int) ($request->body['duration_minutes'] ?? 0),
                'is_preview' => isset($request->body['is_preview']),
            ];
            $result = match ($action) {
                'add_section' => $client->post('/api/v1/learning/courses/' . $courseId . '/sections', $payload),
                'update_section' => $client->request('PATCH', '/api/v1/learning/sections/' . (int) ($request->body['section_id'] ?? 0), $payload),
                'delete_section' => $client->request('DELETE', '/api/v1/learning/sections/' . (int) ($request->body['section_id'] ?? 0)),
                'add_lesson' => $client->post('/api/v1/learning/sections/' . (int) ($request->body['section_id'] ?? 0) . '/lessons', $payload),
                'update_lesson' => $client->request('PATCH', '/api/v1/learning/lessons/' . (int) ($request->body['lesson_id'] ?? 0), $payload),
                'delete_lesson' => $client->request('DELETE', '/api/v1/learning/lessons/' . (int) ($request->body['lesson_id'] ?? 0)),
                default => throw new DomainException('Choose a valid curriculum action.'),
            };
            $message = (string) ($result['message'] ?? 'Curriculum updated.');
        }
        $course = $courseId > 0 ? ($client->get('/api/v1/learning/courses/' . $courseId . '/manage')['data'] ?? []) : [];
    } catch (DomainException $exception) {
        $courses = $courses ?? [];
        $course = [];
        try {
            $course = $courseId > 0 ? ($client->get('/api/v1/learning/courses/' . $courseId . '/manage')['data'] ?? []) : [];
        } catch (DomainException) {
        }
        $message = $exception->getMessage();
        $success = false;
    }

    return InstructorCurriculumPage::render($courses, $courseId, $course, $message, $success);
};
