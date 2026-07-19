<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorWithdrawalsPage
{
    public static function render(array $data, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $available = (float) ($data['available_balance'] ?? 0);
        $requests = is_array($data['requests'] ?? null) ? $data['requests'] : [];
        $pending = $paid = $reserved = 0.0;
        $rows = '';
        foreach ($requests as $request) {
            $status = (string) ($request['request_status'] ?? 'pending');
            $amount = (float) ($request['requested_amount'] ?? 0);
            if (in_array($status, ['pending', 'approved'], true)) { $reserved += $amount; }
            if ($status === 'pending') { $pending++; }
            if ($status === 'paid') { $paid += $amount; }
            $rows .= '<tr><td>#' . (int) ($request['id'] ?? 0) . '</td><td>NPR ' . number_format($amount, 2) . '</td><td>' . $e(ucfirst((string) ($request['payment_method'] ?? 'bank'))) . '</td><td><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span></td><td>' . $e($request['requested_at'] ?? '') . '</td><td>' . $e($request['admin_note'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="6"><div><span>⌁</span><strong>No withdrawal requests</strong><small>Verified available earnings can be reserved for payout.</small></div></td></tr>';
        }
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $requestForm = $available > 0
            ? '<form class="panel-form" method="post" action="/instructor/withdrawals">' . Csrf::field() . '<label>Payout method<select name="payment_method"><option value="bank">Bank transfer</option><option value="esewa">eSewa</option><option value="khalti">Khalti</option></select></label><label>Instructor note<textarea name="note" rows="4" maxlength="1000" placeholder="Optional payout note"></textarea></label><div class="payment-note"><span>i</span><p>This request reserves all currently available verified earnings. This avoids double-withdrawal and partial-ledger ambiguity.</p></div><button class="portal-button full" type="submit">Request NPR ' . number_format($available, 2) . ' withdrawal</button></form>'
            : '<div class="rich-empty"><h3>No available balance</h3><p>Approved student payments create earnings. Reserved or paid earnings cannot be requested again.</p><a class="portal-button secondary" href="/instructor/sales">View sales ledger</a></div>';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Available balance</span><i></i></div><strong>NPR ' . number_format($available, 2) . '</strong><small>Verified and unreserved</small></article><article class="metric-card violet"><div class="metric-top"><span>Reserved balance</span><i></i></div><strong>NPR ' . number_format($reserved, 2) . '</strong><small>Pending or approved payout</small></article><article class="metric-card teal"><div class="metric-top"><span>Paid total</span><i></i></div><strong>NPR ' . number_format($paid, 2) . '</strong><small>Completed withdrawals</small></article><article class="metric-card orange"><div class="metric-top"><span>Pending requests</span><i></i></div><strong>' . (int) $pending . '</strong><small>Waiting for admin review</small></article></section>'
            . '<div class="panel-split"><section class="data-card"><div class="data-card-head"><div><span>PAYOUT HISTORY</span><h3>Your withdrawal requests</h3></div><a class="portal-button secondary" href="/instructor/bank-details">Payout details</a></div><div class="table-wrap"><table><thead><tr><th>Request</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th><th>Admin note</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section><aside class="summary-card accent-card"><span>REQUEST PAYOUT</span>' . $requestForm . '</aside></div>';
        return PortalPage::render('instructor', 'Withdrawals', $content);
    }
}
