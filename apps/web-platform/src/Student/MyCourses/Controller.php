<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);

    try {
        $api = new ApiClient();
        $courses = $api->get('/api/v1/enrollments/mine')['data'] ?? [];
        $progressByCourse = [];

        try {
            $progressRows = $api->get('/api/v1/progress/mine')['data'] ?? [];
            foreach ($progressRows as $row) {
                $courseId = (int) ($row['course_id'] ?? 0);
                if ($courseId > 0) {
                    $progressByCourse[$courseId] = $row;
                }
            }
        } catch (DomainException) {
            // Enrollment access remains usable even during a temporary progress-service failure.
        }

        foreach ($courses as &$course) {
            $courseId = (int) ($course['course_id'] ?? 0);
            $progress = $progressByCourse[$courseId] ?? [];
            $totalLessons = max(0, (int) ($progress['total_lessons'] ?? $course['lesson_count'] ?? 0));
            $completedLessons = max(0, min($totalLessons, (int) ($progress['completed_lessons'] ?? 0)));
            $progressPercent = $totalLessons > 0
                ? min(100, (int) floor(($completedLessons / $totalLessons) * 100))
                : 0;

            $course['total_lessons'] = $totalLessons;
            $course['completed_lessons'] = $completedLessons;
            $course['progress_percent'] = $progressPercent;
            $course['is_completed'] = $totalLessons > 0 && $completedLessons === $totalLessons;
            $course['last_activity'] = $progress['last_activity'] ?? null;
        }
        unset($course);

        return StudentMyCoursesPage::render($courses);
    } catch (DomainException $exception) {
        return StudentMyCoursesPage::render([], $exception->getMessage());
    }
};
