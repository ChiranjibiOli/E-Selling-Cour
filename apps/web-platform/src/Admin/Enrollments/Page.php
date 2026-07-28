<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminEnrollmentsPage
{
    public static function render(array $enrollments, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        $active = 0;
        $inactive = 0;

        foreach ($enrollments as $enrollment) {
            $status = (string) ($enrollment['status'] ?? 'active');
            if ($status === 'active') {
                $active++;
            } else {
                $inactive++;
            }
            $rows .= '<tr><td>#' . (int) ($enrollment['id'] ?? 0) . '</td><td><strong>' . $e($enrollment['student_name'] ?? '') . '</strong><small>' . $e($enrollment['student_email'] ?? '') . '</small></td>'
                . '<td>' . $e($enrollment['course_title'] ?? '') . '</td><td>' . $e($enrollment['instructor_name'] ?? '') . '</td><td>#' . (int) ($enrollment['order_id'] ?? 0) . ' / #' . (int) ($enrollment['payment_id'] ?? 0) . '</td>'
                . '<td><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span></td><td>' . $e($enrollment['granted_at'] ?? '') . '</td></tr>';
        }

        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="7"><div><span>⌁</span><strong>No enrollments yet</strong><small>Verified payments create lifetime enrollment records automatically.</small></div></td></tr>';
        }

        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $content = $alert
            . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Enrollments</span><i></i></div><strong>' . count($enrollments) . '</strong><small>Payment-linked access records</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Active access</span><i></i></div><strong>' . $active . '</strong><small>Purchased lifetime access</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Inactive records</span><i></i></div><strong>' . $inactive . '</strong><small>Administrative or policy history</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>COURSE ACCESS</span><h3>Enrollment records</h3></div><label class="table-search">⌕ <input placeholder="Search records"></label></div>'
            . '<div class="payment-note"><span>i</span><p>Students cannot request removal of purchased lifetime access. Refunds and exceptional policy actions are handled separately.</p></div>'
            . '<div class="table-wrap"><table><thead><tr><th>Enrollment</th><th>Student</th><th>Course</th><th>Instructor</th><th>Order / Payment</th><th>Status</th><th>Granted</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';

        return PortalPage::render('admin', 'Enrollments', $content);
    }
}
