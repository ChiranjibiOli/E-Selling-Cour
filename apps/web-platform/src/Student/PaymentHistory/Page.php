<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentPaymentHistoryPage
{
    public static function render(array $payments, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        $pending = $paid = $rejected = 0;
        $paidValue = 0.0;
        foreach ($payments as $payment) {
            $status = (string) ($payment['payment_status'] ?? 'pending');
            if ($status === 'pending') { $pending++; }
            if ($status === 'paid') { $paid++; $paidValue += (float) ($payment['paid_amount'] ?? 0); }
            if (in_array($status, ['rejected', 'failed'], true)) { $rejected++; }
            $rows .= '<tr><td>#' . (int) ($payment['id'] ?? 0) . '</td><td>#' . (int) ($payment['order_id'] ?? 0) . '</td><td>' . $e(ucfirst((string) ($payment['payment_method'] ?? 'manual'))) . '</td>'
                . '<td>NPR ' . number_format((float) ($payment['paid_amount'] ?? 0), 2) . '</td><td><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span></td>'
                . '<td>' . $e($payment['transaction_id'] ?? '') . '</td><td>' . $e($payment['uploaded_at'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="7"><div><span>⌁</span><strong>No payments submitted</strong><small>Your manual and automatic transactions will appear here.</small></div></td></tr>';
        }
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Transactions</span><i></i></div><strong>' . count($payments) . '</strong><small>All submitted payments</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Awaiting review</span><i></i></div><strong>' . $pending . '</strong><small>Admin action required</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Approved value</span><i></i></div><strong>NPR ' . number_format($paidValue, 2) . '</strong><small>' . $paid . ' verified payments</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Rejected or failed</span><i></i></div><strong>' . $rejected . '</strong><small>Review before retrying</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>PAYMENT HISTORY</span><h3>Your transaction record</h3></div><a class="portal-button secondary" href="/student/payment">Open payment</a></div>'
            . '<div class="table-wrap"><table><thead><tr><th>Payment</th><th>Order</th><th>Method</th><th>Amount</th><th>Status</th><th>Reference</th><th>Submitted</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
        return PortalPage::render('student', 'Payment history', $content);
    }
}
