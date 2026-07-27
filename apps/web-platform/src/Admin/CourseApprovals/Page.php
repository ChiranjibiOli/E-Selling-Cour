<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class CourseApprovalsPage
{
    public static function render(
        array $courses,
        array $permissions,
        array $revisions,
        string $message = '',
        bool $success = true,
    ): Response {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $courseQueue = self::courseQueue($courses, $e);
        $permissionQueue = self::permissionQueue($permissions, $e);
        $revisionQueue = self::revisionQueue($revisions, $e);
        $content = $alert
            . '<section class="course-workflow-console"><header><div><span>COURSE GOVERNANCE</span><h2>Approval and change control</h2><p>Review new complete courses, decide whether a published course may be edited, and approve staged changes without altering the live version early.</p></div><div class="course-workflow-counts"><span><strong>' . count($courses) . '</strong> new</span><span><strong>' . count($permissions) . '</strong> permissions</span><span><strong>' . count($revisions) . '</strong> revisions</span></div></header>'
            . '<div class="course-workflow-group"><div class="workflow-group-title"><span>01</span><div><h3>New course submissions</h3><p>Review public details and the complete serial curriculum.</p></div></div>' . $courseQueue . '</div>'
            . '<div class="course-workflow-group"><div class="workflow-group-title"><span>02</span><div><h3>Published-course edit permission</h3><p>Grant access only when the Instructor has explained a valid need.</p></div></div>' . $permissionQueue . '</div>'
            . '<div class="course-workflow-group"><div class="workflow-group-title"><span>03</span><div><h3>Staged course revisions</h3><p>The current published course stays live until one of these revisions is approved.</p></div></div>' . $revisionQueue . '</div></section>';
        return PortalPage::render('admin', 'Course approvals', $content);
    }

    private static function courseQueue(array $courses, callable $e): string
    {
        if ($courses === []) {
            return '<div class="workflow-empty">No new course is waiting for review.</div>';
        }
        $html = '<div class="workflow-record-list">';
        foreach ($courses as $course) {
            $id = (int) ($course['id'] ?? 0);
            $thumbnail = trim((string) ($course['thumbnail_url'] ?? ''));
            $image = $thumbnail !== '' ? '<img src="' . $e($thumbnail) . '" alt="Course thumbnail">' : '<span>NO IMAGE</span>';
            $curriculum = '';
            $globalLesson = 0;
            foreach ((array) ($course['sections'] ?? []) as $sectionIndex => $section) {
                $lessons = '';
                foreach ((array) ($section['lessons'] ?? []) as $lesson) {
                    $globalLesson++;
                    $resource = trim((string) ($lesson['content_name'] ?? ''));
                    $contentText = trim((string) ($lesson['content_text'] ?? ''));
                    $resourceInfo = $resource !== '' ? ' · ' . $resource : ($contentText !== '' ? ' · written content supplied' : '');
                    $lessons .= '<li><b>' . str_pad((string) $globalLesson, 2, '0', STR_PAD_LEFT) . '</b><span><strong>' . $e($lesson['title'] ?? 'Lesson') . '</strong><small>' . $e($lesson['content_type'] ?? 'text') . ' · ' . (int) ($lesson['duration_minutes'] ?? 0) . ' min' . $e($resourceInfo) . '</small></span></li>';
                }
                $curriculum .= '<article><header><span>SECTION ' . str_pad((string) ($sectionIndex + 1), 2, '0', STR_PAD_LEFT) . '</span><strong>' . $e($section['title'] ?? '') . '</strong></header><ol>' . $lessons . '</ol></article>';
            }
            $html .= '<details class="workflow-record"><summary><div class="workflow-course-thumb">' . $image . '</div><div><span class="status-badge pending">Pending</span><h3>' . $e($course['title'] ?? '') . '</h3><p>' . $e($course['instructor_name'] ?? '') . ' · ' . $e($course['category_name'] ?? '') . ' · ' . $globalLesson . ' lessons</p></div><strong>NPR ' . number_format((float) ($course['discount_price'] ?? $course['price'] ?? 0), 2) . '</strong></summary>'
                . '<div class="workflow-record-body"><section class="course-review-copy"><h4>' . $e($course['subtitle'] ?? '') . '</h4><p>' . nl2br($e($course['full_description'] ?? '')) . '</p><div class="course-review-lists"><div><strong>Learning outcomes</strong>' . self::listItems($course['learning_outcomes'] ?? [], $e) . '</div><div><strong>Requirements</strong>' . self::listItems($course['requirements'] ?? [], $e) . '</div><div><strong>Target audience</strong>' . self::listItems($course['target_audience'] ?? [], $e) . '</div></div></section>'
                . '<section class="complete-curriculum-review"><h4>Complete curriculum</h4>' . ($curriculum !== '' ? $curriculum : '<p>No curriculum supplied.</p>') . '</section>'
                . '<form class="portal-form workflow-decision-form" method="post" action="/admin/course-approvals">' . Csrf::field() . '<input type="hidden" name="action" value="review_course"><input type="hidden" name="course_id" value="' . $id . '"><label>Review note<textarea name="note" rows="4" maxlength="1000" placeholder="Required when rejecting" data-error="Explain the reason when rejecting this course."></textarea></label><div><button class="portal-button" name="decision" value="approve" type="submit">Approve and publish</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject complete course</button></div></form></div></details>';
        }
        return $html . '</div>';
    }

    private static function permissionQueue(array $permissions, callable $e): string
    {
        if ($permissions === []) {
            return '<div class="workflow-empty">No published-course edit request is waiting.</div>';
        }
        $html = '<div class="workflow-record-list">';
        foreach ($permissions as $request) {
            $id = (int) ($request['id'] ?? 0);
            $html .= '<article class="workflow-permission"><div><span class="status-badge pending">Permission requested</span><h3>' . $e($request['title'] ?? '') . '</h3><small>' . $e($request['instructor_name'] ?? '') . ' · ' . $e($request['instructor_email'] ?? '') . '</small><p>' . nl2br($e($request['edit_permission_reason'] ?? '')) . '</p></div><form class="portal-form" method="post" action="/admin/course-approvals">' . Csrf::field() . '<input type="hidden" name="action" value="edit_permission"><input type="hidden" name="course_id" value="' . $id . '"><label>Admin note<textarea name="note" rows="3" maxlength="1000" placeholder="Explain limits or denial reason"></textarea></label><div><button class="portal-button" name="decision" value="grant" type="submit">Grant private revision access</button><button class="portal-button danger" name="decision" value="deny" type="submit">Deny edit request</button></div></form></article>';
        }
        return $html . '</div>';
    }

    private static function revisionQueue(array $revisions, callable $e): string
    {
        if ($revisions === []) {
            return '<div class="workflow-empty">No published-course revision is waiting.</div>';
        }
        $html = '<div class="workflow-record-list">';
        foreach ($revisions as $revision) {
            $id = (int) ($revision['id'] ?? 0);
            $changes = '';
            foreach ((array) ($revision['change_summary'] ?? []) as $change) {
                $before = self::displayValue($change['before'] ?? null);
                $after = self::displayValue($change['after'] ?? null);
                $changes .= '<tr><th>' . $e($change['path'] ?? 'Course content') . '</th><td>' . $e($before) . '</td><td>' . $e($after) . '</td></tr>';
            }
            $html .= '<details class="workflow-record revision-record" open><summary><div><span class="status-badge pending">Revision pending</span><h3>' . $e($revision['title'] ?? '') . '</h3><p>' . $e($revision['instructor_name'] ?? '') . ' · live version ' . (int) ($revision['content_version'] ?? 1) . '</p></div><strong>' . count((array) ($revision['change_summary'] ?? [])) . ' changes</strong></summary><div class="workflow-record-body"><div class="revision-student-summary"><strong>Student-visible summary</strong><p>' . $e($revision['student_summary'] ?? '') . '</p></div><div class="revision-diff-table"><table><thead><tr><th>Location</th><th>Before</th><th>After</th></tr></thead><tbody>' . ($changes !== '' ? $changes : '<tr><td colspan="3">No readable change entries.</td></tr>') . '</tbody></table></div><form class="portal-form workflow-decision-form" method="post" action="/admin/course-approvals">' . Csrf::field() . '<input type="hidden" name="action" value="review_revision"><input type="hidden" name="revision_id" value="' . $id . '"><label>Revision review note<textarea name="note" rows="4" maxlength="1000" placeholder="Required when rejecting"></textarea></label><div><button class="portal-button" name="decision" value="approve" type="submit">Approve revision and notify Students</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject revision</button></div></form></div></details>';
        }
        return $html . '</div>';
    }

    private static function listItems(mixed $items, callable $e): string
    {
        if (!is_array($items) || $items === []) {
            return '<ul><li>Not supplied</li></ul>';
        }
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . $e($item) . '</li>';
        }
        return $html . '</ul>';
    }

    private static function displayValue(mixed $value): string
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
