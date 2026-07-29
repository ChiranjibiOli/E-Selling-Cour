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

        $paymentBrandStyles = '<style>'
            . '.payment-provider-logo{width:64px!important;height:34px!important;padding:4px 7px!important;display:inline-flex!important;align-items:center!important;justify-content:flex-start!important;gap:5px!important;flex:0 0 64px!important;background:#fff!important;border:1px solid!important;border-radius:10px!important;box-shadow:0 5px 14px rgba(16,25,54,.08)!important;font-style:normal!important;line-height:1!important}'
            . '.payment-provider-logo .provider-symbol{width:23px;height:23px;display:grid;place-items:center;border-radius:7px;color:#fff;font-size:.78rem;font-weight:950;letter-spacing:-.04em}'
            . '.payment-provider-logo .provider-word{font-size:.66rem;font-weight:900;letter-spacing:-.035em;white-space:nowrap}'
            . '.payment-provider-logo.provider-esewa{color:#2f8f46!important;border-color:rgba(47,143,70,.24)!important}'
            . '.payment-provider-logo.provider-esewa .provider-symbol{background:#49a942}'
            . '.payment-provider-logo.provider-khalti{color:#5c2d91!important;border-color:rgba(92,45,145,.24)!important}'
            . '.payment-provider-logo.provider-khalti .provider-symbol{background:#5c2d91}'
            . '.payment-provider-logo.provider-manual{color:#315eea!important;border-color:rgba(49,94,234,.22)!important}'
            . '.payment-provider-logo.provider-manual .provider-symbol{background:#315eea;font-size:.58rem;letter-spacing:.01em}'
            . '@media(max-width:720px){.payment-provider-logo{width:58px!important;flex-basis:58px!important}.payment-provider-logo .provider-word{display:none}}'
            . '</style>';

        $gatewayButton = static function (string $provider, array $state) use ($e): string {
            $available = ($state['available'] ?? false) === true;
            $providerName = $provider === 'esewa' ? 'eSewa' : 'Khalti';
            $symbol = $provider === 'esewa' ? 'e' : 'K';
            $copy = $available
                ? 'Pay securely with ' . $providerName
                : (($state['configured'] ?? false) === true ? 'Temporarily unavailable' : 'Merchant connection is not configured');
            $logo = '<i class="payment-provider-logo provider-' . $e($provider) . '" aria-hidden="true"><span class="provider-symbol">' . $e($symbol) . '</span><span class="provider-word">' . $e($providerName) . '</span></i>';

            return '<button class="payment-method" type="' . ($available ? 'submit' : 'button') . '" name="payment_method" value="' . $e($provider) . '"'
                . ($available ? ' formnovalidate' : ' disabled') . '>' . $logo . '<span><strong>' . $e($providerName) . '</strong><small>' . $e($copy) . '</small></span><b>' . ($available ? '→' : '×') . '</b></button>';
        };

        $esewa = is_array($options['esewa'] ?? null) ? $options['esewa'] : [];
        $khalti = is_array($options['khalti'] ?? null) ? $options['khalti'] : [];

        $manualLogo = '<i class="payment-provider-logo provider-manual" aria-hidden="true"><span class="provider-symbol">QR</span><span class="provider-word">Manual</span></i>';
        $form = $paymentBrandStyles . '<form method="post" action="/student/payment?order=' . $orderId . '" enctype="multipart/form-data" novalidate data-payment-proof-form>' . Csrf::field() . '<input type="hidden" name="order_id" value="' . $orderId . '">'
            . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>FINAL PAYMENT STEP</span><h3>Pay order #' . $orderId . '</h3><p>Complete payment here, then follow verification in Payment history.</p></div><span class="secure-pill">NPR ' . number_format($amount, 2) . '</span></div>'
            . '<div class="payment-methods"><button class="payment-method active" type="button">' . $manualLogo . '<span><strong>Manual payment</strong><small>Upload receipt for Admin verification</small></span><b>✓</b></button>'
            . $gatewayButton('esewa', $esewa) . $gatewayButton('khalti', $khalti) . '</div>'
            . '<div class="payment-note"><span>✓</span><p>Complete your payment securely using your preferred method.</p></div>'
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
