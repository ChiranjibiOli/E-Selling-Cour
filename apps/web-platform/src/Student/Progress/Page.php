<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentProgressPage
{
    public static function render(array $courses, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $totalLessons = $completedLessons = $completedCourses = 0;
        $sumProgress = 0;
        $rows = '';
        foreach ($courses as $course) {
            $total = (int) ($course['total_lessons'] ?? 0);
            $completed = (int) ($course['completed_lessons'] ?? 0);
            $percent = (int) ($course['progress_percent'] ?? 0);
            $totalLessons += $total;
            $completedLessons += $completed;
            $sumProgress += $percent;
            if ($total > 0 && $completed >= $total) { $completedCourses++; }
            $rows .= '<article class="progress-course-row"><div><span class="portal-eyebrow">' . $e($course['instructor_name'] ?? 'CourseHub instructor') . '</span><h3>' . $e($course['title'] ?? 'Course') . '</h3><p>' . $completed . ' of ' . $total . ' lessons completed' . (($course['last_activity'] ?? '') !== '' ? ' · Last activity ' . $e($course['last_activity']) : '') . '</p><div class="learning-progress"><span><i style="width:' . max(0, min(100, $percent)) . '%"></i></span><b>' . $percent . '%</b></div></div><a class="portal-button" href="/student/course-player?course=' . (int) ($course['course_id'] ?? 0) . '">' . ($percent > 0 ? 'Continue' : 'Start') . ' course →</a></article>';
        }
        if ($rows === '') {
            $rows = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No learning progress yet</h3><p>Purchase a course and complete the first lesson to begin tracking momentum.</p><a class="portal-button" href="/courses">Explore courses</a></div>';
        }
        $average = count($courses) > 0 ? (int) floor($sumProgress / count($courses)) : 0;
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Overall progress</span><i></i></div><strong>' . $average . '%</strong><small>Average across active courses</small></article><article class="metric-card violet"><div class="metric-top"><span>Lessons completed</span><i></i></div><strong>' . $completedLessons . '</strong><small>Out of ' . $totalLessons . ' available lessons</small></article><article class="metric-card teal"><div class="metric-top"><span>Active courses</span><i></i></div><strong>' . count($courses) . '</strong><small>Verified lifetime enrollments</small></article><article class="metric-card orange"><div class="metric-top"><span>Completed courses</span><i></i></div><strong>' . $completedCourses . '</strong><small>Every lesson completed</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>LEARNING MOMENTUM</span><h3>Progress across your courses</h3></div><a class="portal-button secondary" href="/student/my-courses">My courses</a></div><div class="progress-course-list">' . $rows . '</div></section>';
        return PortalPage::render('student', 'Progress', $content);
    }
}
