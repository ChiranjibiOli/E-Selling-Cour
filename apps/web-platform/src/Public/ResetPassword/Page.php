<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class ResetPasswordPage
{
    public static function render(array $values = [], string $message = '', bool $success = false): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $token = (string) ($values['token'] ?? '');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $form = $success
            ? '<a class="recovery-button" href="/learn/sign-in">Sign in with the new password →</a>'
            : '<form method="post" action="/reset-password">' . Csrf::field()
                . '<input type="hidden" name="token" value="' . $e($token) . '">'
                . '<label>New password<input type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required></label>'
                . '<label>Confirm new password<input type="password" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" required></label>'
                . '<button type="submit"' . ($token === '' ? ' disabled' : '') . '>Change Student password</button></form>';
        $missing = !$success && $token === '' ? '<div class="form-alert error">This page requires a valid reset token. Start again from Student password recovery.</div>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f0e5">'
            . '<title>Reset Student password | CourseHub</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/AuthRecovery/page.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1"></head>'
            . '<body class="recovery-body"><header class="public-site-nav" data-public-site-nav><a class="public-site-brand" href="/" aria-label="CourseHub home"><img src="/assets/images/coursehub-robot.svg" alt=""><strong>CourseHub</strong></a>'
            . '<button class="public-site-menu" type="button" data-public-site-menu aria-label="Open navigation" aria-expanded="false"><span></span><span></span></button>'
            . '<nav class="public-site-links" aria-label="Public navigation"><a href="/">Home</a><a href="/courses">Courses</a><a href="/#categories">Categories</a><a href="/about">About</a><a href="/contact">Contact</a></nav>'
            . '<div class="public-site-account"><a class="public-login active" href="/learn/sign-in">Log in</a><a class="public-create" href="/register/student">Create account</a></div></header>'
            . '<main class="recovery-shell"><section class="recovery-copy"><span>STUDENT SECURE RESET</span><h1>Choose a new private password.</h1><p>This recovery page is limited to Student accounts. New recovery requests use a six-digit Gmail code.</p><ul><li>Use at least eight characters.</li><li>Do not reuse a password from another service.</li><li>Password values are hashed before storage.</li></ul></section>'
            . '<section class="recovery-card">' . $alert . $missing . '<span>NEW STUDENT PASSWORD</span><h2>Complete account recovery</h2><p>Enter and confirm your new password.</p>' . $form
            . '<p class="form-foot"><a href="/forgot-password">Request a Gmail reset code</a></p></section></main>'
            . '<script src="/assets/js/public-site.js?v=20260728-1" defer></script></body></html>';
        return Response::html($html);
    }
}
