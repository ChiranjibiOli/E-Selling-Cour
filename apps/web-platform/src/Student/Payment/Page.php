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
            $content = $alert . '<section class="data-card"><div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No unpaid order found</h3><p>Create an order from your cart before opening payment.</p><a class="portal-button" href="/student/cart">Open my cart</a></div></section>';
            return PortalPage::render('student', 'Payment', $content);
        }

        $form = '<form method="post" action="/student/payment?order=' . $orderId . '">' . Csrf::field() . '<input type="hidden" name="order_id" value="' . $orderId . '">'
            . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>PAYMENT METHOD</span><h3>Pay order #' . $orderId . '</h3></div><span class="secure-pill">NPR ' . number_format($amount, 2) . '</span></div>'
            . '<div class="payment-methods"><button class="payment-method active" type="button"><i>QR</i><span><strong>Manual QR or bank payment</strong><small>Submit transaction reference for admin verification</small></span><b>✓</b></button>'
            . '<button class="payment-method" type="button" disabled title="Configure gateway credentials first"><i>eS</i><span><strong>eSewa</strong><small>Requires merchant credentials and webhook verification</small></span></button>'
            . '<button class="payment-method" type="button" disabled title="Configure gateway credentials first"><i>Kh</i><span><strong>Khalti</strong><small>Requires live public/secret keys and webhook verification</small></span></button></div>'
            . '<div class="panel-form"><label>Transaction reference<input name="transaction_id" maxlength="150" value="' . $e($values['transaction_id'] ?? '') . '" placeholder="Bank, eSewa or Khalti reference" required></label>'
            . '<label>Uploaded proof filename<input name="proof_image" maxlength="255" value="' . $e($values['proof_image'] ?? '') . '" placeholder="payment-proof-123.jpg" required><small>The secure media uploader must create this filename before production use.</small></label>'
            . '<label>Payment note<textarea name="note" rows="4" maxlength="1000" placeholder="Sender name, paid account or useful verification detail">' . $e($values['note'] ?? '') . '</textarea></label>'
            . '<div class="payment-note"><span>i</span><p>Submitting this form does not grant access. Enrollment is created only after an administrator verifies the payment amount and approves it.</p></div>'
            . '<button class="portal-button" type="submit">Submit payment for verification</button></div></section>'
            . '<aside class="summary-card"><span>ORDER #' . $orderId . '</span>' . $itemList . '<div class="summary-row"><span>Original amount</span><strong>NPR ' . number_format((float) ($order['original_amount'] ?? 0), 2) . '</strong></div>'
            . '<div class="summary-row"><span>Discount</span><strong>− NPR ' . number_format((float) ($order['discount_amount'] ?? 0), 2) . '</strong></div><div class="summary-total"><span>Payable</span><strong>NPR ' . number_format($amount, 2) . '</strong></div>'
            . '<a class="portal-button secondary full" href="/student/payment-history">View payment history</a></aside></div></form>';

        return PortalPage::render('student', 'Payment', $alert . $form, '<a class="portal-button secondary" href="/student/payment-history">Payment history</a>');
    }
}
