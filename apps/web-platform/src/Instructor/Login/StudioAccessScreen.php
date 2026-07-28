<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class StudioAccessScreen
{
    public static function render(string $error = '', string $email = '', int $status = 200): Response
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $errorHtml = $error !== '' ? '<aside class="studio-error" role="alert">' . $e($error) . '</aside>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="#171d27"><title>Instructor sign in | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/room-assets/Instructor/Login/page.css?v=20260728-4"></head><body class="studio-access-body">'
            . '<header class="studio-top"><a class="coursehub-auth-brand" href="/"><img src="/assets/images/coursehub-robot.svg" alt=""><span>CourseHub</span><small>Instructor studio</small></a>'
            . '<nav aria-label="Instructor access links"><a href="/learn/sign-in">Student sign in</a><a class="studio-create-link" href="/register/instructor">Create Instructor account</a></nav></header>'
            . '<main class="studio-access-grid">'
            . '<section class="studio-panel" aria-labelledby="studio-heading"><div class="studio-panel-inner">'
            . '<span>APPROVED INSTRUCTOR ACCESS</span><h1 id="studio-heading">Build courses.<br><em>Run the business.</em></h1>'
            . '<p>Return to authoring, enrolled students, verified sales and payouts from one protected teaching workspace.</p>'
            . '<dl class="studio-access-facts"><div><dt>Approval</dt><dd>Only approved Instructor accounts enter</dd></div><div><dt>Authoring</dt><dd>Draft, submit and revise complete courses</dd></div><div><dt>Business</dt><dd>Follow learners, sales and payout records</dd></div></dl>'
            . '<div class="studio-trust-row"><span>✓ Ownership protected</span><span>✓ Earnings server calculated</span></div>'
            . '<div class="studio-apply-line"><span>Not registered as an Instructor?</span><a href="/register/instructor">Start an application</a></div>'
            . '</div></section>'
            . '<section class="studio-form-wrap"><div class="studio-form-card"><div class="studio-form-brand"><img src="/assets/images/coursehub-robot.svg" alt=""><span>Teaching workspace</span></div><header><span>STUDIO SIGN IN</span><h2>Welcome back, Instructor</h2><p>Use the account approved for your CourseHub teaching studio.</p></header>'
            . $errorHtml
            . '<form method="post" action="/teach/studio-access">' . Csrf::field()
            . '<label><span>Instructor email</span><input type="email" name="studio_email" value="' . $e($email) . '" autocomplete="email" inputmode="email" placeholder="you@example.com" required></label>'
            . '<label><span>Password</span><input type="password" name="studio_password" autocomplete="current-password" placeholder="Your password" required></label>'
            . '<div class="studio-form-row"><a href="/forgot-password">Forgot password?</a></div>'
            . '<button type="submit">Open Instructor studio</button></form>'
            . '<footer><span>Application not submitted?</span><a href="/register/instructor">Create Instructor account</a></footer>'
            . '</div></section></main>'
            . '<script src="/room-assets/Instructor/Login/page.js" defer></script></body></html>';

        return Response::html($html, $status);
    }
}
