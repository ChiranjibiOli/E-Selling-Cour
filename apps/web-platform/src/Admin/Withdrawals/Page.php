<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminWithdrawalsPage
{
    public static function render(array $withdrawals, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cards = '';
        $pendingValue = $approvedValue = 0.0;
        foreach ($withdrawals as $withdrawal) {
            $id = (int) ($withdrawal['id'] ?? 0);
            $status = (string) ($withdrawal['request_status'] ?? 'pending');
            $amount = (float) ($withdrawal['requested_amount'] ?? 0);
            if ($status === 'pending') { $pendingValue += $amount; } else { $approvedValue += $amount; }
            $destination = match ((string) ($withdrawal['payment_method'] ?? 'bank')) {
                'esewa' => 'eSewa · ' . $e($withdrawal['esewa_number'] ?? ''),
                'khalti' => 'Khalti · ' . $e($withdrawal['khalti_number'] ?? ''),
                default => $e($withdrawal['bank_name'] ?? 'Bank') . ' · ' . $e($withdrawal['account_number'] ?? ''),
            };
            $buttons = $status === 'pending'
                ? '<button class="portal-button" name="decision" value="approve" type="submit">Approve request</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject</button>'
                : '<button class="portal-button" name="decision" value="paid" type="submit">Record payout as paid</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject and release balance</button>';
            $cards .= '<article class="portal-card"><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span><h2>Withdrawal #' . $id . '</h2><p><strong>Instructor:</strong> ' . $e($withdrawal['instructor_name'] ?? '') . '<br><strong>Email:</strong> ' . $e($withdrawal['instructor_email'] ?? '') . '<br><strong>Amount:</strong> NPR ' . number_format($amount, 2) . '<br><strong>Destination:</strong> ' . $destination . '</p>'
                . '<form class="portal-form" method="post" action="/admin/withdrawals">' . Csrf::field() . '<input type="hidden" name="withdrawal_id" value="' . $id . '"><label>Admin note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label><label>Transaction reference<input name="transaction_reference" maxlength="150" placeholder="Required when marking paid"></label><div class="actions">' . $buttons . '</div></form></article>';
        }
        if ($cards === '') {
            $cards = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No payout requests need review</h3><p>Instructor withdrawals appear here only after verified earnings are reserved.</p></div>';
        }
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Open requests</span><i></i></div><strong>' . count($withdrawals) . '</strong><small>Pending and approved</small></article><article class="metric-card violet"><div class="metric-top"><span>Pending value</span><i></i></div><strong>NPR ' . number_format($pendingValue, 2) . '</strong><small>Needs first decision</small></article><article class="metric-card teal"><div class="metric-top"><span>Approved value</span><i></i></div><strong>NPR ' . number_format($approvedValue, 2) . '</strong><small>Ready to record payout</small></article><article class="metric-card orange"><div class="metric-top"><span>Ledger protection</span><i></i></div><strong>Reserved</strong><small>No double withdrawal</small></article></section><section class="data-card"><div class="data-card-head"><div><span>PAYOUT QUEUE</span><h3>Instructor withdrawal review</h3></div><span class="secure-pill">Admin only</span></div><div class="portal-grid">' . $cards . '</div></section>';
        return PortalPage::render('admin', 'Withdrawals', $content);
    }
}
