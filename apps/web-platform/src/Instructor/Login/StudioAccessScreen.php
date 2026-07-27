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
            . '<meta name="theme-color" content="#f4ede2"><title>Instructor access | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css">'
            . '<link rel="stylesheet" href="/room-assets/Instructor/Login/page.css"><link rel="stylesheet" href="/assets/css/coursehub-editorial.css"></head><body class="studio-access-body">'
            . '<header class="studio-top"><a class="coursehub-auth-brand" href="/"><img src="/assets/images/coursehub-robot.svg" alt=""><span>CourseHub</span></a><a href="/register/instructor">Create Instructor account</a></header>'
            . '<main class="studio-access-grid"><section class="studio-panel"><span>SEPARATE INSTRUCTOR ACCESS</span><h1>Build courses.<br>Teach clearly.<br>Track every sale.</h1>'
            . '<ul><li>Instructor login is separate from the Student landing-page flow</li><li>Applications require a passport-size photo and identity document</li><li>Only approved Instructor accounts can enter the studio</li></ul>'
            . '<a class="studio-apply-link" href="/register/instructor">Create a new Instructor account →</a></section>'
            . '<section class="studio-form-wrap"><div class="studio-form-card"><p class="studio-label">APPROVED INSTRUCTORS</p><h2>Instructor sign in</h2>'
            . '<p>Pending applications cannot enter until an administrator approves the Instructor account.</p>' . $errorHtml
            . '<form method="post" action="/teach/studio-access">' . Csrf::field()
            . '<label>Instructor email<input type="email" name="studio_email" value="' . $e($email) . '" autocomplete="email" required></label>'
            . '<label>Instructor password<input type="password" name="studio_password" autocomplete="current-password" required></label>'
            . '<button type="submit">Open Instructor studio</button></form>'
            . '<div class="studio-account-actions"><a href="/forgot-password">Recover Instructor access</a><a href="/register/instructor">Create Instructor account</a></div></div></section></main>'
            . '<script src="/room-assets/Instructor/Login/page.js" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
