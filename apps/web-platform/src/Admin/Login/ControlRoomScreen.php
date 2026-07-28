<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class ControlRoomScreen
{
    public static function render(string $action, string $error = '', string $identity = '', int $status = 200): Response
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $errorHtml = $error !== '' ? '<div class="control-error" role="alert">' . $e($error) . '</div>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="#0f151d"><title>Restricted administration | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/role-auth.css?v=20260728-1"></head><body class="control-body">'
            . '<main class="control-shell">'
            . '<section class="control-mark" aria-labelledby="control-heading"><div class="control-brand"><img class="coursehub-control-logo" src="/assets/images/coursehub-robot.svg" width="44" height="44" alt=""><div><strong>CourseHub</strong><span>Restricted administration</span></div></div>'
            . '<div class="control-intro"><span>PRIVATE CONTROL ENTRY</span><h1 id="control-heading">Administrative access<br>without a public doorway.</h1><p>This route stays outside public navigation and requires three independent checks before the administration workspace opens.</p></div>'
            . '<dl class="control-facts"><div><dt>Identity</dt><dd>Verified administrator account</dd></div><div><dt>Challenge</dt><dd>Separate restricted entry code</dd></div><div><dt>Session</dt><dd>Server-side role and session validation</dd></div></dl>'
            . '<div class="control-monitor"><i></i><span>Protected entry monitoring active</span></div></section>'
            . '<section class="control-form-section"><div class="control-terminal"><div class="control-terminal-badge"><span>ADMIN ONLY</span><b>03 checks required</b></div><header><span>SECURE ENTRY</span><h2>Authenticate control access</h2><p>Enter the credentials assigned to the restricted administration surface.</p></header>'
            . $errorHtml . '<form method="post" action="' . $e($action) . '">' . Csrf::field()
            . '<label><span>Administrator email</span><input type="email" name="control_identity" value="' . $e($identity) . '" autocomplete="username" inputmode="email" placeholder="admin@example.com" required></label>'
            . '<label><span>Account password</span><input type="password" name="control_secret" autocomplete="current-password" placeholder="Account password" required></label>'
            . '<label><span>Entry challenge</span><input type="password" name="control_entry_code" autocomplete="one-time-code" placeholder="Restricted entry code" required></label>'
            . '<button type="submit">Authenticate restricted access</button></form>'
            . '<footer><strong>Security note</strong><span>Access attempts are validated by the server and recorded by the platform.</span></footer></div></section>'
            . '</main><script src="/assets/js/role-auth.js?v=20260728-1" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
