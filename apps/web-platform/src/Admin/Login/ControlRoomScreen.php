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
            . '<meta name="robots" content="noindex,nofollow"><title>Restricted control entry</title>'
            . '<link rel="stylesheet" href="/room-assets/Admin/Login/page.css"></head><body class="control-body">'
            . '<main class="control-shell"><section class="control-mark"><span>COURSEHUB</span><strong>CONTROL<br>ROOM</strong><small>Restricted administration surface</small></section>'
            . '<section class="control-terminal"><div class="control-status"><i></i> SECURE ENTRY</div><h1>Administrator challenge</h1>'
            . '<p>This entrance is intentionally absent from public navigation. Access still requires an administrator account, entry code, valid session and server-side role verification.</p>'
            . $errorHtml . '<form method="post" action="' . $e($action) . '">' . Csrf::field()
            . '<label>Control identity<input type="email" name="control_identity" value="' . $e($identity) . '" autocomplete="username" required></label>'
            . '<label>Account secret<input type="password" name="control_secret" autocomplete="current-password" required></label>'
            . '<label>Entry challenge<input type="password" name="control_entry_code" autocomplete="one-time-code" required></label>'
            . '<button type="submit">Authenticate control access</button></form></section></main>'
            . '<script src="/room-assets/Admin/Login/page.js" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
