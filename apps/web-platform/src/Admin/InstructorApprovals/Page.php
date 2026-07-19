<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorApprovalsPage
{
    public static function render(array $applications, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($applications === []) {
            $content .= '<div class="portal-empty"><h2>No pending instructor applications.</h2><p>New applicants appear here until an administrator approves or rejects them.</p></div>';
        } else {
            $content .= '<section class="portal-grid">';
            foreach ($applications as $application) {
                $id = (int) ($application['id'] ?? 0);
                $content .= '<article class="portal-card"><span class="status-badge pending">Awaiting review</span><h2>' . $e($application['full_name'] ?? '') . '</h2>'
                    . '<p>' . $e($application['email'] ?? '') . '<br>' . $e($application['phone'] ?? '') . '</p><p>' . nl2br($e($application['bio'] ?? '')) . '</p>'
                    . '<form class="portal-form" method="post" action="/admin/instructor-approvals">' . Csrf::field() . '<input type="hidden" name="instructor_id" value="' . $id . '">'
                    . '<label>Decision note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label>'
                    . '<div class="actions"><button class="portal-button" name="decision" value="approve" type="submit">Approve instructor</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject application</button></div></form></article>';
            }
            $content .= '</section>';
        }
        return PortalPage::render('admin', 'Instructor approvals', $content);
    }
}
