<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentCoursePlayerPage
{
    public static function render(array $course, int $selectedLessonId = 0, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($course === []) {
            $content = $alert . '<section class="data-card"><div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No enrolled course selected</h3><p>Open a course from your lifetime course library to start learning.</p><a class="portal-button" href="/student/my-courses">Open my courses</a></div></section>';
            return PortalPage::render('student', 'Course player', $content);
        }

        $completed = [];
        foreach ((array) ($course['completed_lessons'] ?? []) as $record) {
            $completed[(int) ($record['lesson_id'] ?? 0)] = true;
        }
        $flatLessons = [];
        foreach ((array) ($course['sections'] ?? []) as $section) {
            foreach ((array) ($section['lessons'] ?? []) as $lesson) {
                $lesson['section_title'] = (string) ($section['title'] ?? 'Section');
                $flatLessons[] = $lesson;
            }
        }
        if ($selectedLessonId < 1 && $flatLessons !== []) {
            foreach ($flatLessons as $lesson) {
                if (!isset($completed[(int) ($lesson['id'] ?? 0)])) {
                    $selectedLessonId = (int) ($lesson['id'] ?? 0);
                    break;
                }
            }
            if ($selectedLessonId < 1) {
                $selectedLessonId = (int) ($flatLessons[0]['id'] ?? 0);
            }
        }
        $selectedIndex = 0;
        $selectedLesson = [];
        foreach ($flatLessons as $index => $lesson) {
            if ((int) ($lesson['id'] ?? 0) === $selectedLessonId) {
                $selectedLesson = $lesson;
                $selectedIndex = $index;
                break;
            }
        }
        if ($selectedLesson === [] && $flatLessons !== []) {
            $selectedLesson = $flatLessons[0];
            $selectedLessonId = (int) ($selectedLesson['id'] ?? 0);
        }

        $courseId = (int) ($course['id'] ?? 0);
        $contentStage = self::lessonContent($selectedLesson, $e);
        $previous = $flatLessons[$selectedIndex - 1] ?? null;
        $next = $flatLessons[$selectedIndex + 1] ?? null;
        $navigation = '<div class="actions">'
            . (is_array($previous) ? '<a class="portal-button secondary" href="/student/course-player?course=' . $courseId . '&lesson=' . (int) $previous['id'] . '">← Previous lesson</a>' : '')
            . (!isset($completed[$selectedLessonId]) && $selectedLessonId > 0 ? '<form method="post" action="/student/course-player?course=' . $courseId . '&lesson=' . $selectedLessonId . '">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><input type="hidden" name="lesson_id" value="' . $selectedLessonId . '"><button class="portal-button" type="submit">Mark complete' . (is_array($next) ? ' & continue' : '') . ' →</button></form>' : '')
            . (isset($completed[$selectedLessonId]) && is_array($next) ? '<a class="portal-button" href="/student/course-player?course=' . $courseId . '&lesson=' . (int) $next['id'] . '">Next lesson →</a>' : '') . '</div>';

        $curriculum = '';
        foreach ((array) ($course['sections'] ?? []) as $section) {
            $links = '';
            foreach ((array) ($section['lessons'] ?? []) as $lesson) {
                $id = (int) ($lesson['id'] ?? 0);
                $links .= '<a class="player-lesson-link' . ($id === $selectedLessonId ? ' active' : '') . (isset($completed[$id]) ? ' completed' : '') . '" href="/student/course-player?course=' . $courseId . '&lesson=' . $id . '"><span>' . (isset($completed[$id]) ? '✓' : '○') . '</span><strong>' . $e($lesson['title'] ?? 'Lesson') . '</strong><small>' . (int) ($lesson['duration_minutes'] ?? 0) . 'm</small></a>';
            }
            $curriculum .= '<section class="player-section"><span>SECTION ' . (int) ($section['sort_order'] ?? 1) . '</span><h3>' . $e($section['title'] ?? 'Section') . '</h3>' . $links . '</section>';
        }
        if ($curriculum === '') {
            $curriculum = '<p class="muted-copy">The instructor has not added curriculum yet.</p>';
        }
        $total = count($flatLessons);
        $done = count($completed);
        $percent = $total > 0 ? (int) floor(($done / $total) * 100) : 0;

        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Course progress</span><i></i></div><strong>' . $percent . '%</strong><small>' . $done . ' of ' . $total . ' lessons</small></article><article class="metric-card violet"><div class="metric-top"><span>Current section</span><i></i></div><strong>' . $e($selectedLesson['section_title'] ?? '—') . '</strong><small>Structured curriculum</small></article><article class="metric-card teal"><div class="metric-top"><span>Access</span><i></i></div><strong>Lifetime</strong><small>Verified active enrollment</small></article><article class="metric-card orange"><div class="metric-top"><span>Instructor</span><i></i></div><strong>' . $e($course['instructor_name'] ?? 'CourseHub') . '</strong><small>Course owner</small></article></section>'
            . '<div class="course-player-shell"><section class="course-player-stage"><div class="lesson-content-stage">' . $contentStage . '</div><div class="lesson-meta-panel"><span>NOW LEARNING</span><h2>' . $e($selectedLesson['title'] ?? $course['title'] ?? 'Course') . '</h2><p>' . $e($course['title'] ?? '') . ' · ' . $e($selectedLesson['section_title'] ?? '') . '</p>' . $navigation . '</div></section><aside class="player-curriculum"><div class="data-card-head"><div><span>CURRICULUM</span><h3>' . $e($course['title'] ?? 'Course lessons') . '</h3></div><strong>' . $percent . '%</strong></div>' . $curriculum . '</aside></div>';
        return PortalPage::render('student', 'Course player', $content, '<a class="portal-button secondary" href="/student/my-courses">My courses</a>');
    }

    private static function lessonContent(array $lesson, callable $e): string
    {
        if ($lesson === []) {
            return '<div class="video-empty"><span>▶</span><strong>No lesson selected</strong><small>Choose a lesson from the curriculum.</small></div>';
        }
        $type = (string) ($lesson['content_type'] ?? 'text');
        $url = trim((string) ($lesson['content_url'] ?? ''));
        if ($type === 'text') {
            return '<article class="lesson-text-content">' . nl2br($e($lesson['content_text'] ?? '')) . '</article>';
        }
        if ($type === 'video' && $url !== '' && preg_match('#^(?:/|https://)#', $url) === 1) {
            return '<video controls controlsList="nodownload" preload="metadata"><source src="' . $e($url) . '">Your browser cannot play this lesson video.</video>';
        }
        if ($url !== '' && preg_match('#^(?:/|https://)#', $url) === 1) {
            return '<div class="video-empty"><span>↗</span><strong>' . $e(ucfirst($type)) . ' lesson resource</strong><small>This resource opens from the instructor-provided stored path.</small><a class="portal-button" href="' . $e($url) . '" target="_blank" rel="noopener noreferrer">Open resource</a></div>';
        }
        return '<div class="video-empty"><span>!</span><strong>Lesson content unavailable</strong><small>The instructor needs to attach a valid stored resource.</small></div>';
    }
}
