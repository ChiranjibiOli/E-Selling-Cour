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
            $content .= '<div class="portal-empty"><h2>No pending Instructor applications.</h2><p>New applicants appear here until an administrator approves or rejects them.</p></div>';
        } else {
            $content .= '<section class="portal-grid">';
            foreach ($applications as $application) {
                $id = (int) ($application['id'] ?? 0);
                $social = trim((string) ($application['social_profile_url'] ?? ''));
                $socialLink = $social !== '' ? '<a class="portal-link" href="' . $e($social) . '" target="_blank" rel="noopener noreferrer">Open professional profile ↗</a>' : '<span class="muted-copy">No profile link supplied</span>';
                $profilePath = trim((string) ($application['profile_image'] ?? ''));
                $identityPath = trim((string) ($application['identity_document'] ?? ''));
                $profilePreview = $profilePath !== ''
                    ? '<a class="instructor-application-photo" href="/admin/instructor-approvals?media=profile&amp;id=' . $id . '" target="_blank"><img src="/admin/instructor-approvals?media=profile&amp;id=' . $id . '" alt="Passport-size profile photo for ' . $e($application['full_name'] ?? 'Instructor') . '" loading="lazy"><span>Open full photo</span></a>'
                    : '<div class="form-alert error">Passport-size profile photo missing.</div>';
                $identityPreview = $identityPath !== ''
                    ? '<a class="portal-button secondary" href="/admin/instructor-approvals?media=identity&amp;id=' . $id . '" target="_blank" rel="noopener">Open private identity document</a>'
                    : '<span class="form-alert error">Identity document missing.</span>';

                $content .= '<article class="portal-card instructor-review-card"><span class="status-badge pending">Awaiting review</span><h2>' . $e($application['full_name'] ?? '') . '</h2>'
                    . '<p>' . $e($application['email'] ?? '') . '<br>' . $e($application['phone'] ?? '') . '</p>'
                    . $profilePreview
                    . '<div class="payment-review-facts"><span><small>Professional headline</small><strong>' . $e($application['professional_headline'] ?? '') . '</strong></span>'
                    . '<span><small>Application submitted</small><strong>' . $e($application['created_at'] ?? '') . '</strong></span>'
                    . '<span><small>Profile-photo use</small><strong>Becomes official after approval</strong></span>'
                    . '<span><small>Next photo change</small><strong>25 days after approval</strong></span></div>'
                    . '<details open><summary>Biography and teaching plan</summary><h3>Biography</h3><p>' . nl2br($e($application['bio'] ?? '')) . '</p>'
                    . '<h3>Expertise</h3><p>' . nl2br($e($application['expertise'] ?? '')) . '</p>'
                    . '<h3>Teaching experience</h3><p>' . nl2br($e($application['teaching_experience'] ?? '')) . '</p>'
                    . '<h3>Planned course subjects</h3><p>' . nl2br($e($application['course_subjects'] ?? '')) . '</p>' . $socialLink . '</details>'
                    . '<div class="actions">' . $identityPreview . '</div>'
                    . '<p class="muted-copy">The photo and identity document are served only through this authenticated Admin route and remain outside the public web root.</p>'
                    . '<form class="portal-form" method="post" action="/admin/instructor-approvals">' . Csrf::field() . '<input type="hidden" name="instructor_id" value="' . $id . '">'
                    . '<label>Decision note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label>'
                    . '<div class="actions"><button class="portal-button" name="decision" value="approve" type="submit">Approve Instructor</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject application</button></div></form></article>';
            }
            $content .= '</section>';
        }
        return PortalPage::render('admin', 'Instructor approvals', $content);
    }
}
