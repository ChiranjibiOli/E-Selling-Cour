<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminPaymentsPage
{
    public static function render(array $payments, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $rows = '';
        $pendingValue = 0.0;
        foreach ($payments as $payment) {
            $id = (int) ($payment['id'] ?? 0);
            $pendingValue += (float) ($payment['paid_amount'] ?? 0);
            $rows .= '<article class="payment-review-row"><div><span class="status-badge pending">Needs verification</span><h2>Payment #' . $id . '</h2><p>' . $e($payment['student_name'] ?? '') . ' · ' . $e($payment['student_email'] ?? '') . '</p></div>'
                . '<div class="payment-review-facts"><span><small>Submitted</small><strong>NPR ' . number_format((float) ($payment['paid_amount'] ?? 0), 2) . '</strong></span><span><small>Expected</small><strong>NPR ' . number_format((float) ($payment['final_amount'] ?? 0), 2) . '</strong></span><span><small>Transaction</small><strong>' . $e($payment['transaction_id'] ?? '') . '</strong></span></div>'
                . '<button class="portal-button secondary" type="button" data-proof-open data-proof-url="/admin/payments?proof=' . $id . '" data-proof-title="Payment #' . $id . ' proof">View uploaded proof</button>'
                . (($payment['note'] ?? '') !== '' ? '<div class="payment-student-note"><strong>Student note</strong><p>' . $e($payment['note']) . '</p></div>' : '')
                . '<form class="portal-form" method="post" action="/admin/payments">' . Csrf::field() . '<input type="hidden" name="payment_id" value="' . $id . '"><label>Verification note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label><div class="actions"><button class="portal-button" name="decision" value="approve" type="submit">Approve payment and grant access</button><button class="portal-button danger" name="decision" value="reject" type="submit">Reject payment</button></div></form></article>';
        }
        if ($rows === '') {
            $rows = '<div class="rich-empty"><h3>Payment queue is clear</h3><p>Manual payments appear here only after a Student uploads a real protected receipt.</p></div>';
        }
        $content = $alert . '<section class="payment-review-console"><header><div><span>PAYMENT VERIFICATION</span><h2>Manual payment queue</h2><p>Compare the order amount and transaction reference, then inspect the actual uploaded screenshot or PDF.</p></div><div><strong>' . count($payments) . '</strong><span>NPR ' . number_format($pendingValue, 2) . '</span></div></header><div class="payment-review-list">' . $rows . '</div></section>'
            . '<dialog class="payment-proof-dialog" data-proof-dialog aria-labelledby="payment-proof-title"><div class="payment-proof-dialog-shell"><header><h2 id="payment-proof-title" data-proof-title>Payment proof</h2><button type="button" data-proof-close aria-label="Close proof viewer">×</button></header><div><iframe title="Protected payment proof" data-proof-frame></iframe></div><footer><button class="portal-button" type="button" data-proof-close>Close</button></footer></div></dialog>';
        return PortalPage::render('admin', 'Payments', $content);
    }
}
