<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class PublicContactPage
{
    public static function render(array $values = [], string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contact CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"></head>'
            . '<body><header class="house-header"><a href="/">CourseHub</a><nav><a href="/courses">Courses</a><a href="/about">About</a><a href="/faq">FAQ</a><a href="/learn/sign-in">Student sign in</a></nav></header>'
            . '<main class="form-shell contact-shell"><section class="form-intro"><span>COURSEHUB SUPPORT</span><h1>Tell us what needs attention.</h1><p>Questions about courses, payments, instructor applications or account access arrive in the administrator support queue.</p>'
            . '<div class="contact-points"><article><strong>Course support</strong><p>Ask about published courses, previews and lifetime access.</p></article><article><strong>Payment support</strong><p>Include the order or transaction reference when available.</p></article><article><strong>Instructor support</strong><p>Ask about applications, reviews and course publishing.</p></article></div></section>'
            . '<section class="form-card">' . $alert . '<form method="post" action="/contact">' . Csrf::field()
            . '<div class="form-columns"><label>Full name<input name="name" maxlength="100" value="' . $e($values['name'] ?? '') . '" required></label><label>Email address<input type="email" name="email" maxlength="150" value="' . $e($values['email'] ?? '') . '" required></label></div>'
            . '<label>Subject<input name="subject" maxlength="200" value="' . $e($values['subject'] ?? '') . '" placeholder="Payment, course, instructor application..."></label>'
            . '<label>Message<textarea name="message" rows="8" maxlength="10000" required>' . $e($values['message'] ?? '') . '</textarea></label>'
            . '<button type="submit">Send message to support</button></form><p class="form-foot">Do not include passwords, OTP codes, card details or private identity documents in this form.</p></section></main>'
            . '<footer class="catalog-footer"><a href="/">CourseHub</a><span>Support without mystery.</span></footer></body></html>';
        return Response::html($html);
    }
}
