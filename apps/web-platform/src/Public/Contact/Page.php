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
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Contact CourseHub support about courses, payments or account access."><title>Contact CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/public-unified.css?v=20260728-1"></head>'
            . '<body class="public-contact-body"><header class="public-nav" data-public-nav><a class="public-brand" href="/" aria-label="CourseHub home"><span class="public-brand-mark"><img src="/assets/images/coursehub-robot.svg" alt=""></span><strong>CourseHub</strong></a>'
            . '<button class="public-menu" type="button" aria-label="Open navigation" aria-expanded="false" data-public-menu><span></span><span></span></button>'
            . '<nav class="public-links" aria-label="Primary navigation" data-public-links><a href="/">Home</a><a href="/courses">Courses</a><a href="/#categories">Categories</a><a href="/about">About</a><a class="active" href="/contact">Contact</a></nav>'
            . '<div class="public-account"><a class="public-login" href="/login">Log in</a><a class="public-create" href="/register/student">Create account</a></div></header>'
            . '<main class="form-shell contact-shell"><section class="form-intro"><span>COURSEHUB SUPPORT</span><h1>Tell us what needs attention.</h1><p>Questions about courses, payments, account access or publishing arrive in the administrator support queue.</p>'
            . '<div class="contact-points"><article><strong>Course support</strong><p>Ask about published courses, previews and lifetime access.</p></article><article><strong>Payment support</strong><p>Include the order or transaction reference when available.</p></article><article><strong>Account support</strong><p>Explain the login, registration or access problem without sharing private credentials.</p></article></div></section>'
            . '<section class="form-card">' . $alert . '<form method="post" action="/contact">' . Csrf::field()
            . '<div class="form-columns"><label>Full name<input name="name" maxlength="100" value="' . $e($values['name'] ?? '') . '" autocomplete="name" required></label><label>Email address<input type="email" name="email" maxlength="150" value="' . $e($values['email'] ?? '') . '" autocomplete="email" required></label></div>'
            . '<label>Subject<input name="subject" maxlength="200" value="' . $e($values['subject'] ?? '') . '" placeholder="Payment, course or account access"></label>'
            . '<label>Message<textarea name="message" rows="8" maxlength="10000" required>' . $e($values['message'] ?? '') . '</textarea></label>'
            . '<button type="submit">Send message to support</button></form><p class="form-foot">Do not include passwords, OTP codes, card details or private identity documents in this form.</p></section></main>'
            . '<footer class="catalog-footer"><a href="/">CourseHub</a><span>Support without mystery.</span></footer><script src="/assets/js/public-nav.js?v=20260728-1" defer></script></body></html>';
        return Response::html($html);
    }
}
