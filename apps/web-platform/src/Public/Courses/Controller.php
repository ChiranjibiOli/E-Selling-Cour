<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Service.php';
require_once __DIR__ . '/ViewModel.php';
require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    $filters = CourseCatalogRequest::from($request->query);
    CourseCatalogValidator::validate($filters);
    $data = (new CourseCatalogService())->load($filters);
    $courses = is_array($data['courses'] ?? null) ? $data['courses'] : [];

    if (AuthSession::role() === 'student') {
        try {
            $enrollments = (new ApiClient())->get('/api/v1/enrollments/mine')['data'] ?? [];
            $ownedCourseIds = [];
            foreach (is_array($enrollments) ? $enrollments : [] as $enrollment) {
                $courseId = (int) ($enrollment['course_id'] ?? 0);
                if ($courseId > 0 && (string) ($enrollment['status'] ?? '') === 'active') {
                    $ownedCourseIds[$courseId] = true;
                }
            }
            if ($ownedCourseIds !== []) {
                $courses = array_values(array_filter(
                    $courses,
                    static fn (array $course): bool => !isset($ownedCourseIds[(int) ($course['id'] ?? 0)]),
                ));
            }
        } catch (DomainException) {
            // The catalogue remains available if enrollment history is temporarily unavailable.
        }
    }

    return CourseCatalogPage::render(new CourseCatalogViewModel(
        $filters,
        $courses,
        is_array($data['categories'] ?? null) ? $data['categories'] : [],
        (bool) ($data['available'] ?? false),
    ));
};
