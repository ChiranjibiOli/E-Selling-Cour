<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorCurriculumPage
{
    public static function render(array $courses, int $courseId, array $course, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $options = '';
        foreach ($courses as $candidate) {
            $id = (int) ($candidate['id'] ?? 0);
            $options .= '<option value="' . $id . '"' . ($id === $courseId ? ' selected' : '') . '>' . $e($candidate['title'] ?? 'Course') . ' · ' . $e($candidate['status'] ?? 'draft') . '</option>';
        }
        $selector = '<form method="get" action="/instructor/curriculum" class="curriculum-selector"><label>Course<select name="course" onchange="this.form.submit()"><option value="">Choose a course</option>' . $options . '</select></label><noscript><button class="portal-button secondary" type="submit">Open course</button></noscript></form>';

        if ($courseId < 1 || $course === []) {
            $content = $alert . '<section class="data-card"><div class="data-card-head"><div><span>CURRICULUM BUILDER</span><h3>Choose an editable course</h3></div></div>' . $selector
                . '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No course selected</h3><p>Create a draft course or select an existing editable course to build sections and lessons.</p><a class="portal-button" href="/instructor/courses/create">Create course</a></div></section>';
            return PortalPage::render('instructor', 'Curriculum builder', $content);
        }

        $sections = is_array($course['sections'] ?? null) ? $course['sections'] : [];
        $lessonCount = 0;
        $previewCount = 0;
        $duration = 0;
        $sectionHtml = '';
        foreach ($sections as $section) {
            $sectionId = (int) ($section['id'] ?? 0);
            $lessons = is_array($section['lessons'] ?? null) ? $section['lessons'] : [];
            $lessonsHtml = '';
            foreach ($lessons as $lesson) {
                $lessonCount++;
                $previewCount += (int) ($lesson['is_preview'] ?? 0);
                $duration += (int) ($lesson['duration_minutes'] ?? 0);
                $lessonId = (int) ($lesson['id'] ?? 0);
                $lessonsHtml .= '<details class="lesson-editor"><summary><span>' . str_pad((string) $lessonCount, 2, '0', STR_PAD_LEFT) . '</span><strong>' . $e($lesson['title'] ?? 'Lesson') . '</strong><small>' . $e($lesson['content_type'] ?? 'text') . ' · ' . (int) ($lesson['duration_minutes'] ?? 0) . ' min' . ((int) ($lesson['is_preview'] ?? 0) === 1 ? ' · Preview' : '') . '</small></summary>'
                    . '<form class="panel-form" method="post" action="/instructor/curriculum?course=' . $courseId . '">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><input type="hidden" name="lesson_id" value="' . $lessonId . '">'
                    . self::lessonFields($lesson, $e) . '<div class="actions"><button class="portal-button" name="action" value="update_lesson" type="submit">Save lesson</button><button class="portal-button danger" name="action" value="delete_lesson" type="submit">Delete lesson</button></div></form></details>';
            }
            if ($lessonsHtml === '') {
                $lessonsHtml = '<p class="muted-copy">No lessons in this section yet.</p>';
            }
            $sectionHtml .= '<article class="curriculum-section-editor"><header><div><span>SECTION ' . (int) ($section['sort_order'] ?? 1) . '</span><h3>' . $e($section['title'] ?? 'Section') . '</h3></div>'
                . '<form method="post" action="/instructor/curriculum?course=' . $courseId . '">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><input type="hidden" name="section_id" value="' . $sectionId . '"><button class="text-button danger-text" name="action" value="delete_section" type="submit">Delete section</button></form></header>'
                . '<form class="section-inline-form" method="post" action="/instructor/curriculum?course=' . $courseId . '">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><input type="hidden" name="section_id" value="' . $sectionId . '"><input name="title" maxlength="180" value="' . $e($section['title'] ?? '') . '" required><input type="number" name="sort_order" min="1" value="' . (int) ($section['sort_order'] ?? 1) . '"><button class="portal-button secondary" name="action" value="update_section" type="submit">Update section</button></form>'
                . '<div class="lesson-editor-list">' . $lessonsHtml . '</div><details class="add-lesson-card"><summary>+ Add lesson to this section</summary><form class="panel-form" method="post" action="/instructor/curriculum?course=' . $courseId . '">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><input type="hidden" name="section_id" value="' . $sectionId . '">' . self::lessonFields([], $e) . '<button class="portal-button" name="action" value="add_lesson" type="submit">Create lesson</button></form></details></article>';
        }
        if ($sectionHtml === '') {
            $sectionHtml = '<div class="rich-empty"><h3>No sections created</h3><p>Add the first section, then place lessons inside it.</p></div>';
        }

        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Sections</span><i></i></div><strong>' . count($sections) . '</strong><small>Ordered learning stages</small></article><article class="metric-card violet"><div class="metric-top"><span>Lessons</span><i></i></div><strong>' . $lessonCount . '</strong><small>Text, video, files or links</small></article><article class="metric-card teal"><div class="metric-top"><span>Duration</span><i></i></div><strong>' . $duration . 'm</strong><small>Total lesson time</small></article><article class="metric-card orange"><div class="metric-top"><span>Free previews</span><i></i></div><strong>' . $previewCount . '</strong><small>Visible before purchase</small></article></section>'
            . '<div class="curriculum-builder-live"><section class="builder-outline"><div class="data-card-head"><div><span>COURSE OUTLINE</span><h3>' . $e($course['title'] ?? 'Course') . '</h3></div>' . $selector . '</div><div class="curriculum-editor-stack">' . $sectionHtml . '</div></section>'
            . '<aside class="builder-tips"><span>ADD SECTION</span><h3>Create the next learning stage.</h3><form class="panel-form" method="post" action="/instructor/curriculum?course=' . $courseId . '">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '"><label>Section title<input name="title" maxlength="180" placeholder="Getting started" required></label><label>Order<input type="number" name="sort_order" min="1" value="' . (count($sections) + 1) . '"></label><button class="portal-button full" name="action" value="add_section" type="submit">+ Add section</button></form><hr><span>QUALITY GUIDE</span><ul><li>Keep one learning goal per section.</li><li>Use short outcome-focused lesson titles.</li><li>Mark only useful introductions as previews.</li><li>Pending courses remain locked during admin review.</li></ul></aside></div>';
        return PortalPage::render('instructor', 'Curriculum builder', $content, '<a class="portal-button secondary" href="/instructor/courses">Back to courses</a>');
    }

    private static function lessonFields(array $lesson, callable $e): string
    {
        $type = (string) ($lesson['content_type'] ?? 'text');
        $options = '';
        foreach (['text' => 'Text', 'video' => 'Video', 'link' => 'External link', 'pdf' => 'PDF', 'word' => 'Word document'] as $value => $label) {
            $options .= '<option value="' . $value . '"' . ($type === $value ? ' selected' : '') . '>' . $label . '</option>';
        }
        return '<div class="field-grid"><label>Lesson title<input name="title" maxlength="180" value="' . $e($lesson['title'] ?? '') . '" required></label><label>Content type<select name="content_type">' . $options . '</select></label></div>'
            . '<div class="field-grid"><label>Duration minutes<input type="number" name="duration_minutes" min="0" max="10000" value="' . (int) ($lesson['duration_minutes'] ?? 0) . '"></label><label>Order<input type="number" name="sort_order" min="1" value="' . (int) ($lesson['sort_order'] ?? 1) . '"></label></div>'
            . '<label>Media or resource URL<input name="content_url" maxlength="500" value="' . $e($lesson['content_url'] ?? '') . '" placeholder="Secure stored path or validated URL"></label>'
            . '<label>Text lesson content<textarea name="content_text" rows="6" maxlength="200000">' . $e($lesson['content_text'] ?? '') . '</textarea></label>'
            . '<label class="check-line"><input type="checkbox" name="is_preview" value="1"' . ((int) ($lesson['is_preview'] ?? 0) === 1 ? ' checked' : '') . '> Allow this lesson as a free public preview</label>';
    }
}
