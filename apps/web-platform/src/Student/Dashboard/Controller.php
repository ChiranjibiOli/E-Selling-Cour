<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);

    $api = new ApiClient();
    $model = [
        'enrollments' => [],
        'cart' => ['items' => [], 'count' => 0, 'subtotal' => '0.00'],
        'notifications' => [],
        'unread_notifications' => 0,
        'catalogue' => [],
    ];
    $warnings = [];

    try {
        $enrollments = $api->get('/api/v1/enrollments/mine')['data'] ?? [];
        $enrollments = is_array($enrollments) ? array_values($enrollments) : [];
        $progressByCourse = [];

        try {
            $progressRows = $api->get('/api/v1/progress/mine')['data'] ?? [];
            foreach (is_array($progressRows) ? $progressRows : [] as $progress) {
                $courseId = (int) ($progress['course_id'] ?? 0);
                if ($courseId > 0) {
                    $progressByCourse[$courseId] = $progress;
                }
            }
        } catch (DomainException $exception) {
            $warnings[] = $exception->getMessage();
        }

        foreach ($enrollments as &$enrollment) {
            if (!is_array($enrollment)) {
                $enrollment = [];
                continue;
            }
            $courseId = (int) ($enrollment['course_id'] ?? 0);
            $progress = $progressByCourse[$courseId] ?? [];
            $totalLessons = max(0, (int) ($progress['total_lessons'] ?? $enrollment['lesson_count'] ?? 0));
            $completedLessons = max(0, min($totalLessons, (int) ($progress['completed_lessons'] ?? 0)));
            $progressPercent = $totalLessons > 0
                ? min(100, (int) floor(($completedLessons / $totalLessons) * 100))
                : 0;

            $enrollment['total_lessons'] = $totalLessons;
            $enrollment['completed_lessons'] = $completedLessons;
            $enrollment['progress_percent'] = $progressPercent;
            $enrollment['is_completed'] = $totalLessons > 0 && $completedLessons === $totalLessons;
            $enrollment['last_activity'] = $progress['last_activity'] ?? null;
        }
        unset($enrollment);

        $model['enrollments'] = $enrollments;
    } catch (DomainException $exception) {
        $warnings[] = $exception->getMessage();
    }

    try {
        $cart = $api->get('/api/v1/cart')['data'] ?? [];
        if (is_array($cart)) {
            $model['cart'] = $cart + $model['cart'];
        }
    } catch (DomainException $exception) {
        $warnings[] = $exception->getMessage();
    }

    try {
        $notificationResult = $api->get('/api/v1/notifications?limit=5');
        $model['notifications'] = is_array($notificationResult['data'] ?? null)
            ? array_values($notificationResult['data'])
            : [];
        $model['unread_notifications'] = max(0, (int) ($notificationResult['meta']['unread'] ?? 0));
    } catch (DomainException $exception) {
        $warnings[] = $exception->getMessage();
    }

    try {
        $catalogue = $api->get('/api/v1/courses?limit=6')['data'] ?? [];
        $model['catalogue'] = is_array($catalogue) ? array_values($catalogue) : [];
    } catch (DomainException $exception) {
        $warnings[] = $exception->getMessage();
    }

    return StudentDashboardPage::render($model, array_values(array_unique(array_filter($warnings))));
};
