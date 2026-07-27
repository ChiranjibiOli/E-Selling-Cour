<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminDashboardPage
{
    public static function render(array $data, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $metrics = is_array($data['metrics'] ?? null) ? $data['metrics'] : [];
        $orders = is_array($data['recent_orders'] ?? null) ? $data['recent_orders'] : [];
        $rows = '';
        foreach ($orders as $order) {
            $rows .= '<tr><td>#' . (int) ($order['id'] ?? 0) . '</td><td>' . $e($order['student_name'] ?? '') . '</td><td>' . (int) ($order['item_count'] ?? 0) . '</td><td>NPR ' . number_format((float) ($order['final_amount'] ?? 0), 2) . '</td><td><span class="status-badge ' . $e($order['order_status'] ?? 'pending') . '">' . $e($order['order_status'] ?? 'pending') . '</span></td><td>' . $e($order['created_at'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="6"><div><strong>No orders yet</strong><small>Student checkout activity will appear here.</small></div></td></tr>';
        }
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $summary = '<div class="admin-summary-strip admin-dashboard-summary">'
            . '<span><small>Verified revenue</small><strong>NPR ' . number_format((float) ($metrics['verified_revenue'] ?? 0), 2) . '</strong></span>'
            . '<span><small>Platform earnings</small><strong>NPR ' . number_format((float) ($metrics['platform_earnings'] ?? 0), 2) . '</strong></span>'
            . '<span><small>Active enrollments</small><strong>' . (int) ($metrics['active_enrollments'] ?? 0) . '</strong></span>'
            . '<span><small>Pending payments</small><strong>' . (int) ($metrics['pending_payments'] ?? 0) . '</strong></span>'
            . '<span><small>Students</small><strong>' . (int) ($metrics['students'] ?? 0) . '</strong></span>'
            . '<span><small>Instructors</small><strong>' . (int) ($metrics['instructors'] ?? 0) . '</strong></span>'
            . '</div>';
        $queues = '<section class="admin-dashboard-queues"><header><div><h2>Work queues</h2><p>Open only the areas that currently need an Admin decision.</p></div></header><div class="table-wrap"><table><thead><tr><th>Queue</th><th>Waiting</th><th>Open</th></tr></thead><tbody>'
            . '<tr><td>Instructor applications</td><td>' . (int) ($metrics['pending_instructors'] ?? 0) . '</td><td><a class="text-button" href="/admin/instructor-approvals">Review</a></td></tr>'
            . '<tr><td>Course approvals</td><td>' . (int) ($metrics['pending_courses'] ?? 0) . '</td><td><a class="text-button" href="/admin/course-approvals">Review</a></td></tr>'
            . '<tr><td>Withdrawal requests</td><td>' . (int) ($metrics['pending_withdrawals'] ?? 0) . '</td><td><a class="text-button" href="/admin/withdrawals">Review</a></td></tr>'
            . '<tr><td>Support messages</td><td>' . (int) ($metrics['new_messages'] ?? 0) . '</td><td><a class="text-button" href="/admin/contact-messages">Open</a></td></tr>'
            . '</tbody></table></div></section>';
        $recent = '<section class="admin-dashboard-orders"><header><div><h2>Recent orders</h2><p>Latest platform commerce activity.</p></div><a class="portal-button secondary" href="/admin/orders">All orders</a></header><div class="table-wrap"><table><thead><tr><th>Order</th><th>Student</th><th>Items</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
        return PortalPage::render('admin', 'Overview', $alert . '<section class="admin-console-panel">' . $summary . $queues . $recent . '</section>');
    }
}
