<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Session\AuthSession;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentCheckoutPage
{
    public static function render(array $cart, string $message = '', bool $success = true, array $values = []): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $items = is_array($cart['items'] ?? null) ? $cart['items'] : [];
        $subtotal = (float) ($cart['subtotal'] ?? 0);
        $user = AuthSession::user();
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $orderItems = '';
        foreach ($items as $item) {
            $orderItems .= '<div class="order-mini"><i>CH</i><div><strong>' . $e($item['title'] ?? 'Course') . '</strong><small>'
                . $e($item['instructor_name'] ?? 'CourseHub instructor') . ' · Lifetime access</small></div><b>NPR '
                . number_format((float) ($item['price'] ?? 0), 2) . '</b></div>';
        }
        if ($orderItems === '') {
            $orderItems = '<div class="rich-empty"><h3>No course selected</h3><p>Return to the Student catalogue and add a published course before checking out.</p><a class="portal-button secondary" href="/student/courses">Browse courses</a></div>';
        }

        $form = '<form method="post" action="/student/checkout">' . Csrf::field()
            . '<div class="checkout-layout"><section class="data-card"><div class="stepper"><span class="done"><i>1</i>Cart</span><b></b><span class="active"><i>2</i>Checkout</span><b></b><span><i>3</i>Manual proof</span></div>'
            . '<div class="data-card-head"><div><span>ACCOUNT DETAILS</span><h3>Confirm the order owner</h3></div><span class="secure-pill">Authenticated Student</span></div>'
            . '<div class="panel-form"><div class="field-grid"><label>Full name<input value="' . $e($user['name'] ?? '') . '" disabled></label><label>Email address<input value="' . $e($user['email'] ?? '') . '" disabled></label></div>'
            . '<label>Coupon code<input name="coupon_code" maxlength="50" value="' . $e($values['coupon_code'] ?? '') . '" placeholder="Optional code"></label>'
            . '<div class="payment-note"><span>i</span><p>Course availability, ownership, coupon eligibility and every price are checked again by the server. A course already in My Courses cannot be ordered again.</p></div>'
            . '<label class="check-line"><input type="checkbox" required> I confirm that this order is for the listed courses and lifetime access.</label>'
            . '<div class="form-actions"><a class="portal-button secondary" href="/student/cart">Back to cart</a>'
            . (count($items) > 0 ? '<button class="portal-button" type="submit">Create order for manual payment →</button>' : '') . '</div></div></section>'
            . '<aside class="summary-card"><span>YOUR ORDER</span>' . $orderItems . '<div class="summary-row"><span>Subtotal</span><strong>NPR ' . number_format($subtotal, 2) . '</strong></div>'
            . '<div class="summary-row"><span>Coupon discount</span><strong>Validated on submit</strong></div><div class="summary-total"><span>Maximum payable</span><strong>NPR ' . number_format($subtotal, 2) . '</strong></div>'
            . '<p class="muted-copy">After the order is created, pay manually using the displayed wallet or bank details, then upload the real receipt for Admin verification.</p></aside></div></form>';

        return PortalPage::render('student', 'Secure checkout', $alert . $form);
    }
}
