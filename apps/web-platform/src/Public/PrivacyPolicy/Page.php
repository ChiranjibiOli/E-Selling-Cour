<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PublicInformationPage;

final class PrivacyPolicyPage
{
    public static function render(): Response
    {
        return PublicInformationPage::render(
            'Privacy policy',
            'COURSEHUB PRIVACY',
            'This page explains the categories of information CourseHub needs to operate accounts, teaching applications, purchases, learning access, and support.',
            [
                ['title' => 'Information we collect', 'body' => '<p>Depending on your role, CourseHub may collect your name, email, phone, profile details, login-security records, course activity, orders, payments, reviews, support messages, and instructor application information.</p>'],
                ['title' => 'Instructor identity information', 'body' => '<p>Instructor applications may include a personal photo and citizenship, passport, or other identity document. These files must be stored outside the public web root and accessed only by authorized reviewers.</p>'],
                ['title' => 'How information is used', 'body' => '<ul><li>Create and secure accounts.</li><li>Review instructors and courses.</li><li>Calculate orders and verify payments.</li><li>Grant and protect course access.</li><li>Track progress, reviews, earnings, and payouts.</li><li>Respond to support and security events.</li></ul>'],
                ['title' => 'Payments and financial records', 'body' => '<p>CourseHub stores order and transaction records needed for payment verification, enrollment, earnings, refunds, and accounting. Full payment-card credentials should be handled by authorized payment providers rather than stored by CourseHub.</p>'],
                ['title' => 'Cookies and sessions', 'body' => '<p>Authentication uses a session cookie and an opaque server-verified access token. Session cookies should use HttpOnly, SameSite protection, and Secure mode when served over HTTPS.</p>'],
                ['title' => 'Data sharing', 'body' => '<p>Information should be shared only with service providers needed to operate the platform, such as payment gateways, email delivery, hosting, private media storage, and approved legal or security processes. CourseHub should not sell personal information.</p>'],
                ['title' => 'Retention and deletion', 'body' => '<p>Account information may be removed when no longer required, but financial, enrollment, payout, audit, fraud-prevention, and legal records may need longer retention. Deleting a user interface record must not silently destroy required business history.</p>'],
                ['title' => 'Security practices', 'body' => '<p>CourseHub uses role checks, ownership checks, parameterized database queries, CSRF protection, login throttling, hashed passwords, hashed session tokens, private upload storage, and restricted admin workflows. No system is risk-free, so security controls require ongoing review.</p>'],
                ['title' => 'Your choices', 'body' => '<p>Users may update eligible profile information, change passwords, review purchase and access records, submit support requests, and request legally available data correction or deletion through <a href="/contact">CourseHub support</a>.</p>'],
                ['title' => 'Policy updates', 'body' => '<p>This policy should be reviewed before production launch by the platform owner and qualified legal counsel for Nepal and any other jurisdiction where CourseHub operates.</p>'],
            ],
            'This repository text is an operational draft, not a substitute for jurisdiction-specific legal review before launch.',
        );
    }
}
