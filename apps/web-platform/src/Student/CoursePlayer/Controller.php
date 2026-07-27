<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Media\PrivateMedia;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;

require_once __DIR__ . '/Page.php';

return static function (Request $request): Response {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $courseId = filter_var($request->query['course'] ?? $request->body['course_id'] ?? 0, FILTER_VALIDATE_INT);
    $lessonId = filter_var($request->query['lesson'] ?? $request->body['lesson_id'] ?? 0, FILTER_VALIDATE_INT);
    $courseId = $courseId !== false && $courseId > 0 ? (int) $courseId : 0;
    $lessonId = $lessonId !== false && $lessonId > 0 ? (int) $lessonId : 0;
    $message = (string) ($request->query['completed'] ?? '') === '1' ? 'Lesson marked complete.' : '';
    $success = true;

    try {
        if ($courseId < 1) {
            $courses = $client->get('/api/v1/enrollments/mine')['data'] ?? [];
            foreach ((array) $courses as $enrollment) {
                if ((string) ($enrollment['status'] ?? '') === 'active') {
                    $courseId = (int) ($enrollment['course_id'] ?? 0);
                    break;
                }
            }
        }
        $course = $courseId > 0 ? ($client->get('/api/v1/learning/courses/' . $courseId . '/player')['data'] ?? []) : [];

        if ($request->method === 'GET' && (string) ($request->query['resource'] ?? '') === '1') {
            if ($lessonId < 1 || !is_array($course)) {
                return Response::html('', 404);
            }
            foreach ((array) ($course['sections'] ?? []) as $section) {
                foreach ((array) ($section['lessons'] ?? []) as $lesson) {
                    if ((int) ($lesson['id'] ?? 0) !== $lessonId) {
                        continue;
                    }
                    $storedPath = trim((string) ($lesson['content_url'] ?? ''));
                    $contentName = trim((string) ($lesson['content_name'] ?? ''));
                    return PrivateMedia::response(
                        $storedPath,
                        ['private/course-content'],
                        $contentName !== '' ? $contentName : null,
                    );
                }
            }
            return Response::html('', 404);
        }

        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $courseId = FormInput::integer($request->body, 'course_id', 'Course', 1, PHP_INT_MAX);
            $lessonId = FormInput::integer($request->body, 'lesson_id', 'Lesson', 1, PHP_INT_MAX);
            $client->post('/api/v1/progress/lessons/' . $lessonId . '/complete', []);
            $course = $client->get('/api/v1/learning/courses/' . $courseId . '/player')['data'] ?? [];
            $lessons = [];
            foreach ((array) ($course['sections'] ?? []) as $section) {
                foreach ((array) ($section['lessons'] ?? []) as $lesson) {
                    $lessons[] = $lesson;
                }
            }
            $destinationLesson = $lessonId;
            foreach ($lessons as $index => $lesson) {
                if ((int) ($lesson['id'] ?? 0) === $lessonId) {
                    $next = $lessons[$index + 1] ?? null;
                    if (is_array($next)) {
                        $destinationLesson = (int) ($next['id'] ?? $lessonId);
                    }
                    break;
                }
            }
            return Response::redirect('/student/course-player?course=' . $courseId . '&lesson=' . $destinationLesson . '&completed=1');
        }

        $changes = $courseId > 0 ? ($client->get('/api/v1/courses/' . $courseId . '/change-log')['data'] ?? []) : [];
    } catch (DomainException $exception) {
        $course = [];
        $changes = [];
        $message = $exception->getMessage();
        $success = false;
    }

    return StudentCoursePlayerPage::render(
        is_array($course) ? $course : [],
        $lessonId,
        $message,
        $success,
        is_array($changes) ? $changes : [],
    );
};
