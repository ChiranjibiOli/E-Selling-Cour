<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

final class PricingPage
{
    public static function render(): Response
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="description" content="Understand CourseHub course pricing, lifetime access, discounts, and manual payment verification."><title>Pricing | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Pricing/page.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">' . PublicNavbar::styles() . '</head>'
            . '<body class="info-body-page">' . PublicNavbar::render('') . '<main>'
            . '<section class="pricing-hero"><span>COURSEHUB PRICING</span><h1>Pay for the course you choose. Keep access for life.</h1><p>CourseHub does not use a platform-wide monthly subscription. Each published course has its own price, and a manually verified purchase unlocks only that course.</p><div class="pricing-actions"><a href="/courses">Browse course prices →</a><a href="/faq">Read payment FAQ</a></div></section>'
            . '<section class="pricing-model"><article class="pricing-featured"><span>THE STANDARD MODEL</span><h2>One-time course purchase</h2><strong>Course-specific price</strong><p>The exact amount is displayed on the course page. The server recalculates the price, discount, ownership and payable total during checkout.</p><ul><li>Lifetime access after Admin verification</li><li>Progress and purchased-course history</li><li>Verified review eligibility</li><li>Owned courses cannot be bought twice</li></ul><a href="/courses">Choose a course</a></article>'
            . '<div class="pricing-side"><article><span>FREE COURSES</span><h3>NPR 0</h3><p>A zero-total order creates a recorded enrollment without requiring a payment screenshot.</p></article><article><span>DISCOUNTS</span><h3>Validated server-side</h3><p>Coupons must be active, eligible, within their usage limit, and valid for the selected course.</p></article><article><span>REFUNDS</span><h3>Separate decision</h3><p>Removing access does not automatically create a refund. Financial reversal follows the published refund policy.</p></article></div></section>'
            . '<section class="pricing-flow"><div><span>MANUAL PAYMENT WORKFLOW</span><h2>Access begins after a real person verifies the payment proof.</h2></div><ol><li><b>01</b><strong>Choose</strong><span>Add a course not already owned to the Student cart.</span></li><li><b>02</b><strong>Calculate</strong><span>The server checks ownership, price and eligible discounts.</span></li><li><b>03</b><strong>Pay</strong><span>Pay manually using the displayed wallet or bank details.</span></li><li><b>04</b><strong>Upload</strong><span>Submit the real transaction reference and receipt.</span></li><li><b>05</b><strong>Verify</strong><span>Admin approval activates lifetime access and instructor earnings.</span></li></ol></section>'
            . '<section class="pricing-methods"><div class="pricing-section-heading"><span>PAYMENT METHOD</span><h2>Manual proof and Admin verification only.</h2></div><div class="pricing-method-grid"><article><b>Wallet or QR payment</b><p>Pay through the displayed eSewa, Khalti or other wallet account, then upload the receipt.</p><span class="available">Manual verification</span></article><article><b>Bank payment</b><p>Transfer to the displayed bank account and submit the transaction reference with a readable receipt.</p><span class="available">Manual verification</span></article><article><b>No automatic checkout</b><p>CourseHub does not initiate gateway checkout or activate enrollment from an automatic callback.</p><span class="configuration">Disabled</span></article></div></section>'
            . '<section class="pricing-clarity"><div><span>BEFORE YOU BUY</span><h2>Every course page should tell you what you are paying for.</h2></div><ul><li>Course title and instructor</li><li>Curriculum and preview lessons</li><li>Level, language, and duration</li><li>Current price and discount</li><li>Lifetime-access statement</li><li>Support and access-removal policy</li></ul></section>'
            . '<section class="pricing-closing"><div><span>START CAREFULLY</span><h2>Compare the course, then purchase with context.</h2></div><a href="/courses">Explore the catalogue →</a></section></main>'
            . '<footer class="pricing-footer"><a href="/">CourseHub</a><div><a href="/#promise">About</a><a href="/contact">Support</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a></div><span>One course. One verified purchase. Lifetime access.</span></footer>'
            . PublicNavbar::script() . '</body></html>';
        return Response::html($html);
    }
}
