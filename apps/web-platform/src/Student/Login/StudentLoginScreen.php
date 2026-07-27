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
        $googleClientId = trim((string) getenv('GOOGLE_CLIENT_ID'));
        $googleHtml = '';
        $googleScript = '';

        if ($googleClientId !== '') {
            $googleHtml = '<div class="student-oauth-divider"><span>or</span></div>'
                . '<div class="student-google-login">'
                . '<div id="g_id_onload" data-client_id="' . $e($googleClientId) . '" data-callback="courseHubGoogleSignIn" data-auto_prompt="false" data-cancel_on_tap_outside="true"></div>'
                . '<div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline" data-text="continue_with" data-size="large" data-logo_alignment="left" data-width="360"></div>'
                . '<form id="studentGoogleLoginForm" class="student-google-form" method="post" action="/learn/sign-in">'
                . Csrf::field()
                . '<input type="hidden" name="google_credential" value=""></form></div>';
            $googleScript = '<script src="https://accounts.google.com/gsi/client" async defer></script>';
        }

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#f7f0e5"><title>Student sign in | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Student/Login/page.css"><link rel="stylesheet" href="/assets/css/coursehub-editorial.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">'
            . $googleScript . '</head><body class="student-login-body">'
            . '<header class="public-site-nav" data-public-site-nav><a class="public-site-brand" href="/" aria-label="CourseHub home"><img src="/assets/images/coursehub-robot.svg" alt=""><strong>CourseHub</strong></a>'
            . '<button class="public-site-menu" type="button" data-public-site-menu aria-label="Open navigation" aria-expanded="false"><span></span><span></span></button>'
            . '<nav class="public-site-links" aria-label="Public navigation"><a href="/">Home</a><a href="/courses">Courses</a><a href="/#categories">Categories</a><a href="/about">About</a><a href="/contact">Contact</a></nav>'
            . '<div class="public-site-account"><a class="public-login active" href="/learn/sign-in">Log in</a><a class="public-create" href="/register/student">Create account</a></div></header>'
            . '<main class="student-login-shell"><section class="student-login-copy">'
            . '<span class="student-kicker">STUDENT ACCESS</span><h1>Continue learning.</h1>'
            . '<p>Open your purchased courses, continue lessons, review progress and manage your learning account from one focused space.</p>'
            . '<a href="/register/student">Create a Student account</a></section>'
            . '<section class="student-login-card"><h2>Student sign in</h2><p>Use your learner account or continue securely with Google.</p>'
            . $errorHtml . '<form method="post" action="/learn/sign-in">' . Csrf::field()
            . '<label>Email<input type="email" name="email" value="' . $e($email) . '" autocomplete="email" required></label>'
            . '<label>Password<input type="password" name="password" autocomplete="current-password" required></label>'
            . '<button type="submit">Enter learning space</button></form>'
            . $googleHtml
            . '<a class="student-help" href="/forgot-password">Forgot your password?</a></section></main>'
            . '<script src="/assets/js/public-site.js?v=20260728-2" defer></script><script src="/room-assets/Student/Login/page.js" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
