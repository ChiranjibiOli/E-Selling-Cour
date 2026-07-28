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
        $reservedFromRows = 0.0;
        $paidFromRows = 0.0;
        $pending = 0;
        $rows = '';

        foreach ($requests as $request) {
            $status = (string) ($request['request_status'] ?? 'pending');
            $amount = (float) ($request['requested_amount'] ?? 0);
            if (in_array($status, ['pending', 'approved'], true)) { $reservedFromRows += $amount; }
            if ($status === 'pending') { $pending++; }
            if ($status === 'paid') { $paidFromRows += $amount; }

            $requestId = (int) ($request['id'] ?? 0);
            $source = (string) ($request['request_source'] ?? 'instructor');
            $sourceLabel = $source === 'automatic' ? 'Automatic queue' : 'Instructor request';
            $action = '<span>—</span>';
            if ($status === 'rejected') {
                $action = '<form method="post" action="/instructor/withdrawals">' . Csrf::field()
                    . '<input type="hidden" name="action" value="retry"><input type="hidden" name="request_id" value="' . $requestId . '">'
                    . '<button class="portal-button secondary" type="submit">Request again</button></form>';
            } elseif ($status === 'approved') {
                $action = '<small>Waiting for transfer or Admin settlement</small>';
            } elseif ($status === 'pending') {
                $action = '<small>Waiting for Admin review</small>';
            } elseif ($status === 'paid') {
                $action = '<small>Transfer completed</small>';
            }

            $rows .= '<tr><td>#' . $requestId . '</td><td>' . $e($sourceLabel) . '</td><td>NPR ' . number_format($amount, 2) . '</td><td>'
                . $e(ucfirst((string) ($request['payment_method'] ?? 'bank'))) . '</td><td><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span></td><td>'
                . $e($request['requested_at'] ?? '') . '</td><td>' . $e($request['admin_note'] ?? '') . '</td><td>' . $action . '</td></tr>';
        }

        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="8"><div><span>⌁</span><strong>No withdrawal requests</strong><small>Verified Instructor earnings will be prepared for payout here.</small></div></td></tr>';
        }

        $reserved = array_key_exists('reserved_balance', $data) ? (float) $data['reserved_balance'] : $reservedFromRows;
        $paid = array_key_exists('paid_total', $data) ? (float) $data['paid_total'] : $paidFromRows;
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $requestForm = $available > 0
            ? '<form class="panel-form" method="post" action="/instructor/withdrawals">' . Csrf::field()
                . '<input type="hidden" name="action" value="request"><label>Payout method<select name="payment_method"><option value="bank">Bank transfer</option><option value="esewa">eSewa</option><option value="khalti">Khalti</option></select></label>'
                . '<label>Instructor note<textarea name="note" rows="4" maxlength="1000" placeholder="Optional payout note"></textarea></label>'
                . '<div class="payment-note"><span>i</span><p>This reserves all currently available verified earnings. Rejected money returns here and can be requested again.</p></div>'
                . '<button class="portal-button full" type="submit">Request NPR ' . number_format($available, 2) . ' withdrawal</button></form>'
            : '<div class="rich-empty"><h3>No available balance</h3><p>Your money may already be queued for transfer. A rejected request returns to available balance and shows a Request again button.</p><a class="portal-button secondary" href="/instructor/sales">View sales ledger</a></div>';

        $content = $alert
            . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Ready for payout</span><i></i></div><strong>NPR ' . number_format($available, 2) . '</strong><small>Verified and available to request</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Waiting transfer</span><i></i></div><strong>NPR ' . number_format($reserved, 2) . '</strong><small>Automatic or Admin payout queue</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Paid total</span><i></i></div><strong>NPR ' . number_format($paid, 2) . '</strong><small>Completed Instructor transfers</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Admin review</span><i></i></div><strong>' . $pending . '</strong><small>Instructor requests awaiting approval</small></article></section>'
            . '<section class="data-card"><div class="payment-note"><span>✓</span><p>Every verified sale creates Instructor earnings. CourseHub attempts automatic payout when a real provider is configured. Otherwise, the money stays queued for Admin settlement and remains visible here.</p></div></section>'
            . '<div class="panel-split"><section class="data-card"><div class="data-card-head"><div><span>PAYOUT HISTORY</span><h3>Your transfer and withdrawal requests</h3></div><a class="portal-button secondary" href="/instructor/bank-details">Payout details</a></div>'
            . '<div class="table-wrap"><table><thead><tr><th>Request</th><th>Source</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th><th>Admin note</th><th>Action</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>'
            . '<aside class="summary-card accent-card"><span>REQUEST ADMIN PAYMENT</span>' . $requestForm . '</aside></div>';

        return PortalPage::render('instructor', 'Withdrawals', $content);
    }
}
