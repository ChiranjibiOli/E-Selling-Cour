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
                . '<button type="submit"' . ($token === '' ? ' disabled' : '') . '>Change password</button></form>';
        $missing = !$success && $token === '' ? '<div class="form-alert error">This page requires a valid reset token. Start again from Forgot password.</div>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Reset password | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/AuthRecovery/page.css"></head>'
            . '<body class="recovery-body"><header class="recovery-header"><a href="/">CourseHub</a><nav><a href="/courses">Courses</a><a href="/learn/sign-in">Student</a><a href="/teach/studio-access">Instructor</a></nav></header>'
            . '<main class="recovery-shell"><section class="recovery-copy"><span>SECURE RESET</span><h1>Choose a new private password.</h1><p>The reset token is single-use, expires automatically, and revokes existing sessions after a successful password change.</p><ul><li>Use at least eight characters.</li><li>Do not reuse a password from another service.</li><li>Password values are hashed before storage.</li></ul></section>'
            . '<section class="recovery-card">' . $alert . $missing . '<span>NEW PASSWORD</span><h2>Complete account recovery</h2><p>Enter and confirm your new password.</p>' . $form
            . '<p class="form-foot"><a href="/forgot-password">Request another reset link</a></p></section></main></body></html>';
        return Response::html($html);
    }
}
