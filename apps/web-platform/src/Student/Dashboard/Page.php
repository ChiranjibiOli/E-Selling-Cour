<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Session\AuthSession;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentDashboardPage
{
    public static function render(array $model, array $warnings = []): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $enrollments = is_array($model['enrollments'] ?? null) ? array_values($model['enrollments']) : [];
        $cart = is_array($model['cart'] ?? null) ? $model['cart'] : [];
        $notifications = is_array($model['notifications'] ?? null) ? array_values($model['notifications']) : [];
        $catalogue = is_array($model['catalogue'] ?? null) ? array_values($model['catalogue']) : [];
        $unread = max(0, (int) ($model['unread_notifications'] ?? 0));
        $cartItems = is_array($cart['items'] ?? null) ? $cart['items'] : [];
        $cartCount = max(count($cartItems), (int) ($cart['count'] ?? 0));
        $cartSubtotal = (float) ($cart['subtotal'] ?? 0);

        $user = AuthSession::user();
        $name = trim((string) ($user['name'] ?? 'Student'));
        $firstName = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY)[0] ?? 'Student';

        $activeEnrollments = 0;
        $completedCourses = 0;
        $progressTotal = 0;
        $progressCount = 0;
        $continueCourses = [];

        foreach ($enrollments as $enrollment) {
            if (!is_array($enrollment)) {
                continue;
            }
            $status = (string) ($enrollment['status'] ?? 'active');
            $progress = max(0, min(100, (int) ($enrollment['progress_percent'] ?? 0)));
            if ($status === 'active') {
                $activeEnrollments++;
            }
            if (!empty($enrollment['is_completed'])) {
                $completedCourses++;
            }
            $progressTotal += $progress;
            $progressCount++;
            if ($status === 'active' && empty($enrollment['is_completed'])) {
                $continueCourses[] = $enrollment;
            }
        }
        usort($continueCourses, static fn (array $left, array $right): int => (int) ($right['progress_percent'] ?? 0) <=> (int) ($left['progress_percent'] ?? 0));
        $averageProgress = $progressCount > 0 ? (int) round($progressTotal / $progressCount) : 0;

        $primaryCourse = $continueCourses[0] ?? ($enrollments[0] ?? null);
        $primaryCourseId = is_array($primaryCourse) ? (int) ($primaryCourse['course_id'] ?? 0) : 0;
        $primaryTitle = is_array($primaryCourse) ? trim((string) ($primaryCourse['title'] ?? '')) : '';
        $primaryProgress = is_array($primaryCourse) ? max(0, min(100, (int) ($primaryCourse['progress_percent'] ?? 0))) : 0;
        $heroAction = $primaryCourseId > 0
            ? '<a class="portal-button" href="/student/course-player?course=' . $primaryCourseId . '">' . ($primaryProgress > 0 ? 'Continue learning' : 'Start first lesson') . '</a>'
            : '<a class="portal-button" href="/student/courses">Find your first course</a>';
        $heroCopy = $primaryCourseId > 0
            ? 'Your next useful step is ready in ' . ($primaryTitle !== '' ? $primaryTitle : 'your course library') . '.'
            : 'Explore approved courses, add one to your cart and build a learning path that belongs to you.';

        $warningHtml = '';
        if ($warnings !== []) {
            $warningHtml = '<div class="form-alert error"><strong>Some overview data could not be refreshed.</strong><span>Your courses and account remain safe. Open the related panel to retry.</span></div>';
        }

        $courseCards = '';
        foreach (array_slice($continueCourses, 0, 3) as $course) {
            $courseId = (int) ($course['course_id'] ?? 0);
            $progress = max(0, min(100, (int) ($course['progress_percent'] ?? 0)));
            $total = max(0, (int) ($course['total_lessons'] ?? $course['lesson_count'] ?? 0));
            $completed = max(0, min($total, (int) ($course['completed_lessons'] ?? 0)));
            $thumbnail = trim((string) ($course['thumbnail_url'] ?? ''));
            $media = $thumbnail !== ''
                ? '<img src="' . $e($thumbnail) . '" alt="" loading="lazy">'
                : '<span>CH</span>';
            $courseCards .= '<article class="student-overview-course"><div class="student-overview-course-media">' . $media . '</div><div class="student-overview-course-copy">'
                . '<span>' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . ' · ' . $e($course['language'] ?? 'English') . '</span>'
                . '<h3>' . $e($course['title'] ?? 'Course') . '</h3><small>' . $completed . ' of ' . $total . ' lessons complete</small>'
                . '<div class="student-overview-progress"><span><i style="width:' . $progress . '%"></i></span><b>' . $progress . '%</b></div>'
                . '<a href="/student/course-player?course=' . $courseId . '">' . ($progress > 0 ? 'Continue course' : 'Start course') . ' →</a></div></article>';
        }
        if ($courseCards === '') {
            $courseCards = '<div class="student-overview-empty"><strong>No course in progress</strong><p>Your active courses will appear here with lesson-by-lesson progress.</p><a href="/student/courses">Explore courses →</a></div>';
        }

        $notificationList = '';
        foreach (array_slice($notifications, 0, 4) as $notification) {
            $isRead = (int) ($notification['is_read'] ?? 0) === 1;
            $notificationList .= '<article class="student-overview-update' . ($isRead ? '' : ' unread') . '"><i></i><div><strong>' . $e($notification['title'] ?? 'CourseHub update') . '</strong>'
                . '<p>' . $e($notification['message'] ?? '') . '</p><small>' . $e($notification['created_at'] ?? '') . '</small></div></article>';
        }
        if ($notificationList === '') {
            $notificationList = '<div class="student-overview-mini-empty">Payment, enrolment and course updates will appear here.</div>';
        }

        $discoverCards = '';
        foreach (array_slice($catalogue, 0, 3) as $course) {
            $courseId = (int) ($course['id'] ?? 0);
            $thumbnail = trim((string) ($course['thumbnail_url'] ?? ''));
            $media = $thumbnail !== '' ? '<img src="' . $e($thumbnail) . '" alt="" loading="lazy">' : '<span>CH</span>';
            $price = (float) ($course['effective_price'] ?? $course['discount_price'] ?? $course['price'] ?? 0);
            $discoverCards .= '<article class="student-discover-card"><div>' . $media . '</div><span>' . $e($course['category_name'] ?? 'Course') . '</span>'
                . '<h3>' . $e($course['title'] ?? 'Course') . '</h3><footer><strong>' . ($price > 0 ? 'NPR ' . number_format($price, 2) : 'Free') . '</strong>'
                . '<a href="/student/courses?course=' . $courseId . '">View course →</a></footer></article>';
        }
        if ($discoverCards === '') {
            $discoverCards = '<div class="student-overview-mini-empty">Published course recommendations are temporarily unavailable.</div>';
        }

        $content = $warningHtml
            . '<section class="student-overview-hero"><div><span>LEARNING OVERVIEW</span><h1>Welcome back, ' . $e($firstName) . '.</h1><p>' . $e($heroCopy) . '</p><div>' . $heroAction . '<a class="portal-button secondary" href="/student/my-courses">Open My courses</a></div></div>'
            . '<aside><span>YOUR MOMENTUM</span><strong>' . $averageProgress . '%</strong><p>Average progress across your enrolled courses.</p><div><i style="width:' . $averageProgress . '%"></i></div></aside></section>'
            . '<section class="student-overview-metrics">'
            . '<article><span>Active courses</span><strong>' . $activeEnrollments . '</strong><small>' . $completedCourses . ' completed</small></article>'
            . '<article><span>Average progress</span><strong>' . $averageProgress . '%</strong><small>Across your library</small></article>'
            . '<article><span>Cart</span><strong>' . $cartCount . '</strong><small>NPR ' . number_format($cartSubtotal, 2) . '</small></article>'
            . '<article><span>Unread updates</span><strong>' . $unread . '</strong><small>Needs your attention</small></article></section>'
            . '<div class="student-overview-layout"><section class="student-overview-main"><header><div><span>CONTINUE LEARNING</span><h2>Your active learning path</h2></div><a href="/student/my-courses">View library →</a></header><div class="student-overview-course-list">' . $courseCards . '</div></section>'
            . '<aside class="student-overview-side"><section class="student-overview-cart"><header><span>MY CART</span><strong>' . $cartCount . ' course' . ($cartCount === 1 ? '' : 's') . '</strong></header><p>Your whole purchase process now begins from one cart workflow.</p><div><span>Current subtotal</span><strong>NPR ' . number_format($cartSubtotal, 2) . '</strong></div>'
            . ($cartCount > 0 ? '<a class="portal-button full" href="/student/cart">Review cart and continue</a>' : '<a class="portal-button secondary full" href="/student/courses">Choose a course</a>') . '</section>'
            . '<section class="student-overview-updates"><header><div><span>RECENT UPDATES</span><h2>What changed</h2></div><a href="/student/notifications">All updates</a></header><div>' . $notificationList . '</div></section></aside></div>'
            . '<section class="student-overview-discover"><header><div><span>DISCOVER NEXT</span><h2>Published courses worth exploring</h2></div><a href="/student/courses">Browse catalogue →</a></header><div>' . $discoverCards . '</div></section>';

        return PortalPage::render('student', 'Overview', $content);
    }
}
