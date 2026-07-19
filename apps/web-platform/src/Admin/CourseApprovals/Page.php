<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class CourseApprovalsPage
{
    public static function render(array $courses, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($courses === []) {
            $content .= '<div class="portal-empty"><h2>Approval queue is clear.</h2><p>Only courses explicitly submitted by instructors appear here. Private drafts remain private.</p></div>';
        } else {
            $content .= '<section class="portal-grid">';
            foreach ($courses as $course) {
                $id = (int) ($course['id'] ?? 0);
                $content .= '<article class="portal-card"><span class="status-badge pending">Pending</span><h2>' . $e($course['title'] ?? '') . '</h2>'
                    . '<p>' . $e($course['short_description'] ?? '') . '</p><p><strong>Instructor:</strong> ' . $e($course['instructor_name'] ?? '') . '<br><strong>Category:</strong> ' . $e($course['category_name'] ?? '') . '<br><strong>Price:</strong> NPR ' . number_format((float) ($course['price'] ?? 0), 2) . '</p>'
                    . '<details><summary>Read full description</summary><p>' . nl2br($e($course['full_description'] ?? '')) . '</p></details>'
                    . '<form class="portal-form" method="post" action="/admin/course-approvals">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $id . '">'
                    . '<label>Review note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label>'
                    . '<div class="actions"><button class="portal-button" name="decision" value="approve" type="submit">Approve and publish</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject course</button></div></form></article>';
            }
            $content .= '</section>';
        }
        return PortalPage::render('admin', 'Course approvals', $content);
    }
}
