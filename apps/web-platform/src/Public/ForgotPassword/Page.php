<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

final class ForgotPasswordPage
{
    public static function render(array $values = [], string $message = '', bool $success = false): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f0e5">'
            . '<title>Student password recovery | CourseHub</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/AuthRecovery/page.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">' . PublicNavbar::styles() . '</head>'
            . '<body class="recovery-body">' . PublicNavbar::render('login')
            . '<main class="recovery-shell"><section class="recovery-copy"><span>STUDENT ACCOUNT RECOVERY</span><h1>Reset your password safely.</h1><p>Enter the Gmail address attached to your Student account. CourseHub will send a six-digit code to that exact address.</p><ul><li>The code expires after 10 minutes.</li><li>Only the newest code remains valid.</li><li>A successful reset signs out older sessions.</li></ul></section>'
            . '<section class="recovery-card">' . $alert . '<span>PASSWORD RESET</span><h2>Send recovery code</h2><p>This recovery page is only for Student accounts.</p><form method="post" action="/forgot-password">' . Csrf::field()
            . '<label>Student Gmail address<input type="email" name="email" value="' . $e($values['email'] ?? '') . '" maxlength="150" autocomplete="email" placeholder="yourname@gmail.com" required></label>'
            . '<button type="submit">Send six-digit code</button></form><p class="form-foot"><a href="/learn/sign-in">← Return to Student sign in</a></p></section></main>'
            . PublicNavbar::script() . '</body></html>';
        return Response::html($html);
    }
}
