<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentMyCoursesPage
{
    public static function render(array $courses, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cards = '';
        $active = 0;
        foreach ($courses as $course) {
            $status = (string) ($course['status'] ?? 'active');
            if ($status === 'active') { $active++; }
            $image = trim((string) ($course['thumbnail_url'] ?? ''));
            $media = $image !== '' ? '<img src="' . $e($image) . '" alt="" loading="lazy">' : '<span>CH</span>';
            $courseId = (int) ($course['course_id'] ?? 0);
            $cards .= '<article class="learning-course-card"><div class="learning-course-media">' . $media . '<span class="status-badge ' . $e($status) . '">' . $e($status) . '</span></div>'
                . '<div class="learning-course-copy"><span>' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . ' · ' . $e($course['language'] ?? 'English') . '</span><h3>' . $e($course['title'] ?? 'Course') . '</h3>'
                . '<p>' . $e($course['short_description'] ?? '') . '</p><small>By ' . $e($course['instructor_name'] ?? 'Instructor') . ' · ' . (int) ($course['lesson_count'] ?? 0) . ' lessons · Lifetime access</small>'
                . '<div class="learning-progress"><span><i style="width:0%"></i></span><b>0%</b></div><footer><a class="portal-button" href="/student/course-player?course=' . $courseId . '">Start learning</a>'
                . '<a class="portal-button secondary" href="/course?id=' . $courseId . '">Course details</a></footer></div></article>';
        }
        if ($cards === '') {
            $cards = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>Your course library is waiting</h3><p>Approved payments create lifetime enrollments here automatically.</p><a class="portal-button" href="/courses">Explore published courses</a></div>';
        }
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Purchased courses</span><i></i></div><strong>' . count($courses) . '</strong><small>Lifetime enrollment records</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Active access</span><i></i></div><strong>' . $active . '</strong><small>Ready for learning</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Completed</span><i></i></div><strong>0</strong><small>Progress service comes next</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Ownership</span><i></i></div><strong>Verified</strong><small>Order and payment linked</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>MY COURSES</span><h3>Your lifetime learning library</h3></div><a class="portal-button secondary" href="/courses">Find another course</a></div><div class="learning-course-grid">' . $cards . '</div></section>';
        return PortalPage::render('student', 'My courses', $content);
    }
}
