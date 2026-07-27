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
            $content .= '<section class="instructor-approval-list" aria-label="Pending Instructor applications">';

            foreach ($applications as $application) {
                $id = (int) ($application['id'] ?? 0);
                $name = trim((string) ($application['full_name'] ?? 'Instructor applicant'));
                $email = trim((string) ($application['email'] ?? ''));
                $phone = trim((string) ($application['phone'] ?? ''));
                $headline = trim((string) ($application['professional_headline'] ?? 'Instructor application'));
                $submittedAt = trim((string) ($application['created_at'] ?? ''));
                $social = trim((string) ($application['social_profile_url'] ?? ''));
                $profilePath = trim((string) ($application['profile_image'] ?? ''));
                $identityPath = trim((string) ($application['identity_document'] ?? ''));

                $summaryPhoto = $profilePath !== ''
                    ? '<img src="/admin/instructor-approvals?media=profile&amp;id=' . $id . '" alt="" loading="lazy">'
                    : '<span aria-hidden="true">IN</span>';
                $profilePreview = $profilePath !== ''
                    ? '<a class="instructor-application-photo" href="/admin/instructor-approvals?media=profile&amp;id=' . $id . '" target="_blank" rel="noopener"><img src="/admin/instructor-approvals?media=profile&amp;id=' . $id . '" alt="Passport-size profile photo for ' . $e($name) . '" loading="lazy"><span>Open full photo ↗</span></a>'
                    : '<div class="form-alert error">Passport-size profile photo missing.</div>';
                $identityPreview = $identityPath !== ''
                    ? '<a class="portal-button secondary" href="/admin/instructor-approvals?media=identity&amp;id=' . $id . '" target="_blank" rel="noopener">Open private identity document</a>'
                    : '<span class="form-alert error">Identity document missing.</span>';
                $socialLink = $social !== ''
                    ? '<a class="portal-link instructor-profile-link" href="' . $e($social) . '" target="_blank" rel="noopener noreferrer">Open professional profile ↗</a>'
                    : '<span class="muted-copy">No professional profile link supplied.</span>';

                $content .= '<details class="portal-card instructor-review-card">'
                    . '<summary class="instructor-review-summary">'
                    . '<span class="instructor-summary-photo">' . $summaryPhoto . '</span>'
                    . '<span class="instructor-summary-main"><span class="status-badge pending">Awaiting review</span><strong>' . $e($name) . '</strong><small>' . $e($email) . '</small><span>' . $e($headline) . '</span></span>'
                    . '<span class="instructor-summary-date"><small>Submitted</small><strong>' . $e($submittedAt !== '' ? $submittedAt : 'Recently') . '</strong></span>'
                    . '<span class="instructor-summary-action">Review application <b aria-hidden="true">⌄</b></span>'
                    . '</summary>'
                    . '<div class="instructor-review-body">'
                    . '<section class="instructor-review-overview">'
                    . '<div class="instructor-review-photo-panel">' . $profilePreview . '</div>'
                    . '<div class="instructor-review-facts">'
                    . '<div><small>Applicant</small><strong>' . $e($name) . '</strong></div>'
                    . '<div><small>Email</small><strong>' . $e($email) . '</strong></div>'
                    . '<div><small>Phone</small><strong>' . $e($phone !== '' ? $phone : 'Not supplied') . '</strong></div>'
                    . '<div><small>Professional headline</small><strong>' . $e($headline) . '</strong></div>'
                    . '<div><small>Application submitted</small><strong>' . $e($submittedAt !== '' ? $submittedAt : 'Recently') . '</strong></div>'
                    . '<div><small>Profile-photo use</small><strong>Becomes official after approval</strong></div>'
                    . '<div><small>Next photo change</small><strong>25 days after approval</strong></div>'
                    . '</div></section>'
                    . '<section class="instructor-application-sections" aria-label="Instructor application details">'
                    . '<article><span>01</span><div><h3>Biography</h3><p>' . nl2br($e($application['bio'] ?? 'Not supplied')) . '</p></div></article>'
                    . '<article><span>02</span><div><h3>Expertise</h3><p>' . nl2br($e($application['expertise'] ?? 'Not supplied')) . '</p></div></article>'
                    . '<article><span>03</span><div><h3>Teaching experience</h3><p>' . nl2br($e($application['teaching_experience'] ?? 'Not supplied')) . '</p></div></article>'
                    . '<article><span>04</span><div><h3>Planned course subjects</h3><p>' . nl2br($e($application['course_subjects'] ?? 'Not supplied')) . '</p>' . $socialLink . '</div></article>'
                    . '</section>'
                    . '<section class="instructor-document-panel"><div><span>PRIVATE VERIFICATION</span><h3>Identity document</h3><p>The document is available only through this authenticated Admin route and remains outside the public web root.</p></div><div class="actions">' . $identityPreview . '</div></section>'
                    . '<form class="portal-form instructor-decision-form" method="post" action="/admin/instructor-approvals">' . Csrf::field() . '<input type="hidden" name="instructor_id" value="' . $id . '">'
                    . '<label>Decision note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label>'
                    . '<div class="actions"><button class="portal-button" name="decision" value="approve" type="submit">Approve Instructor</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject application</button></div></form>'
                    . '</div></details>';
            }

            $content .= '</section>';
        }

        return PortalPage::render('admin', 'Instructor approvals', $content);
    }
}
