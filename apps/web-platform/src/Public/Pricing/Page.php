<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

final class PricingPage
{
    public static function render(): Response
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="description" content="Understand CourseHub course pricing, lifetime access, automatic gateway verification and Instructor settlement."><title>Pricing | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Pricing/page.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">' . PublicNavbar::styles() . '</head>'
            . '<body class="info-body-page">' . PublicNavbar::render('') . '<main>'
            . '<section class="pricing-hero"><span>COURSEHUB PRICING</span><h1>Pay for the course you choose. Keep access for life.</h1><p>CourseHub does not use a platform-wide monthly subscription. Each published course has its own price, and a verified purchase unlocks only that course.</p><div class="pricing-actions"><a href="/courses">Browse course prices →</a><a href="/faq">Read payment FAQ</a></div></section>'
            . '<section class="pricing-model"><article class="pricing-featured"><span>THE STANDARD MODEL</span><h2>One-time course purchase</h2><strong>Course-specific price</strong><p>The server recalculates ownership, price, discount and payable total during checkout.</p><ul><li>Lifetime access after verified payment</li><li>Progress and purchased-course history</li><li>Verified review eligibility</li><li>Owned courses cannot be bought twice</li></ul><a href="/courses">Choose a course</a></article>'
            . '<div class="pricing-side"><article><span>FREE COURSES</span><h3>NPR 0</h3><p>A zero-total order creates a recorded enrollment without requiring a payment gateway.</p></article><article><span>DISCOUNTS</span><h3>Validated server-side</h3><p>Coupons must be active, eligible, within their usage limit, and valid for the selected course.</p></article><article><span>LIFETIME ACCESS</span><h3>No removal request</h3><p>A purchased enrollment remains in My Courses. Student access-removal requests are not part of the purchase workflow.</p></article></div></section>'
            . '<section class="pricing-flow"><div><span>AUTOMATIC PAYMENT WORKFLOW</span><h2>Access begins only after CourseHub verifies the provider.</h2></div><ol><li><b>01</b><strong>Choose</strong><span>Add a course not already owned to the Student cart.</span></li><li><b>02</b><strong>Calculate</strong><span>The server checks ownership, price and eligible discounts.</span></li><li><b>03</b><strong>Pay</strong><span>Use an enabled eSewa or Khalti merchant checkout, or manual proof.</span></li><li><b>04</b><strong>Verify</strong><span>CourseHub validates the provider status, amount and order reference.</span></li><li><b>05</b><strong>Settle</strong><span>Lifetime access activates, commission is recorded and the Instructor net amount enters payout.</span></li></ol></section>'
            . '<section class="pricing-methods"><div class="pricing-section-heading"><span>PAYMENT METHODS</span><h2>Automatic when merchant credentials are configured.</h2></div><div class="pricing-method-grid"><article><b>eSewa checkout</b><p>The Student pays the CourseHub merchant account. A signed response and server-side status check must confirm the payment.</p><span class="available">Automatic verification</span></article><article><b>Khalti checkout</b><p>CourseHub initiates the payment and verifies the returned pidx through the lookup API before providing access.</p><span class="available">Automatic verification</span></article><article><b>Manual fallback</b><p>A Student may submit a real transaction reference and receipt for Admin review when a gateway is unavailable.</p><span class="configuration">Admin verification</span></article></div></section>'
            . '<section class="pricing-clarity"><div><span>INSTRUCTOR SETTLEMENT</span><h2>The platform commission and Instructor share are recorded separately.</h2></div><ul><li>Student payment reaches the CourseHub merchant account</li><li>CourseHub calculates the configured commission</li><li>Instructor net earnings are linked to the exact order item</li><li>A configured payout API can transfer the net amount</li><li>Failed or unavailable transfers remain in the Admin payout queue</li><li>No payout is marked paid without a real provider reference</li></ul></section>'
            . '<section class="pricing-closing"><div><span>START CAREFULLY</span><h2>Compare the course, then purchase with context.</h2></div><a href="/courses">Explore the catalogue →</a></section></main>'
            . '<footer class="pricing-footer"><a href="/">CourseHub</a><div><a href="/#promise">About</a><a href="/contact">Support</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a></div><span>One course. One verified purchase. Lifetime access.</span></footer>'
            . PublicNavbar::script() . '</body></html>';
        return Response::html($html);
    }
}
