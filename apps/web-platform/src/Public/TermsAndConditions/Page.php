<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PublicInformationPage;

final class TermsAndConditionsPage
{
    public static function render(): Response
    {
        return PublicInformationPage::render(
            'Terms and conditions',
            'COURSEHUB TERMS',
            'These terms describe the intended operating rules for CourseHub Students, Instructors, administrators, course purchases, learning access, and platform content.',
            [
                ['title' => 'Account responsibilities', 'body' => '<p>Users must provide accurate information, protect their credentials, use the correct portal, and notify CourseHub if account access appears compromised. Accounts and purchased access may not be resold or shared.</p>'],
                ['title' => 'Course purchases and access', 'body' => '<p>Each purchase applies only to the identified course. Lifetime access begins after payment is verified. CourseHub does not provide a Student access-removal request after purchase. Refunds, fraud responses and exceptional lawful restrictions use separate recorded processes.</p>'],
                ['title' => 'Prices, coupons, and payments', 'body' => '<p>The authoritative amount is calculated by the server at checkout. Coupons may be limited by dates, courses, usage, minimum totals, and maximum discounts. Payment access is not granted merely because a browser displays a success page; CourseHub must verify the provider or Admin-approved proof.</p>'],
                ['title' => 'Automatic payment gateways', 'body' => '<p>Enabled gateways collect payment through the CourseHub platform merchant account. CourseHub verifies the provider status, amount and order reference before creating lifetime enrollment. Manual payment proof may remain available as a fallback.</p>'],
                ['title' => 'Refunds and financial reversals', 'body' => '<p>A refund, chargeback or provider reversal is a separate financial decision. It may require adjustment of enrollment, commission and Instructor earnings, and must remain linked to the original order and payment record.</p>'],
                ['title' => 'Student conduct', 'body' => '<ul><li>Do not copy, redistribute, scrape, sell, or publicly repost protected course content.</li><li>Do not attack, bypass, overload, or misuse the platform.</li><li>Do not submit fraudulent payment proof, reviews, or identity information.</li><li>Keep questions, reviews, and support communication lawful and respectful.</li></ul>'],
                ['title' => 'Instructor eligibility', 'body' => '<p>Instructor studio access requires an approved application. Approval may depend on identity, expertise, experience, subject suitability, and agreement to platform, copyright, content-quality, payment, and conduct rules.</p>'],
                ['title' => 'Instructor course obligations', 'body' => '<p>Instructors must own or be authorized to use all submitted material, describe courses accurately, maintain professional quality, avoid harmful or unlawful instruction, and submit significant published-course changes for review.</p>'],
                ['title' => 'Reviews and moderation', 'body' => '<p>Only verified enrolled Students may review a course. CourseHub may hide unlawful, abusive, fraudulent, irrelevant, or privacy-violating content. Instructors may not remove legitimate criticism merely because it is inconvenient.</p>'],
                ['title' => 'Earnings and payouts', 'body' => '<p>Instructor earnings are calculated from verified paid order items after platform commission, refunds, reversals and adjustments. CourseHub may submit the net amount to a configured provider-approved payout API. A payout is not treated as paid until a real provider transaction reference is recorded; otherwise it remains queued for Admin settlement.</p>'],
                ['title' => 'Suspension and termination', 'body' => '<p>CourseHub may block or restrict accounts, courses, payments, enrollments, or payouts for fraud, abuse, legal obligations, security risks, intellectual-property violations, or serious breaches of these terms. Historical business records may be retained.</p>'],
                ['title' => 'Platform availability and liability', 'body' => '<p>CourseHub should maintain reasonable operational safeguards, but uninterrupted availability cannot be guaranteed. Production terms must define jurisdiction-appropriate limitations, dispute handling, warranties, and liability before commercial launch.</p>'],
                ['title' => 'Changes and contact', 'body' => '<p>Terms may change as the platform, payment methods, laws, and business policies evolve. Material changes should be communicated appropriately. Questions can be sent through <a href="/contact">CourseHub support</a>.</p>'],
            ],
            'This repository text is a product-policy draft. Obtain qualified legal review before accepting real commercial transactions.',
        );
    }
}
