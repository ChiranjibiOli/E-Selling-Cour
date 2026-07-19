<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentUnsubscribePage
{
    public static function render(array $enrollments, array $requests, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $pendingEnrollmentIds = [];
        foreach ($requests as $request) {
            if (($request['request_status'] ?? '') === 'pending') {
                $pendingEnrollmentIds[(int) ($request['enrollment_id'] ?? 0)] = true;
            }
        }

        $eligible = '';
        foreach ($enrollments as $enrollment) {
            $id = (int) ($enrollment['id'] ?? 0);
            $canRequest = (bool) ($enrollment['can_request_removal'] ?? false);
            if (!$canRequest || isset($pendingEnrollmentIds[$id])) {
                continue;
            }
            $eligible .= '<article class="portal-card"><span class="status-badge active">12-hour window open</span><h2>' . $e($enrollment['title'] ?? '') . '</h2>'
                . '<p>Instructor: ' . $e($enrollment['instructor_name'] ?? '') . '</p><small>Request deadline: ' . $e($enrollment['access_request_deadline'] ?? '') . '</small>'
                . '<form class="portal-form" method="post" action="/student/unsubscribe">' . Csrf::field()
                . '<input type="hidden" name="enrollment_id" value="' . $id . '"><label>Why do you want access removed?<textarea name="reason" rows="4" minlength="10" maxlength="2000" required></textarea></label>'
                . '<button class="portal-button danger" type="submit">Submit access-removal request</button></form></article>';
        }
        if ($eligible === '') {
            $eligible = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No eligible enrollments</h3><p>Requests are available only during the first twelve hours after access is granted, and only one pending request may exist per enrollment.</p></div>';
        }

        $historyRows = '';
        foreach ($requests as $request) {
            $status = (string) ($request['request_status'] ?? 'pending');
            $historyRows .= '<tr><td>#' . (int) ($request['id'] ?? 0) . '</td><td><strong>' . $e($request['course_title'] ?? '') . '</strong><small>' . $e($request['reason'] ?? '') . '</small></td>'
                . '<td><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span></td><td>' . $e($request['requested_at'] ?? '') . '</td><td>' . $e($request['processed_at'] ?? 'Awaiting decision') . '</td></tr>';
        }
        if ($historyRows === '') {
            $historyRows = '<tr class="empty-row"><td colspan="5"><div><span>⌁</span><strong>No requests submitted</strong><small>Your request history will remain visible here.</small></div></td></tr>';
        }

        $content = $alert
            . '<section class="panel-intro"><div><span>ACCESS POLICY</span><h2>Request removal without silently deleting business records.</h2><p>An approved request revokes course access. It does not automatically create a refund; refund eligibility is a separate financial decision.</p></div><div class="panel-intro-orb"><i></i><strong>12h</strong></div></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>ELIGIBLE COURSES</span><h3>Open request windows</h3></div></div><div class="portal-grid">' . $eligible . '</div></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>REQUEST HISTORY</span><h3>Administrator decisions</h3></div></div><div class="table-wrap"><table><thead><tr><th>Request</th><th>Course and reason</th><th>Status</th><th>Requested</th><th>Processed</th></tr></thead><tbody>' . $historyRows . '</tbody></table></div></section>';
        return PortalPage::render('student', 'Access requests', $content);
    }
}
