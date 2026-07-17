<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

require_once __DIR__ . '/Components/CourseCard.php';
require_once __DIR__ . '/Components/CategoryPill.php';

final class LandingPage
{
    public static function render(LandingViewModel $model): Response
    {
        $courseCards = '';
        foreach ($model->courses as $course) {
            $courseCards .= LandingCourseCard::render($course);
        }
        if ($courseCards === '') {
            $courseCards = '<div class="landing-empty"><h3>Courses are being prepared.</h3><p>Start the catalog service or publish a course to fill this section.</p></div>';
        }

        $categoryPills = '';
        foreach ($model->categories as $category) {
            $categoryPills .= LandingCategoryPill::render($category);
        }
        if ($categoryPills === '') {
            $categoryPills = '<a class="category-pill" href="/courses"><strong>All courses</strong><span>Browse the catalog</span></a>';
        }

        $serviceNotice = $model->catalogAvailable ? '' : '<div class="service-notice">Catalog service is currently offline. The public shell remains available.</div>';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="description" content="Learn practical skills from trusted instructors on CourseHub."><title>CourseHub | Learn skills that move you forward</title>'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Landing/page.css"></head><body class="landing-body">'
            . '<header class="public-header"><a class="public-brand" href="/">CourseHub</a><nav><a href="/courses">Explore</a><a href="/about">About</a><a href="/teach/studio-access">Teach</a></nav>'
            . '<div class="public-actions"><a href="/learn/sign-in">Sign in</a><a class="button" href="/register/student">Start learning</a></div></header>'
            . $serviceNotice . '<main><section class="hero"><div class="hero-copy"><span class="eyebrow">PRACTICAL ONLINE LEARNING</span>'
            . '<h1>Build skills that make your next move possible.</h1><p>Learn through structured courses, clear lessons and lifetime access. Built for students in Nepal and instructors who want a serious teaching workspace.</p>'
            . '<div class="hero-actions"><a class="primary" href="/courses">Explore courses</a><a class="secondary" href="/register/instructor">Become an instructor</a></div>'
            . '<div class="hero-proof"><div><strong>Lifetime</strong><span>course access</span></div><div><strong>Secure</strong><span>manual verification</span></div><div><strong>Focused</strong><span>learning progress</span></div></div></div>'
            . '<aside class="hero-panel"><span>COURSEHUB LEARNING PATH</span><div class="path-step active"><b>01</b><div><strong>Choose a course</strong><small>Preview details before purchase</small></div></div>'
            . '<div class="path-step"><b>02</b><div><strong>Complete payment</strong><small>Submit transaction proof securely</small></div></div><div class="path-step"><b>03</b><div><strong>Learn for life</strong><small>Track lessons and progress</small></div></div></aside></section>'
            . '<section class="category-section"><div class="section-heading"><span>DISCOVER</span><h2>Start with a direction.</h2></div><div class="category-grid">' . $categoryPills . '</div></section>'
            . '<section class="featured-section"><div class="section-heading split"><div><span>FEATURED COURSES</span><h2>Learn something useful.</h2></div><a href="/courses">View all courses →</a></div><div class="course-grid">' . $courseCards . '</div></section>'
            . '<section class="teach-banner"><div><span>FOR INSTRUCTORS</span><h2>Turn your knowledge into a structured course.</h2><p>Create drafts, build lessons, submit for approval, manage students and request withdrawals from your own studio.</p></div><a href="/register/instructor">Apply to teach</a></section></main>'
            . '<footer class="public-footer"><a href="/">CourseHub</a><p>Practical learning, organized properly.</p><nav><a href="/privacy">Privacy</a><a href="/terms">Terms</a><a href="/contact">Contact</a></nav></footer>'
            . '<script src="/room-assets/Public/Landing/page.js" defer></script></body></html>';

        return Response::html($html);
    }
}
