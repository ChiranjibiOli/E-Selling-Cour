<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);

    $query = mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 120);
    $category = mb_substr(trim((string) ($request->query['category'] ?? '')), 0, 120);
    $level = strtolower(trim((string) ($request->query['level'] ?? '')));
    if (!in_array($level, ['', 'beginner', 'intermediate', 'advanced'], true)) {
        $level = '';
    }
    $selectedCourseId = filter_var($request->query['course'] ?? 0, FILTER_VALIDATE_INT);
    $selectedCourseId = $selectedCourseId !== false && $selectedCourseId > 0 ? (int) $selectedCourseId : 0;

    $api = new ApiClient();
    $courses = [];
    $categories = [];
    $ownedCourseIds = [];
    $selectedCourse = [];
    $messages = [];

    if ((string) ($request->query['access'] ?? '') === 'required') {
        $messages[] = 'This published course is not active in your learning library yet. Add it to your cart and complete enrollment before opening the course player.';
    }

    try {
        $parameters = array_filter([
            'q' => $query,
            'category' => $category,
            'level' => $level,
            'limit' => 48,
        ], static fn (mixed $value): bool => $value !== '');
        $path = '/api/v1/courses';
        if ($parameters !== []) {
            $path .= '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }
        $courses = $api->get($path)['data'] ?? [];
        if (!is_array($courses)) {
            $courses = [];
        }
    } catch (DomainException $exception) {
        $messages[] = $exception->getMessage();
    }

    try {
        $categories = $api->get('/api/v1/categories?limit=50')['data'] ?? [];
        if (!is_array($categories)) {
            $categories = [];
        }
    } catch (DomainException) {
        // The catalogue remains usable without category filters.
    }

    try {
        $enrollments = $api->get('/api/v1/enrollments/mine')['data'] ?? [];
        foreach (is_array($enrollments) ? $enrollments : [] as $enrollment) {
            $courseId = (int) ($enrollment['course_id'] ?? 0);
            if ($courseId > 0 && (string) ($enrollment['status'] ?? '') === 'active') {
                $ownedCourseIds[$courseId] = true;
            }
        }
    } catch (DomainException) {
        // Published courses must still be visible if enrollment history is temporarily unavailable.
    }

    if ($selectedCourseId > 0) {
        try {
            $selectedCourse = $api->get('/api/v1/courses/' . $selectedCourseId)['data'] ?? [];
            if (!is_array($selectedCourse)) {
                $selectedCourse = [];
            }
        } catch (DomainException $exception) {
            $messages[] = $exception->getMessage();
        }
    }

    return StudentCoursesPage::render(
        $courses,
        $categories,
        $ownedCourseIds,
        $selectedCourse,
        [
            'q' => $query,
            'category' => $category,
            'level' => $level,
        ],
        implode(' ', array_values(array_unique(array_filter($messages)))),
    );
};
