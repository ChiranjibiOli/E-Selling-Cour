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
        $renderList = static function (mixed $items) use ($e): string {
            if (!is_array($items) || $items === []) {
                return '<li>Not supplied</li>';
            }
            $html = '';
            foreach ($items as $item) {
                $html .= '<li>' . $e($item) . '</li>';
            }
            return $html;
        };
        $content = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($courses === []) {
            $content .= '<div class="portal-empty"><h2>Approval queue is clear.</h2><p>Only courses explicitly submitted by instructors appear here. Private drafts remain private.</p></div>';
        } else {
            $content .= '<section class="portal-grid">';
            foreach ($courses as $course) {
                $id = (int) ($course['id'] ?? 0);
                $discount = $course['discount_price'] ?? null;
                $price = 'NPR ' . number_format((float) ($course['price'] ?? 0), 2);
                if ($discount !== null && (float) $discount < (float) ($course['price'] ?? 0)) {
                    $price .= ' → NPR ' . number_format((float) $discount, 2);
                }
                $intro = trim((string) ($course['intro_video_url'] ?? ''));
                $introLink = $intro !== '' ? '<a class="portal-link" href="' . $e($intro) . '" target="_blank" rel="noopener noreferrer">Open introduction video ↗</a>' : '<span class="muted-copy">No introduction video supplied</span>';
                $content .= '<article class="portal-card course-review-card"><span class="status-badge pending">Pending</span><h2>' . $e($course['title'] ?? '') . '</h2>'
                    . '<h3>' . $e($course['subtitle'] ?? '') . '</h3><p>' . $e($course['short_description'] ?? '') . '</p>'
                    . '<div class="payment-review-facts"><span><small>Instructor</small><strong>' . $e($course['instructor_name'] ?? '') . '</strong></span><span><small>Category</small><strong>' . $e($course['category_name'] ?? '') . '</strong></span>'
                    . '<span><small>Price</small><strong>' . $e($price) . '</strong></span><span><small>Level / language</small><strong>' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . ' · ' . $e($course['language'] ?? 'English') . '</strong></span></div>'
                    . '<details open><summary>Review complete course promise</summary><h3>Full description</h3><p>' . nl2br($e($course['full_description'] ?? '')) . '</p>'
                    . '<h3>Learning outcomes</h3><ul>' . $renderList($course['learning_outcomes'] ?? []) . '</ul><h3>Requirements</h3><ul>' . $renderList($course['requirements'] ?? []) . '</ul>'
                    . '<h3>Target audience</h3><ul>' . $renderList($course['target_audience'] ?? []) . '</ul><h3>Tags</h3><p>' . $e($course['tags'] ?? 'None') . '</p>' . $introLink . '</details>'
                    . '<form class="portal-form" method="post" action="/admin/course-approvals">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $id . '">'
                    . '<label>Review note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label>'
                    . '<div class="actions"><button class="portal-button" name="decision" value="approve" type="submit">Approve and publish</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject course</button></div></form></article>';
            }
            $content .= '</section>';
        }
        return PortalPage::render('admin', 'Course approvals', $content);
    }
}
