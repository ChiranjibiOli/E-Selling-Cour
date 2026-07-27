<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class LoginPage
{
    public static function render(bool $sessionEnded = false): Response
    {
        $notice = $sessionEnded
            ? '<div class="access-notice" role="status"><span>Session ended</span><p>Your previous session was closed safely. Choose the correct portal to continue.</p></div>'
            : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="description" content="Enter the correct CourseHub learning or teaching portal."><title>Choose portal | CourseHub</title>'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Login/page.css"></head>'
            . '<body class="access-body"><div class="access-grain" aria-hidden="true"></div>'
            . '<header class="access-header"><a class="access-brand" href="/"><span>CH</span><strong>CourseHub</strong></a>'
            . '<nav aria-label="Public navigation"><a href="/courses">Courses</a><a href="/instructors">Published instructors</a><a href="/contact">Support</a></nav></header>'
            . '<main class="access-main">' . $notice
            . '<section class="access-intro"><div><span>COURSEHUB ACCESS</span><h1>Enter the space built for your work.</h1></div>'
            . '<p>Students learn through one entrance. Approved instructors build and manage courses through another. Admin access stays private and is never advertised on public pages.</p></section>'
            . '<section class="access-portals" aria-label="CourseHub portals">'
            . '<a class="access-portal access-student" href="/learn/sign-in"><div class="access-portal-index">01</div><div class="access-portal-copy"><span>STUDENT LEARNING</span><h2>Continue your courses.</h2><p>Sign in to purchase courses, open protected lessons, track progress and manage your learning account.</p><ul><li>Purchased course library</li><li>Lesson progress</li><li>Payments and notifications</li></ul></div><span class="access-arrow" aria-hidden="true">↗</span></a>'
            . '<a class="access-portal access-instructor" href="/teach/studio-access"><div class="access-portal-index">02</div><div class="access-portal-copy"><span>INSTRUCTOR STUDIO</span><h2>Build and manage teaching.</h2><p>Approved instructors sign in here. New Instructor applications are available only from inside this dedicated portal.</p><ul><li>Complete course authoring</li><li>Course review workflow</li><li>Students, sales and payouts</li></ul></div><span class="access-arrow" aria-hidden="true">↗</span></a>'
            . '</section>'
            . '<section class="access-shortcuts" aria-label="Useful public actions"><a href="/courses"><span>Browse</span><strong>Explore published courses</strong><i>→</i></a>'
            . '<a href="/instructors"><span>People</span><strong>View published instructors</strong><i>→</i></a>'
            . '<a href="/contact"><span>Help</span><strong>Contact CourseHub support</strong><i>→</i></a></section>'
            . '<footer class="access-footer"><span>Admin access is private.</span><a href="/">Return to CourseHub home</a></footer></main></body></html>';

        return Response::html($html);
    }
}
