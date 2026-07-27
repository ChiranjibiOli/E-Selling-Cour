<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class LoginPage
{
    public static function render(bool $sessionEnded = false): Response
    {
        $notice = $sessionEnded
            ? '<div class="access-notice" role="status"><span>Session ended</span><p>Your previous session was closed safely. Sign in again to continue learning.</p></div>'
            : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="description" content="Sign in to your CourseHub Student learning account."><title>Student sign in | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/public-login.css?v=20260728-2"><link rel="stylesheet" href="/assets/css/public-unified.css?v=20260728-2"></head>'
            . '<body class="access-body"><div class="access-grain" aria-hidden="true"></div>'
            . '<header class="public-nav" data-public-nav><a class="public-brand" href="/" aria-label="CourseHub home"><span class="public-brand-mark"><img src="/assets/images/coursehub-robot.svg" alt=""></span><strong>CourseHub</strong></a>'
            . '<button class="public-menu" type="button" aria-label="Open navigation" aria-expanded="false" data-public-menu><span></span><span></span></button>'
            . '<nav class="public-links" aria-label="Primary navigation" data-public-links><a href="/">Home</a><a href="/courses">Courses</a><a href="/#categories">Categories</a><a href="/about">About</a><a href="/contact">Contact</a></nav>'
            . '<div class="public-account"><a class="public-login active" href="/learn/sign-in">Log in</a><a class="public-create" href="/register/student">Create account</a></div></header>'
            . '<main class="access-main">' . $notice
            . '<section class="access-intro"><div><span>STUDENT ACCESS</span><h1>Continue your learning journey.</h1></div>'
            . '<p>Open purchased courses, continue lessons, review progress and manage your Student account through one clear entrance.</p></section>'
            . '<section class="access-portals" aria-label="Student access"><a class="access-portal access-student" href="/learn/sign-in"><div class="access-portal-index">01</div><div class="access-portal-copy"><span>STUDENT LEARNING</span><h2>Sign in to your courses.</h2><p>Access your purchased-course library, protected lessons, progress, payments and notifications.</p><ul><li>Purchased course library</li><li>Lesson progress</li><li>Payments and notifications</li></ul></div><span class="access-arrow" aria-hidden="true">↗</span></a></section>'
            . '<section class="access-shortcuts" aria-label="Useful public actions"><a href="/courses"><span>Browse</span><strong>Explore published courses</strong><i>→</i></a>'
            . '<a href="/about"><span>About</span><strong>Understand how CourseHub works</strong><i>→</i></a>'
            . '<a href="/contact"><span>Help</span><strong>Contact CourseHub support</strong><i>→</i></a></section>'
            . '<footer class="access-footer"><a href="/">Return to CourseHub home</a></footer></main><script src="/assets/js/public-nav.js?v=20260728-2" defer></script></body></html>';

        return Response::html($html);
    }
}
