<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentPaymentPage
{
    public static function render(array $order, string $message = '', bool $success = true, array $values = []): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $orderId = (int) ($order['id'] ?? 0);
        $amount = (float) ($order['final_amount'] ?? 0);
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $itemList = '';
        foreach ($items as $item) {
            $itemList .= '<div class="order-mini"><i>CH</i><div><strong>' . $e($item['title'] ?? 'Course') . '</strong><small>'
                . $e($item['instructor_name'] ?? 'Instructor') . '</small></div><b>NPR ' . number_format((float) ($item['final_price'] ?? 0), 2) . '</b></div>';
        }

        if ($orderId < 1) {
            $content = $alert . '<section class="data-card"><div class="rich-empty"><h3>No unpaid order found</h3><p>Create an order from your cart before opening payment.</p><a class="portal-button" href="/student/cart">Open my cart</a></div></section>';
            return PortalPage::render('student', 'Payment', $content);
        }

        $form = '<form method="post" action="/student/payment?order=' . $orderId . '" enctype="multipart/form-data" novalidate data-payment-proof-form>' . Csrf::field() . '<input type="hidden" name="order_id" value="' . $orderId . '">'
            . '<input type="hidden" name="payment_method" value="manual">'
            . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>MANUAL PAYMENT</span><h3>Pay order #' . $orderId . '</h3></div><span class="secure-pill">NPR ' . number_format($amount, 2) . '</span></div>'
            . '<div class="payment-methods"><div class="payment-method active"><i>QR</i><span><strong>Manual QR, wallet or bank payment</strong><small>Pay outside CourseHub, then upload the real receipt for Admin verification.</small></span><b>✓</b></div></div>'
            . '<div class="payment-note"><span>i</span><p>CourseHub does not use automatic payment checkout. Access starts only after an Admin verifies the amount, transaction reference and uploaded proof.</p></div>'
            . '<div class="panel-form"><label>Transaction reference<input type="text" name="transaction_id" minlength="3" maxlength="150" value="' . $e($values['transaction_id'] ?? '') . '" placeholder="Bank, eSewa, Khalti or wallet reference" required data-error="Enter the real transaction reference from the completed payment."></label>'
            . '<label>Payment screenshot or receipt<input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp,application/pdf" required data-payment-proof-input data-error="Upload the actual JPG, PNG, WebP or PDF payment receipt."><small>Images must be at least 400 × 300 pixels and no larger than 8 MB.</small></label>'
            . '<div class="payment-proof-preview" data-payment-proof-preview><span>No receipt selected</span></div>'
            . '<label>Payment note<textarea name="note" rows="4" maxlength="1000" placeholder="Sender name, paid account or useful verification detail">' . $e($values['note'] ?? '') . '</textarea></label>'
            . '<button class="portal-button" type="submit">Submit manual proof for verification</button></div></section>'
            . '<aside class="summary-card"><span>ORDER #' . $orderId . '</span>' . $itemList . '<div class="summary-row"><span>Original amount</span><strong>NPR ' . number_format((float) ($order['original_amount'] ?? 0), 2) . '</strong></div>'
            . '<div class="summary-row"><span>Discount</span><strong>− NPR ' . number_format((float) ($order['discount_amount'] ?? 0), 2) . '</strong></div><div class="summary-total"><span>Payable</span><strong>NPR ' . number_format($amount, 2) . '</strong></div>'
            . '<a class="portal-button secondary full" href="/student/payment-history">View payment history</a></aside></div></form>';

        return PortalPage::render('student', 'Payment', $alert . $form, '<a class="portal-button secondary" href="/student/payment-history">Payment history</a>');
    }
}
