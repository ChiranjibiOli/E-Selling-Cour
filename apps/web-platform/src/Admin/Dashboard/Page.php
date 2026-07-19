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
            $rows = '<tr class="empty-row"><td colspan="6"><div><span>⌁</span><strong>No orders yet</strong><small>Student checkout activity will appear here.</small></div></td></tr>';
        }
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Verified revenue</span><i></i></div><strong>NPR ' . number_format((float) ($metrics['verified_revenue'] ?? 0), 2) . '</strong><small>Paid transactions only</small></article><article class="metric-card violet"><div class="metric-top"><span>Platform earnings</span><i></i></div><strong>NPR ' . number_format((float) ($metrics['platform_earnings'] ?? 0), 2) . '</strong><small>Recorded commission</small></article><article class="metric-card teal"><div class="metric-top"><span>Active enrollments</span><i></i></div><strong>' . (int) ($metrics['active_enrollments'] ?? 0) . '</strong><small>Lifetime course access</small></article><article class="metric-card orange"><div class="metric-top"><span>Pending payments</span><i></i></div><strong>' . (int) ($metrics['pending_payments'] ?? 0) . '</strong><small>Manual verification queue</small></article></section>'
            . '<section class="portal-grid"><article class="portal-card"><span class="status-badge pending">Needs review</span><h2>' . (int) ($metrics['pending_instructors'] ?? 0) . '</h2><p>Instructor applications awaiting a decision.</p><a class="portal-link" href="/admin/instructor-approvals">Review instructors →</a></article><article class="portal-card"><span class="status-badge pending">Needs review</span><h2>' . (int) ($metrics['pending_courses'] ?? 0) . '</h2><p>Submitted courses awaiting publication approval.</p><a class="portal-link" href="/admin/course-approvals">Review courses →</a></article><article class="portal-card"><span class="status-badge pending">Payout queue</span><h2>' . (int) ($metrics['pending_withdrawals'] ?? 0) . '</h2><p>Instructor withdrawal requests awaiting action.</p><a class="portal-link" href="/admin/withdrawals">Review withdrawals →</a></article><article class="portal-card"><span class="status-badge pending">Support</span><h2>' . (int) ($metrics['new_messages'] ?? 0) . '</h2><p>New public support requests.</p><a class="portal-link" href="/admin/contact-messages">Open support center →</a></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>RECENT ORDERS</span><h3>Latest platform commerce activity</h3></div><a class="portal-button secondary" href="/admin/orders">All orders</a></div><div class="table-wrap"><table><thead><tr><th>Order</th><th>Student</th><th>Items</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
        return PortalPage::render('admin', 'Control overview', $content);
    }
}
