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
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Login/page.css"></head>'
            . '<body class="access-body"><div class="access-grain" aria-hidden="true"></div>'
            . '<header class="access-header"><a class="access-brand" href="/"><span>CH</span><strong>CourseHub</strong></a>'
            . '<nav aria-label="Public navigation"><a href="/courses">Courses</a><a href="/about">About</a><a href="/contact">Support</a></nav></header>'
            . '<main class="access-main">' . $notice
            . '<section class="access-intro"><div><span>STUDENT ACCESS</span><h1>Continue your learning journey.</h1></div>'
            . '<p>Open purchased courses, continue lessons, review progress and manage your Student account through one clear entrance.</p></section>'
            . '<section class="access-portals" aria-label="Student access">'
            . '<a class="access-portal access-student" href="/learn/sign-in"><div class="access-portal-index">01</div><div class="access-portal-copy"><span>STUDENT LEARNING</span><h2>Sign in to your courses.</h2><p>Access your purchased-course library, protected lessons, progress, payments and notifications.</p><ul><li>Purchased course library</li><li>Lesson progress</li><li>Payments and notifications</li></ul></div><span class="access-arrow" aria-hidden="true">↗</span></a>'
            . '</section>'
            . '<section class="access-shortcuts" aria-label="Useful public actions"><a href="/courses"><span>Browse</span><strong>Explore published courses</strong><i>→</i></a>'
            . '<a href="/about"><span>About</span><strong>Understand how CourseHub works</strong><i>→</i></a>'
            . '<a href="/contact"><span>Help</span><strong>Contact CourseHub support</strong><i>→</i></a></section>'
            . '<footer class="access-footer"><a href="/">Return to CourseHub home</a></footer></main></body></html>';

        return Response::html($html);
    }
}
