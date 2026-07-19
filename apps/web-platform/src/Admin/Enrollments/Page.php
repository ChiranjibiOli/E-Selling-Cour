<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminEnrollmentsPage
{
    public static function render(array $enrollments, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        $active = $revoked = 0;
        foreach ($enrollments as $enrollment) {
            $status = (string) ($enrollment['status'] ?? 'active');
            if ($status === 'active') { $active++; } else { $revoked++; }
            $rows .= '<tr><td>#' . (int) ($enrollment['id'] ?? 0) . '</td><td><strong>' . $e($enrollment['student_name'] ?? '') . '</strong><small>' . $e($enrollment['student_email'] ?? '') . '</small></td>'
                . '<td>' . $e($enrollment['course_title'] ?? '') . '</td><td>' . $e($enrollment['instructor_name'] ?? '') . '</td><td>#' . (int) ($enrollment['order_id'] ?? 0) . ' / #' . (int) ($enrollment['payment_id'] ?? 0) . '</td>'
                . '<td><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span></td><td>' . $e($enrollment['granted_at'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="7"><div><span>⌁</span><strong>No enrollments yet</strong><small>Approved payments create enrollment records automatically.</small></div></td></tr>';
        }
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Enrollments</span><i></i></div><strong>' . count($enrollments) . '</strong><small>Payment-linked access records</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Active access</span><i></i></div><strong>' . $active . '</strong><small>Lifetime course access</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Revoked/refunded</span><i></i></div><strong>' . $revoked . '</strong><small>Historical records retained</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Traceability</span><i></i></div><strong>Complete</strong><small>Student, order and payment</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>COURSE ACCESS</span><h3>Enrollment records</h3></div><label class="table-search">⌕ <input placeholder="Search records"></label></div>'
            . '<div class="table-wrap"><table><thead><tr><th>Enrollment</th><th>Student</th><th>Course</th><th>Instructor</th><th>Order / Payment</th><th>Status</th><th>Granted</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
        return PortalPage::render('admin', 'Enrollments', $content);
    }
}
