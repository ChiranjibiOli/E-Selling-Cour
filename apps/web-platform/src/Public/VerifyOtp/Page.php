<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

final class VerifyOtpPage
{
    public static function render(
        string $purpose,
        string $email,
        array $values = [],
        string $message = '',
        bool $success = false,
        string $developmentCode = '',
        int $status = 200,
    ): Response {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $passwordReset = $purpose === 'password_reset';
        $title = $passwordReset ? 'Reset Student password' : 'Verify Student Gmail';
        $eyebrow = $passwordReset ? 'STUDENT PASSWORD RESET' : 'STUDENT EMAIL VERIFICATION';
        $intro = $passwordReset
            ? 'Enter the code sent to your Gmail account and choose a new password.'
            : 'Enter the code sent to your Gmail account to activate your Student account.';
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $dev = $developmentCode !== ''
            ? '<div class="form-alert success"><strong>Local development code:</strong> ' . $e($developmentCode) . '<br><small>This appears only when ALLOW_LOCAL_EMAIL_CODE is enabled.</small></div>'
            : '';

        if ($success) {
            $content = $alert . '<h2>' . $e($title) . '</h2><p>Your Student account is ready.</p><a class="portal-button" href="/learn/sign-in">Continue to Student sign in</a>';
        } else {
            $passwordFields = $passwordReset
                ? '<label>New password<input type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required></label>'
                    . '<label>Confirm new password<input type="password" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" required></label>'
                : '';
            $content = $alert . $dev . '<h2>' . $e($title) . '</h2><p>' . $e($intro) . '</p>'
                . '<form method="post" action="/verify-otp">' . Csrf::field()
                . '<input type="hidden" name="purpose" value="' . $e($purpose) . '">'
                . '<label>Student Gmail address<input type="email" name="email" value="' . $e($email) . '" autocomplete="email" readonly required></label>'
                . '<label>Six-digit verification code<input inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="code" value="' . $e($values['code'] ?? '') . '" autocomplete="one-time-code" placeholder="000000" required></label>'
                . $passwordFields
                . '<button type="submit">' . ($passwordReset ? 'Reset Student password' : 'Verify and activate account') . '</button></form>'
                . ($passwordReset
                    ? '<p class="form-foot"><a href="/forgot-password">Send another recovery code</a></p>'
                    : '<form method="post" action="/verify-otp" class="form-foot">' . Csrf::field()
                        . '<input type="hidden" name="purpose" value="registration"><input type="hidden" name="operation" value="resend">'
                        . '<input type="hidden" name="email" value="' . $e($email) . '"><button type="submit">Send another verification code</button></form>');
        }

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f0e5">'
            . '<title>' . $e($title) . ' | CourseHub</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/AuthRecovery/page.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">' . PublicNavbar::styles() . '</head>'
            . '<body class="recovery-body">' . PublicNavbar::render('login')
            . '<main class="recovery-shell"><section class="recovery-copy"><span>' . $e($eyebrow) . '</span><h1>Confirm that the Gmail account belongs to you.</h1><p>Codes are single-use, expire quickly and are stored only as secure hashes.</p></section>'
            . '<section class="recovery-card">' . $content . '</section></main>'
            . PublicNavbar::script() . '</body></html>';

        return Response::html($html, $status);
    }
}
