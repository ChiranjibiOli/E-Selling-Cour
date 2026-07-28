<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
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
    $cartCourseIds = [];
    $selectedCourse = [];
    $messages = [];

    if ((string) ($request->query['access'] ?? '') === 'required') {
        $messages[] = 'This course is not active in your learning library. Complete checkout and payment verification before opening the course player.';
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
    } catch (DomainException $exception) {
        $messages[] = 'Your purchased-course list could not be checked. Refresh before adding a course to the cart. ' . $exception->getMessage();
    }

    try {
        $cart = $api->get('/api/v1/cart')['data'] ?? [];
        $cartItems = is_array($cart['items'] ?? null) ? $cart['items'] : [];
        foreach ($cartItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $courseId = (int) ($item['course_id'] ?? 0);
            if ($courseId > 0) {
                $cartCourseIds[$courseId] = true;
            }
        }
    } catch (DomainException $exception) {
        $messages[] = 'Your cart could not be checked. Refresh the catalogue before adding another course. ' . $exception->getMessage();
    }

    if ($ownedCourseIds !== []) {
        $courses = array_values(array_filter(
            $courses,
            static fn (array $course): bool => !isset($ownedCourseIds[(int) ($course['id'] ?? 0)]),
        ));
    }

    if ($selectedCourseId > 0 && isset($ownedCourseIds[$selectedCourseId])) {
        return Response::redirect('/student/my-courses');
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
        $cartCourseIds,
        $selectedCourse,
        [
            'q' => $query,
            'category' => $category,
            'level' => $level,
        ],
        implode(' ', array_values(array_unique(array_filter($messages)))),
    );
};
