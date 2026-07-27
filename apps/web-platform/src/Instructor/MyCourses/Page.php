<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorCoursesPage
{
    public static function render(array $courses, string $message = '', bool $success = true, string $filter = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pageTitle = match ($filter) {
            'draft' => 'Draft courses',
            'pending' => 'Pending courses',
            'published' => 'Published courses',
            'rejected' => 'Rejected courses',
            default => 'My courses',
        };
        $heading = match ($filter) {
            'draft' => 'Private course drafts',
            'pending' => 'Courses under Admin review',
            'published' => 'Published courses',
            'rejected' => 'Courses requiring correction',
            default => 'All courses',
        };
        $description = match ($filter) {
            'draft' => 'Continue editing complete private courses before submitting them.',
            'pending' => 'These complete course snapshots are locked until Admin decides.',
            'published' => 'Live courses require Admin edit permission and a private revision before changes can be published.',
            'rejected' => 'Read the Admin note, correct the complete course, and submit it again.',
            default => 'Drafts are editable. Submitted courses are locked. Published courses require Admin edit permission and private revisions.',
        };
        $content = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($courses === []) {
            $content .= '<div class="portal-empty"><h2>No ' . $e(strtolower($heading)) . '</h2><p>' . $e($description) . '</p><a class="portal-button" href="/instructor/courses/create">Create a complete course</a></div>';
            return PortalPage::render('instructor', $pageTitle, $content, '<a class="portal-button secondary" href="/instructor/courses">All courses</a>');
        }

        $rows = '';
        foreach ($courses as $course) {
            $id = (int) ($course['id'] ?? 0);
            $status = (string) ($course['status'] ?? 'draft');
            $permission = (string) ($course['edit_permission_status'] ?? 'none');
            $revision = (string) ($course['revision_status'] ?? '');
            $review = trim((string) ($course['review_note'] ?? ''));
            $permissionNote = trim((string) ($course['edit_permission_note'] ?? ''));
            $actions = '<div class="course-row-actions">';

            if (in_array($status, ['draft', 'rejected'], true)) {
                $actions .= '<a class="portal-button secondary" href="/instructor/courses/create?course=' . $id . '">Edit complete course</a>'
                    . '<form method="post" action="/instructor/courses">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $id . '"><input type="hidden" name="action" value="submit_course"><button class="portal-button" type="submit">Submit for approval</button></form>';
            } elseif ($status === 'pending') {
                $actions .= '<span class="secure-pill">Locked during Admin review</span>';
            } elseif ($status === 'published') {
                $actions .= '<a class="portal-button secondary" href="/course?id=' . $id . '">View live course</a>';
                if ($revision === 'pending') {
                    $actions .= '<span class="secure-pill">Revision under Admin review</span>';
                } elseif ($revision === 'draft') {
                    $actions .= '<a class="portal-button" href="/instructor/courses/create?course=' . $id . '">Continue private revision</a>';
                } elseif ($permission === 'granted') {
                    $actions .= '<a class="portal-button" href="/instructor/courses/create?course=' . $id . '">Create private revision</a>';
                } elseif ($permission === 'requested') {
                    $actions .= '<span class="secure-pill">Edit permission requested</span>';
                } else {
                    $actions .= '<details class="course-edit-request"><summary>Request edit permission</summary><form class="portal-form" method="post" action="/instructor/courses">' . Csrf::field()
                        . '<input type="hidden" name="course_id" value="' . $id . '"><input type="hidden" name="action" value="request_edit">'
                        . '<label>Why must this published course change?<textarea name="reason" rows="4" minlength="20" maxlength="1000" placeholder="Explain the exact correction, lesson update or resource replacement." required data-error="Explain the exact published-course change in at least 20 characters."></textarea></label>'
                        . '<button class="portal-button" type="submit">Send request to Admin</button></form></details>';
                }
            }
            $actions .= '</div>';

            $notes = '';
            if ($review !== '') {
                $notes .= '<div class="course-row-note"><strong>Course review note</strong><p>' . $e($review) . '</p></div>';
            }
            if ($permission === 'denied' && $permissionNote !== '') {
                $notes .= '<div class="course-row-note denied"><strong>Edit request note</strong><p>' . $e($permissionNote) . '</p></div>';
            }

            $rows .= '<article class="instructor-course-row"><div class="instructor-course-status"><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span><small>Version ' . (int) ($course['content_version'] ?? 1) . '</small></div><div class="instructor-course-copy"><h2>' . $e($course['title'] ?? 'Untitled course') . '</h2><p>' . $e($course['short_description'] ?? '') . '</p><small>' . $e($course['category_name'] ?? 'Uncategorised') . ' · ' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . ' · NPR ' . number_format((float) ($course['price'] ?? 0), 2) . '</small>' . $notes . '</div>' . $actions . '</article>';
        }

        $content .= '<section class="instructor-course-list"><header><div><span>COURSE STUDIO</span><h2>' . $e($heading) . '</h2><p>' . $e($description) . '</p></div><strong>' . count($courses) . '</strong></header>' . $rows . '</section>';
        return PortalPage::render('instructor', $pageTitle, $content, '<a class="portal-button" href="/instructor/courses/create">Create course</a>');
    }
}
