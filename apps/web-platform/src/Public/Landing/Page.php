<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

require_once __DIR__ . '/Components/CourseCard.php';

final class LandingPage
{
    public static function render(LandingViewModel $model): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $courseCards = '';
        foreach (array_slice($model->courses, 0, 6) as $course) {
            $courseCards .= LandingCourseCard::render($course);
        }
        if ($courseCards === '') {
            $courseCards = '<div class="landing-empty"><span>Course catalogue</span><h3>New courses are being prepared.</h3><p>Published courses will appear here automatically.</p></div>';
        }

        $categoryCards = '';
        foreach (array_slice($model->categories, 0, 6) as $index => $category) {
            $slug = (string) ($category['slug'] ?? '');
            $name = (string) ($category['name'] ?? 'Learning category');
            $description = trim((string) ($category['description'] ?? ''));
            if ($description === '') {
                $description = 'Explore practical courses, compare the learning path and choose the level that fits your next goal.';
            }
            $categoryCards .= '<a class="landing-category-card" style="--stack-index:' . $index . '" href="/courses?category=' . rawurlencode($slug) . '">'
                . '<span class="category-number">' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '</span>'
                . '<div><small>COURSE DIRECTION</small><h3>' . $e($name) . '</h3><p>' . $e($description) . '</p></div>'
                . '<strong>Explore <i>↗</i></strong></a>';
        }
        if ($categoryCards === '') {
            $categoryCards = '<a class="landing-category-card" style="--stack-index:0" href="/courses"><span class="category-number">01</span><div><small>COURSE DIRECTION</small><h3>All courses</h3><p>Browse the complete approved CourseHub catalogue.</p></div><strong>Explore <i>↗</i></strong></a>';
        }

        $serviceNotice = $model->catalogAvailable
            ? ''
            : '<div class="landing-service-notice">The live catalogue is temporarily unavailable. The public site remains available.</div>';

        ob_start();
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="CourseHub offers approved, practical courses with lifetime student access.">
    <meta name="theme-color" content="#f5efe5">
    <title>CourseHub | Education that transforms your life</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/room-assets/Public/Landing/page.css?v=20260728-5">
    <link rel="stylesheet" href="/room-assets/Public/Landing/hero-photo.css?v=20260728-4">
    <link rel="stylesheet" href="/assets/css/public-site-fixes.css?v=20260728-2">
    <link rel="stylesheet" href="/assets/css/course-card-theme.css">
    <link rel="stylesheet" href="/assets/css/course-human-system.css?v=20260728-1">
    <?= PublicNavbar::styles() ?>
</head>
<body class="landing-body">
    <?= PublicNavbar::render('home') ?>

    <?= $serviceNotice ?>

    <main>
        <section class="landing-hero" id="top" data-public-section="top">
            <div class="hero-copy" data-reveal>
                <span class="hero-kicker"><i></i> LEARN FROM THE BEST</span>
                <h1>Education<br>that <em>transforms</em><br>your life.</h1>
                <p>Handpicked courses from approved instructors, designed for real progress. Purchase once, complete payment verification, and keep lifetime access.</p>
                <div class="hero-actions">
                    <a class="primary-action" href="/courses">Explore courses</a>
                    <a class="secondary-action" href="/#promise">How it works</a>
                </div>
                <div class="hero-trust"><span>Approved instructors</span><span>Lifetime access</span><span>Progress tracking</span></div>
            </div>

            <div class="hero-visual hero-photo-visual" data-hero-visual>
                <span class="hero-orbit hero-orbit-one" aria-hidden="true"></span>
                <span class="hero-orbit hero-orbit-two" aria-hidden="true"></span>
                <img class="hero-book-photo" src="/assets/images/landing-book-photo.svg?v=20260728-4" alt="Open book with a hand turning a page">
                <div class="hero-note"><span>COURSEHUB</span><strong>Learn clearly.<br>Build confidently.</strong></div>
            </div>
        </section>

        <section class="promise-section" id="promise" data-public-section="promise">
            <div class="promise-heading" data-reveal>
                <span>WHAT COURSEHUB PROMISES</span>
                <h2>Useful promises,<br><em>not marketing fog.</em></h2>
            </div>
            <div class="promise-list">
                <article data-reveal><span>01</span><div><h3>Approved instructors</h3><p>Instructor applications and course submissions are reviewed before they reach Students.</p></div></article>
                <article data-reveal><span>02</span><div><h3>Clear course information</h3><p>Understand the level, learning outcomes, lesson structure and payment before enrolling.</p></div></article>
                <article data-reveal><span>03</span><div><h3>Lifetime learning access</h3><p>After payment approval, the purchased course remains in the Student learning library.</p></div></article>
                <article data-reveal><span>04</span><div><h3>Visible progress</h3><p>Completed lessons and course progress remain connected to the Student account.</p></div></article>
            </div>
        </section>

        <section class="category-section" id="categories" data-public-section="categories">
            <div class="section-intro" data-reveal>
                <span>CHOOSE A DIRECTION</span>
                <h2>Start with a subject.<br><em>Build from there.</em></h2>
                <p>Categories overlap as you scroll, keeping the choices visible without turning the page into a wall of tiny boxes.</p>
            </div>
            <div class="category-stack"><?= $categoryCards ?></div>
        </section>

        <section class="course-section" id="courses" data-public-section="courses">
            <div class="section-intro course-intro" data-reveal>
                <span>APPROVED COURSES</span>
                <h2>Find the course<br><em>worth continuing.</em></h2>
                <a href="/courses">Browse complete catalogue <i>↗</i></a>
            </div>
            <div class="course-grid" data-reveal><?= $courseCards ?></div>
        </section>

        <section class="journey-section">
            <div class="journey-copy" data-reveal>
                <span>ONE CONNECTED JOURNEY</span>
                <h2>Browse.<br>Purchase.<br><em>Keep learning.</em></h2>
            </div>
            <div class="journey-steps">
                <article data-reveal><b>01</b><h3>Discover</h3><p>Search approved courses by subject, category and level.</p></article>
                <article data-reveal><b>02</b><h3>Verify</h3><p>Complete checkout and submit real payment evidence when manual payment is used.</p></article>
                <article data-reveal><b>03</b><h3>Learn</h3><p>Open protected lessons, mark progress and continue from the Student library.</p></article>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="footer-brand"><img src="/assets/images/coursehub-robot.svg" alt=""><div><strong>CourseHub</strong><span>Education that moves with you.</span></div></div>
        <div><small>EXPLORE</small><a href="/courses">Courses</a><a href="/#promise">About</a><a href="/contact">Contact</a></div>
        <div><small>STUDENT</small><a href="/learn/sign-in">Sign in</a><a href="/register/student">Create account</a></div>
        <p>© <?= date('Y') ?> CourseHub. Practical learning, clear payment service and lifetime access to approved purchases.</p>
    </footer>

    <?= PublicNavbar::script() ?>
    <script src="/room-assets/Public/Landing/page.js?v=20260728-6" defer></script>
    <script src="/assets/js/course-card-theme.js" defer></script>
</body>
</html>
        <?php
        return Response::html((string) ob_get_clean());
    }
}
