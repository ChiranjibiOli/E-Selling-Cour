<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class StudentLoginScreen
{
    public static function render(string $error = '', string $email = '', int $status = 200): Response
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $errorHtml = $error !== '' ? '<div class="student-alert" role="alert">' . $e($error) . '</div>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#f4ede2"><title>Student sign in | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css">'
            . '<link rel="stylesheet" href="/room-assets/Student/Login/page.css"><link rel="stylesheet" href="/assets/css/coursehub-editorial.css"></head><body class="student-login-body">'
            . '<main class="student-login-shell"><section class="student-login-copy"><a class="brand coursehub-auth-brand" href="/"><img src="/assets/images/coursehub-robot.svg" alt=""><span>CourseHub</span></a>'
            . '<span class="student-kicker">LEARNER ACCESS</span><h1>Continue learning.</h1>'
            . '<p>Open your purchased courses, lessons, progress and certificates from one focused workspace.</p>'
            . '<a href="/register/student">Create a student account</a></section>'
            . '<section class="student-login-card"><h2>Student sign in</h2><p>Use the email connected to your learner account.</p>'
            . $errorHtml . '<form method="post" action="/learn/sign-in">' . Csrf::field()
            . '<label>Email<input type="email" name="email" value="' . $e($email) . '" autocomplete="email" required></label>'
            . '<label>Password<input type="password" name="password" autocomplete="current-password" required></label>'
            . '<button type="submit">Enter learning space</button></form>'
            . '<a class="student-help" href="/forgot-password">Forgot your password?</a></section></main>'
            . '<script src="/room-assets/Student/Login/page.js" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
