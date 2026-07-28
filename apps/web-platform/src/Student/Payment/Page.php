<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentPaymentPage
{
    public static function render(array $order, string $message = '', bool $success = true, array $values = [], array $options = []): Response
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

        $flow = '<nav class="student-purchase-flow" aria-label="Purchase progress"><span class="done"><i>✓</i>Review cart</span><span class="done"><i>✓</i>Confirm order</span><span class="active"><i>3</i>Complete payment</span></nav>';

        if ($orderId < 1) {
            $content = $alert . $flow . '<section class="data-card"><div class="rich-empty"><h3>No unpaid order found</h3><p>Create an order from My cart before opening the payment step.</p><a class="portal-button" href="/student/cart">Open My cart</a></div></section>';
            return PortalPage::render('student', 'My cart · Payment', $content);
        }

        $gatewayButton = static function (string $provider, string $icon, array $state) use ($e): string {
            $available = ($state['available'] ?? false) === true;
            $mode = strtolower((string) ($state['mode'] ?? 'sandbox'));
            $modeLabel = match ($mode) {
                'local-demo' => ' local demo',
                'sandbox' => ' sandbox',
                default => '',
            };
            $label = ucfirst($provider) . $modeLabel;
            $copy = $available
                ? ($mode === 'local-demo' ? 'Complete a safe local test payment' : 'Pay the platform merchant and verify automatically')
                : (($state['configured'] ?? false) === true ? 'Disabled by the platform administrator' : 'Merchant connection is not configured');
            return '<button class="payment-method" type="' . ($available ? 'submit' : 'button') . '" name="payment_method" value="' . $e($provider) . '"'
                . ($available ? ' formnovalidate' : ' disabled') . '><i>' . $e($icon) . '</i><span><strong>' . $e($label) . '</strong><small>' . $e($copy) . '</small></span><b>' . ($available ? '→' : '×') . '</b></button>';
        };

        $esewa = is_array($options['esewa'] ?? null) ? $options['esewa'] : [];
        $khalti = is_array($options['khalti'] ?? null) ? $options['khalti'] : [];

        $form = '<form method="post" action="/student/payment?order=' . $orderId . '" enctype="multipart/form-data" novalidate data-payment-proof-form>' . Csrf::field() . '<input type="hidden" name="order_id" value="' . $orderId . '">'
            . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>FINAL PAYMENT STEP</span><h3>Pay order #' . $orderId . '</h3><p>Complete payment here, then follow verification in Payment history.</p></div><span class="secure-pill">NPR ' . number_format($amount, 2) . '</span></div>'
            . '<div class="payment-methods"><button class="payment-method active" type="button"><i>QR</i><span><strong>Manual QR or bank payment</strong><small>Upload the receipt for Admin verification</small></span><b>✓</b></button>'
            . $gatewayButton('esewa', 'eS', $esewa) . $gatewayButton('khalti', 'Kh', $khalti) . '</div>'
            . '<div class="payment-note"><span>✓</span><p>Automatic gateway payments go first to the CourseHub platform merchant account. After verification, CourseHub creates lifetime access, calculates the commission and prepares each Instructor payout.</p></div>'
            . '<div class="panel-form"><label>Manual transaction reference<input type="text" name="transaction_id" minlength="3" maxlength="150" value="' . $e($values['transaction_id'] ?? '') . '" placeholder="Bank, eSewa or Khalti reference" required data-error="Enter the real transaction reference from the completed payment."></label>'
            . '<label>Payment screenshot or receipt<input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp,application/pdf" required data-payment-proof-input data-error="Upload the actual payment screenshot or PDF receipt."><small>Only needed for manual payment. Images must be at least 400 × 300 pixels and no larger than 8 MB.</small></label>'
            . '<div class="payment-proof-preview" data-payment-proof-preview><span>No receipt selected</span></div>'
            . '<label>Payment note<textarea name="note" rows="4" maxlength="1000" placeholder="Sender name, paid account or useful verification detail">' . $e($values['note'] ?? '') . '</textarea></label>'
            . '<div class="payment-note"><span>i</span><p>Manual payment remains pending until an Admin checks the amount, reference and uploaded receipt.</p></div>'
            . '<div class="form-actions"><a class="portal-button secondary" href="/student/cart">Return to My cart</a><button class="portal-button" type="submit" name="payment_method" value="manual">Submit manual proof</button></div></div></section>'
            . '<aside class="summary-card"><span>ORDER #' . $orderId . '</span>' . $itemList . '<div class="summary-row"><span>Original amount</span><strong>NPR ' . number_format((float) ($order['original_amount'] ?? 0), 2) . '</strong></div>'
            . '<div class="summary-row"><span>Discount</span><strong>− NPR ' . number_format((float) ($order['discount_amount'] ?? 0), 2) . '</strong></div><div class="summary-total"><span>Payable</span><strong>NPR ' . number_format($amount, 2) . '</strong></div>'
            . '<a class="portal-button secondary full" href="/student/payment-history">View payment history</a></aside></div></form>';

        return PortalPage::render('student', 'My cart · Payment', $alert . $flow . $form, '<a class="portal-button secondary" href="/student/payment-history">Payment history</a>');
    }
}
