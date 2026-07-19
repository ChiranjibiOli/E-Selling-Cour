<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentCartPage
{
    public static function render(array $cart, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $items = is_array($cart['items'] ?? null) ? $cart['items'] : [];
        $subtotal = (float) ($cart['subtotal'] ?? 0);
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $list = '';

        foreach ($items as $item) {
            $courseId = (int) ($item['course_id'] ?? 0);
            $image = trim((string) ($item['thumbnail_url'] ?? ''));
            $media = $image !== '' ? '<img src="' . $e($image) . '" alt="" loading="lazy">' : '<span>CH</span>';
            $list .= '<article class="cart-course-row"><div class="cart-course-media">' . $media . '</div><div class="cart-course-copy">'
                . '<span>' . $e($item['category_name'] ?? 'Course') . '</span><h3>' . $e($item['title'] ?? 'Untitled course') . '</h3>'
                . '<p>By ' . $e($item['instructor_name'] ?? 'CourseHub instructor') . ' · ' . $e(ucfirst((string) ($item['level'] ?? 'beginner'))) . ' · Lifetime access</p></div>'
                . '<strong>NPR ' . number_format((float) ($item['price'] ?? 0), 2) . '</strong>'
                . '<form method="post" action="/student/cart">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . $courseId . '">'
                . '<button class="text-button danger-text" name="action" value="remove" type="submit">Remove</button></form></article>';
        }

        if ($list === '') {
            $list = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>Your cart is empty</h3><p>Add a published course, compare the final server price and continue when you are ready.</p><a class="portal-button secondary" href="/courses">Explore courses</a></div>';
        }

        $content = $alert
            . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Courses in cart</span><i></i></div><strong>' . count($items) . '</strong><small>Published and available</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Current subtotal</span><i></i></div><strong>NPR ' . number_format($subtotal, 2) . '</strong><small>Calculated by the server</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Access type</span><i></i></div><strong>Lifetime</strong><small>Per purchased course</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Pricing</span><i></i></div><strong>Protected</strong><small>Browser totals are never trusted</small></article></section>'
            . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>CART ITEMS</span><h3>Your selected courses</h3></div><a class="text-button" href="/courses">Browse catalog</a></div><div class="cart-course-list">' . $list . '</div></section>'
            . '<aside class="summary-card accent-card"><span>ORDER SUMMARY</span><div class="summary-row"><span>Subtotal</span><strong>NPR ' . number_format($subtotal, 2) . '</strong></div>'
            . '<div class="summary-row"><span>Discount</span><strong>Calculated at checkout</strong></div><div class="summary-total"><span>Current total</span><strong>NPR ' . number_format($subtotal, 2) . '</strong></div>'
            . (count($items) > 0 ? '<a class="portal-button full" href="/student/checkout">Continue to secure checkout</a>' : '<a class="portal-button secondary full" href="/courses">Choose a course</a>')
            . '<p class="muted-copy">The checkout service reloads every course and price from the database before creating an order.</p></aside></div>';

        return PortalPage::render('student', 'My cart', $content, '<a class="portal-button secondary" href="/courses">+ Add another course</a>');
    }
}
