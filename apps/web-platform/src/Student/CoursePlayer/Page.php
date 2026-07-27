<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentCoursePlayerPage
{
    public static function render(
        array $course,
        int $selectedLessonId = 0,
        string $message = '',
        bool $success = true,
        array $changes = [],
    ): Response {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($course === []) {
            $content = $alert . '<section class="data-card"><div class="rich-empty"><h3>No enrolled course selected</h3><p>Open a course from your lifetime course library to start learning.</p><a class="portal-button" href="/student/my-courses">Open my courses</a></div></section>';
            return PortalPage::render('student', 'Course player', $content);
        }

        $completed = [];
        foreach ((array) ($course['completed_lessons'] ?? []) as $record) {
            $completed[(int) ($record['lesson_id'] ?? 0)] = true;
        }
        $flatLessons = [];
        foreach ((array) ($course['sections'] ?? []) as $sectionIndex => $section) {
            foreach ((array) ($section['lessons'] ?? []) as $lessonIndex => $lesson) {
                $lesson['section_title'] = (string) ($section['title'] ?? 'Section');
                $lesson['section_number'] = $sectionIndex + 1;
                $lesson['lesson_number'] = count($flatLessons) + 1;
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
            $selectedIndex = 0;
        }

        $courseId = (int) ($course['id'] ?? 0);
        $contentStage = self::lessonContent($selectedLesson, $courseId, $e);
        $previous = $flatLessons[$selectedIndex - 1] ?? null;
        $next = $flatLessons[$selectedIndex + 1] ?? null;
        $navigation = '<div class="lesson-navigation">'
            . (is_array($previous) ? '<a class="portal-button secondary" href="/student/course-player?course=' . $courseId . '&lesson=' . (int) $previous['id'] . '">← Previous</a>' : '<span></span>')
            . (!isset($completed[$selectedLessonId]) && $selectedLessonId > 0
                ? '<form method="post" action="/student/course-player?course=' . $courseId . '&lesson=' . $selectedLessonId . '">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><input type="hidden" name="lesson_id" value="' . $selectedLessonId . '"><button class="portal-button" type="submit">Mark complete' . (is_array($next) ? ' and continue' : '') . ' →</button></form>'
                : (is_array($next) ? '<a class="portal-button" href="/student/course-player?course=' . $courseId . '&lesson=' . (int) $next['id'] . '">Next lesson →</a>' : '<span class="course-finished-label">Course lessons completed</span>'))
            . '</div>';

        $curriculum = '';
        $globalLesson = 0;
        foreach ((array) ($course['sections'] ?? []) as $sectionIndex => $section) {
            $links = '';
            foreach ((array) ($section['lessons'] ?? []) as $lesson) {
                $globalLesson++;
                $id = (int) ($lesson['id'] ?? 0);
                $links .= '<a class="player-lesson-link' . ($id === $selectedLessonId ? ' active' : '') . (isset($completed[$id]) ? ' completed' : '') . '" href="/student/course-player?course=' . $courseId . '&lesson=' . $id . '"><span>' . (isset($completed[$id]) ? '✓' : str_pad((string) $globalLesson, 2, '0', STR_PAD_LEFT)) . '</span><strong>' . $e($lesson['title'] ?? 'Lesson') . '</strong><small>' . (int) ($lesson['duration_minutes'] ?? 0) . 'm</small></a>';
            }
            $curriculum .= '<section class="player-section"><span>SECTION ' . str_pad((string) ($sectionIndex + 1), 2, '0', STR_PAD_LEFT) . '</span><h3>' . $e($section['title'] ?? 'Section') . '</h3>' . $links . '</section>';
        }
        if ($curriculum === '') {
            $curriculum = '<p class="muted-copy">The Instructor has not added curriculum yet.</p>';
        }
        $total = count($flatLessons);
        $done = count(array_intersect_key($completed, array_flip(array_map(static fn (array $lesson): int => (int) ($lesson['id'] ?? 0), $flatLessons))));
        $percent = $total > 0 ? min(100, (int) floor(($done / $total) * 100)) : 0;
        $changeDialog = self::changeDialog($changes, $course, $e);
        $changeButton = '<button class="portal-button secondary course-update-button" type="button" data-course-changes-open><span>i</span> Course updates' . ($changes !== [] ? ' (' . count($changes) . ')' : '') . '</button>';

        $content = $alert
            . '<section class="player-status-strip"><div><small>Course progress</small><strong>' . $percent . '%</strong><span>' . $done . ' of ' . $total . ' lessons</span></div><div class="player-status-track"><i style="width:' . $percent . '%"></i></div><div><small>Current section</small><strong>' . $e($selectedLesson['section_title'] ?? '—') . '</strong><span>Lesson ' . (int) ($selectedLesson['lesson_number'] ?? 0) . '</span></div><div><small>Instructor</small><strong>' . $e($course['instructor_name'] ?? 'CourseHub') . '</strong><span>Version ' . (int) ($course['content_version'] ?? 1) . '</span></div></section>'
            . '<div class="course-player-shell"><section class="course-player-stage"><header class="player-lesson-heading"><div><span>NOW LEARNING · LESSON ' . str_pad((string) ($selectedLesson['lesson_number'] ?? 0), 2, '0', STR_PAD_LEFT) . '</span><h2>' . $e($selectedLesson['title'] ?? $course['title'] ?? 'Course') . '</h2><p>' . $e($course['title'] ?? '') . ' · ' . $e($selectedLesson['section_title'] ?? '') . '</p></div>' . $changeButton . '</header><div class="lesson-content-stage lesson-type-' . $e($selectedLesson['content_type'] ?? 'text') . '">' . $contentStage . '</div>' . $navigation . '</section><aside class="player-curriculum"><div class="data-card-head"><div><span>CURRICULUM</span><h3>' . $e($course['title'] ?? 'Course lessons') . '</h3></div><strong>' . $percent . '%</strong></div>' . $curriculum . '</aside></div>'
            . $changeDialog;
        return PortalPage::render('student', 'Course player', $content, '<a class="portal-button secondary" href="/student/my-courses">My courses</a>');
    }

    private static function lessonContent(array $lesson, int $courseId, callable $e): string
    {
        if ($lesson === []) {
            return '<div class="lesson-resource-empty"><span>▶</span><strong>No lesson selected</strong><small>Choose a lesson from the curriculum.</small></div>';
        }
        $type = (string) ($lesson['content_type'] ?? 'text');
        $storedPath = trim((string) ($lesson['content_url'] ?? ''));
        $resourceUrl = '/student/course-player?course=' . $courseId . '&lesson=' . (int) ($lesson['id'] ?? 0) . '&resource=1';
        $resourceName = trim((string) ($lesson['content_name'] ?? ''));
        if (in_array($type, ['text', 'word'], true)) {
            $text = trim((string) ($lesson['content_text'] ?? ''));
            if ($text === '') {
                return '<div class="lesson-resource-empty"><span>!</span><strong>Written lesson unavailable</strong><small>The Instructor needs to add the reading content.</small></div>';
            }
            $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];
            $body = '';
            foreach ($paragraphs as $paragraph) {
                $body .= '<p>' . nl2br($e(trim($paragraph))) . '</p>';
            }
            return '<article class="lesson-reading"><header><span>' . ($type === 'word' ? 'DOCUMENT LESSON' : 'READING LESSON') . '</span><strong>' . $e($lesson['title'] ?? '') . '</strong></header><div>' . $body . '</div></article>';
        }
        if ($type === 'link') {
            if ($storedPath !== '' && filter_var($storedPath, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($storedPath), 'https://')) {
                return '<div class="lesson-resource-empty"><span>↗</span><strong>External learning resource</strong><small>This authorised HTTPS resource opens in another tab.</small><a class="portal-button" href="' . $e($storedPath) . '" target="_blank" rel="noopener noreferrer">Open resource</a></div>';
            }
            return '<div class="lesson-resource-empty"><span>!</span><strong>External resource unavailable</strong><small>The Instructor needs to provide a valid HTTPS link.</small></div>';
        }
        if ($storedPath === '') {
            return '<div class="lesson-resource-empty"><span>!</span><strong>' . $e(ucfirst($type)) . ' content unavailable</strong><small>The Instructor needs to attach the actual lesson file.</small></div>';
        }
        return match ($type) {
            'video' => '<div class="lesson-video-wrap"><video controls controlsList="nodownload" preload="metadata" src="' . $e($resourceUrl) . '">Your browser cannot play this lesson video.</video><small>' . $e($resourceName) . '</small></div>',
            'audio' => '<div class="lesson-audio-wrap"><span>COURSE AUDIO</span><h3>' . $e($lesson['title'] ?? 'Audio lesson') . '</h3><audio controls controlsList="nodownload" preload="metadata" src="' . $e($resourceUrl) . '"></audio><small>' . $e($resourceName) . '</small></div>',
            'pdf' => '<div class="lesson-pdf-wrap"><iframe src="' . $e($resourceUrl) . '#toolbar=0" title="' . $e($lesson['title'] ?? 'PDF lesson') . '"></iframe><footer><span>' . $e($resourceName !== '' ? $resourceName : 'PDF lesson') . '</span><a class="portal-button secondary" href="' . $e($resourceUrl) . '" target="_blank" rel="noopener">Open larger</a></footer></div>',
            'image' => '<figure class="lesson-image-wrap"><img src="' . $e($resourceUrl) . '" alt="' . $e($lesson['title'] ?? 'Lesson image') . '"><figcaption>' . $e($resourceName) . '</figcaption></figure>',
            default => '<div class="lesson-resource-empty"><span>!</span><strong>Unsupported lesson content</strong><small>This lesson type cannot be displayed.</small></div>',
        };
    }

    private static function changeDialog(array $changes, array $course, callable $e): string
    {
        $versions = '';
        foreach ($changes as $changeLog) {
            $rows = '';
            foreach ((array) ($changeLog['change_summary'] ?? []) as $change) {
                $rows .= '<tr><th>' . $e($change['path'] ?? 'Course content') . '</th><td>' . $e(self::displayChangeValue($change['before'] ?? null)) . '</td><td>' . $e(self::displayChangeValue($change['after'] ?? null)) . '</td></tr>';
            }
            $versions .= '<details class="course-change-version"><summary><div><span>VERSION ' . (int) ($changeLog['version_number'] ?? 1) . '</span><strong>' . $e($changeLog['student_summary'] ?? 'Course content updated.') . '</strong></div><time>' . $e($changeLog['reviewed_at'] ?? $changeLog['created_at'] ?? '') . '</time></summary><div class="course-change-table"><table><thead><tr><th>Location</th><th>Before</th><th>After</th></tr></thead><tbody>' . ($rows !== '' ? $rows : '<tr><td colspan="3">No detailed change lines were stored.</td></tr>') . '</tbody></table></div></details>';
        }
        if ($versions === '') {
            $versions = '<div class="course-change-empty"><strong>No approved updates yet</strong><p>You are viewing the original approved course version.</p></div>';
        }
        return '<dialog class="course-changes-dialog" data-course-changes-dialog aria-labelledby="course-changes-title"><div class="course-changes-shell"><header><div><span>APPROVED COURSE HISTORY</span><h2 id="course-changes-title">' . $e($course['title'] ?? 'Course') . ' updates</h2><p>Only Admin-approved changes appear here. The location column identifies the field, section or lesson that changed.</p></div><button type="button" data-course-changes-close aria-label="Close course update history">×</button></header><div class="course-change-list">' . $versions . '</div><footer><button class="portal-button" type="button" data-course-changes-close>Close</button></footer></div></dialog>';
    }

    private static function displayChangeValue(mixed $value): string
    {
        if ($value === null || $value === '') return 'Empty';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return mb_substr(is_string($encoded) ? $encoded : 'Structured content', 0, 500);
        }
        return mb_substr((string) $value, 0, 500);
    }
}
