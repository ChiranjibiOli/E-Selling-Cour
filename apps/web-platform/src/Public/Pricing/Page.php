<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

final class PricingPage
{
    public static function render(): Response
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="description" content="Understand CourseHub course pricing, lifetime access, discounts, and payment verification."><title>Pricing | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Pricing/page.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">' . PublicNavbar::styles() . '</head>'
            . '<body class="info-body-page">' . PublicNavbar::render('') . '<main>'
            . '<section class="pricing-hero"><span>COURSEHUB PRICING</span><h1>Pay for the course you choose. Keep access for life.</h1><p>CourseHub does not use a platform-wide monthly subscription. Each published course has its own price, and a verified purchase unlocks only that course.</p><div class="pricing-actions"><a href="/courses">Browse course prices →</a><a href="/faq">Read payment FAQ</a></div></section>'
            . '<section class="pricing-model"><article class="pricing-featured"><span>THE STANDARD MODEL</span><h2>One-time course purchase</h2><strong>Course-specific price</strong><p>The exact amount is displayed on the course page. The server recalculates the price, discount, and payable total during checkout.</p><ul><li>Lifetime access after verified payment</li><li>Progress and purchased-course history</li><li>Verified review eligibility</li><li>No unrelated courses unlocked</li></ul><a href="/courses">Choose a course</a></article>'
            . '<div class="pricing-side"><article><span>FREE COURSES</span><h3>NPR 0</h3><p>A zero-total order creates a recorded enrollment without requiring a fake payment screenshot.</p></article><article><span>DISCOUNTS</span><h3>Validated server-side</h3><p>Coupons must be active, eligible, within their usage limit, and valid for the selected course.</p></article><article><span>REFUNDS</span><h3>Separate decision</h3><p>Removing access does not automatically create a refund. Financial reversal follows the published refund policy.</p></article></div></section>'
            . '<section class="pricing-flow"><div><span>PAYMENT WORKFLOW</span><h2>Access begins after verification, not after a browser celebrates.</h2></div><ol><li><b>01</b><strong>Choose</strong><span>Add a published course to the student cart.</span></li><li><b>02</b><strong>Calculate</strong><span>The server calculates price and eligible discounts.</span></li><li><b>03</b><strong>Pay</strong><span>Use an available manual or configured gateway method.</span></li><li><b>04</b><strong>Verify</strong><span>The transaction is checked against the order.</span></li><li><b>05</b><strong>Enroll</strong><span>Lifetime course access and instructor earnings are recorded atomically.</span></li></ol></section>'
            . '<section class="pricing-methods"><div class="pricing-section-heading"><span>PAYMENT METHODS</span><h2>Designed for Nepal, enabled only when properly configured.</h2></div><div class="pricing-method-grid"><article><b>Manual payment</b><p>Students submit a transaction reference and private proof. An administrator verifies it before enrollment.</p><span class="available">Workflow available</span></article><article><b>eSewa</b><p>Automatic gateway access requires merchant credentials, signed callbacks, transaction lookup, and webhook verification.</p><span class="configuration">Credentials required</span></article><article><b>Khalti</b><p>Automatic gateway access requires production keys, amount verification, callbacks, and idempotent enrollment.</p><span class="configuration">Credentials required</span></article></div></section>'
            . '<section class="pricing-clarity"><div><span>BEFORE YOU BUY</span><h2>Every course page should tell you what you are paying for.</h2></div><ul><li>Course title and instructor</li><li>Curriculum and preview lessons</li><li>Level, language, and duration</li><li>Current price and discount</li><li>Lifetime-access statement</li><li>Support and access-removal policy</li></ul></section>'
            . '<section class="pricing-closing"><div><span>START CAREFULLY</span><h2>Compare the course, then purchase with context.</h2></div><a href="/courses">Explore the catalogue →</a></section></main>'
            . '<footer class="pricing-footer"><a href="/">CourseHub</a><div><a href="/#promise">About</a><a href="/contact">Support</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a></div><span>One course. One verified purchase. Lifetime access.</span></footer>'
            . PublicNavbar::script() . '</body></html>';
        return Response::html($html);
    }
}
