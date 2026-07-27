<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

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
            $googleHtml = '<div class="student-oauth-divider"><span>or continue with</span></div>'
                . '<div class="student-google-login">'
                . '<div id="g_id_onload" data-client_id="' . $e($googleClientId) . '" data-callback="courseHubGoogleSignIn" data-auto_prompt="false" data-cancel_on_tap_outside="true"></div>'
                . '<div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline" data-text="continue_with" data-size="large" data-logo_alignment="left" data-width="360"></div>'
                . '<form id="studentGoogleLoginForm" class="student-google-form" method="post" action="/learn/sign-in">'
                . Csrf::field()
                . '<input type="hidden" name="google_credential" value=""></form></div>';
            $googleScript = '<script src="https://accounts.google.com/gsi/client" async defer></script>';
        }

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#f5efe5"><title>Student sign in | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Student/Login/page.css?v=20260728-3"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-2">' . PublicNavbar::styles()
            . $googleScript . '</head><body class="student-login-body">'
            . PublicNavbar::render('login')
            . '<main class="student-login-shell">'
            . '<section class="student-login-copy" aria-labelledby="student-login-heading"><div class="student-copy-inner">'
            . '<span class="student-kicker">STUDENT SIGN IN</span><h1 id="student-login-heading">Return to the course,<br><em>not the setup.</em></h1>'
            . '<p>Your purchased courses, completed lessons and progress stay connected to this account.</p>'
            . '<dl class="student-access-facts"><div><dt>Access</dt><dd>Lifetime after verified enrolment</dd></div><div><dt>Progress</dt><dd>Saved lesson by lesson</dd></div><div><dt>Account</dt><dd>One place for courses and payments</dd></div></dl>'
            . '</div></section>'
            . '<section class="student-login-form-section"><div class="student-login-card">'
            . '<header><span>WELCOME BACK</span><h2>Sign in to keep learning</h2><p>Use the same email connected to your CourseHub purchases.</p></header>'
            . $errorHtml . '<form method="post" action="/learn/sign-in">' . Csrf::field()
            . '<label><span>Email address</span><input type="email" name="email" value="' . $e($email) . '" autocomplete="email" inputmode="email" placeholder="you@example.com" required></label>'
            . '<label><span>Password</span><input type="password" name="password" autocomplete="current-password" placeholder="Your password" required></label>'
            . '<div class="student-form-row"><a href="/forgot-password">Forgot password?</a></div>'
            . '<button type="submit">Sign in</button></form>'
            . $googleHtml
            . '<footer><span>New to CourseHub?</span><a href="/register/student">Create a Student account</a></footer>'
            . '</div></section></main>'
            . PublicNavbar::script() . '<script src="/room-assets/Student/Login/page.js" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
