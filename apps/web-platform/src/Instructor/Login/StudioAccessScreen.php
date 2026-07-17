<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class StudioAccessScreen
{
    public static function render(string $error = '', string $email = '', int $status = 200): Response
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $errorHtml = $error !== '' ? '<aside class="studio-error">' . $e($error) . '</aside>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Instructor studio access | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css">'
            . '<link rel="stylesheet" href="/room-assets/Instructor/Login/page.css"></head><body class="studio-access-body">'
            . '<header class="studio-top"><a href="/">CourseHub</a><a href="/register/instructor">Apply as instructor</a></header>'
            . '<main class="studio-access-grid"><section class="studio-panel"><span>INSTRUCTOR STUDIO</span><h1>Build courses.<br>Teach clearly.<br>Track every sale.</h1>'
            . '<ul><li>Draft and curriculum workspace</li><li>Approval workflow</li><li>Students, earnings and withdrawals</li></ul></section>'
            . '<section class="studio-form-wrap"><div class="studio-form-card"><p class="studio-label">APPROVED INSTRUCTORS</p><h2>Open your studio</h2>'
            . '<p>Pending applications cannot enter until an administrator approves the instructor account.</p>' . $errorHtml
            . '<form method="post" action="/teach/studio-access">' . Csrf::field()
            . '<label>Studio email<input type="email" name="studio_email" value="' . $e($email) . '" autocomplete="email" required></label>'
            . '<label>Studio password<input type="password" name="studio_password" autocomplete="current-password" required></label>'
            . '<button type="submit">Open instructor studio</button></form>'
            . '<a href="/forgot-password">Recover studio access</a></div></section></main>'
            . '<script src="/room-assets/Instructor/Login/page.js" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
