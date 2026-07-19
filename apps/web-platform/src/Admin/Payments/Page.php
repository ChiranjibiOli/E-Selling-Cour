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
        $cards = '';
        $pendingValue = 0.0;
        foreach ($payments as $payment) {
            $id = (int) ($payment['id'] ?? 0);
            $pendingValue += (float) ($payment['paid_amount'] ?? 0);
            $proof = $e($payment['proof_image'] ?? 'No proof filename');
            $cards .= '<article class="portal-card payment-review-card"><span class="status-badge pending">Needs verification</span><h2>Payment #' . $id . '</h2>'
                . '<p><strong>Student:</strong> ' . $e($payment['student_name'] ?? '') . '<br><strong>Email:</strong> ' . $e($payment['student_email'] ?? '') . '<br><strong>Order:</strong> #' . (int) ($payment['order_id'] ?? 0) . '</p>'
                . '<div class="payment-review-facts"><span><small>Submitted amount</small><strong>NPR ' . number_format((float) ($payment['paid_amount'] ?? 0), 2) . '</strong></span>'
                . '<span><small>Expected amount</small><strong>NPR ' . number_format((float) ($payment['final_amount'] ?? 0), 2) . '</strong></span>'
                . '<span><small>Transaction</small><strong>' . $e($payment['transaction_id'] ?? '') . '</strong></span><span><small>Proof</small><strong>' . $proof . '</strong></span></div>'
                . (($payment['note'] ?? '') !== '' ? '<p><strong>Student note:</strong> ' . $e($payment['note']) . '</p>' : '')
                . '<form class="portal-form" method="post" action="/admin/payments">' . Csrf::field() . '<input type="hidden" name="payment_id" value="' . $id . '">'
                . '<label>Verification note<textarea name="note" rows="3" maxlength="1000" placeholder="Required when rejecting"></textarea></label>'
                . '<div class="actions"><button class="portal-button" name="decision" value="approve" type="submit">Approve payment and grant access</button>'
                . '<button class="portal-button danger" name="decision" value="reject" type="submit">Reject payment</button></div></form></article>';
        }
        if ($cards === '') {
            $cards = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>Payment queue is clear</h3><p>New manual-payment submissions will appear here with order, amount, reference and proof details.</p></div>';
        }
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Needs verification</span><i></i></div><strong>' . count($payments) . '</strong><small>Pending manual payments</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Pending value</span><i></i></div><strong>NPR ' . number_format($pendingValue, 2) . '</strong><small>Must match server orders</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Approval result</span><i></i></div><strong>Atomic</strong><small>Payment, order and enrollment</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Access type</span><i></i></div><strong>Lifetime</strong><small>Only after verification</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>PAYMENT VERIFICATION</span><h3>Manual payment queue</h3></div><span class="secure-pill">Admin only</span></div><div class="portal-grid">' . $cards . '</div></section>';
        return PortalPage::render('admin', 'Payments', $content);
    }
}
