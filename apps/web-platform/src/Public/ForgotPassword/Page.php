<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class ForgotPasswordPage
{
    public static function render(array $values = [], string $message = '', bool $success = false, string $developmentResetUrl = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $localLink = $developmentResetUrl !== ''
            ? '<div class="form-alert success"><strong>Local development link</strong><br><a href="' . $e($developmentResetUrl) . '">Open password reset →</a><br><small>Disable ALLOW_LOCAL_RESET_TOKEN outside local development.</small></div>'
            : '';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Forgot password | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/AuthRecovery/page.css"></head>'
            . '<body class="recovery-body"><header class="recovery-header"><a href="/">CourseHub</a><nav><a href="/courses">Courses</a><a href="/learn/sign-in">Student</a><a href="/teach/studio-access">Instructor</a></nav></header>'
            . '<main class="recovery-shell"><section class="recovery-copy"><span>ACCOUNT RECOVERY</span><h1>Reset your password safely.</h1><p>Enter the email attached to your CourseHub account. The response stays deliberately generic so account addresses cannot be discovered through this form.</p><ul><li>Reset links expire after 30 minutes.</li><li>Older reset links become invalid.</li><li>Successful reset revokes existing sessions.</li></ul></section>'
            . '<section class="recovery-card">' . $alert . $localLink . '<span>PASSWORD RESET</span><h2>Find your account</h2><p>We will prepare instructions for an eligible account.</p><form method="post" action="/forgot-password">' . Csrf::field()
            . '<label>Email address<input type="email" name="email" value="' . $e($values['email'] ?? '') . '" maxlength="150" autocomplete="email" placeholder="name@example.com" required></label>'
            . '<button type="submit">Create reset instructions</button></form><p class="form-foot"><a href="/learn/sign-in">← Return to student sign in</a></p></section></main></body></html>';
        return Response::html($html);
    }
}
