<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

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
    <link rel="stylesheet" href="/room-assets/Public/Landing/page.css?v=20260728-1">
</head>
<body class="landing-body">
    <header class="landing-nav" data-landing-nav>
        <a class="landing-brand" href="/" aria-label="CourseHub home">
            <span class="landing-brand-mark"><img src="/assets/images/coursehub-robot.svg" alt=""></span>
            <strong>CourseHub</strong>
        </a>
        <button class="landing-menu" type="button" aria-label="Open navigation" aria-expanded="false" data-landing-menu><span></span><span></span></button>
        <nav class="landing-links" aria-label="Primary navigation" data-landing-links>
            <a class="active" href="#top">Home</a>
            <a href="/courses">Courses</a>
            <a href="#categories">Categories</a>
            <a href="#promise">About</a>
            <a href="/contact">Contact</a>
        </nav>
        <div class="landing-account">
            <a href="/learn/sign-in">Log in</a>
            <a class="landing-create" href="/register/student">Create account</a>
        </div>
    </header>

    <?= $serviceNotice ?>

    <main>
        <section class="landing-hero" id="top">
            <div class="hero-copy" data-reveal>
                <span class="hero-kicker"><i></i> LEARN FROM THE BEST</span>
                <h1>Education<br>that <em>transforms</em><br>your life.</h1>
                <p>Handpicked courses from approved instructors, designed for real progress. Purchase once, complete payment verification, and keep lifetime access.</p>
                <div class="hero-actions">
                    <a class="primary-action" href="/courses">Explore courses</a>
                    <a class="secondary-action" href="#promise">How it works</a>
                </div>
                <div class="hero-trust"><span>Approved instructors</span><span>Lifetime access</span><span>Progress tracking</span></div>
            </div>

            <div class="hero-visual" aria-hidden="true" data-hero-visual>
                <span class="hero-orbit hero-orbit-one"></span>
                <span class="hero-orbit hero-orbit-two"></span>
                <svg class="hero-book" viewBox="0 0 620 640" role="presentation">
                    <defs>
                        <filter id="bookShadow" x="-30%" y="-30%" width="160%" height="180%"><feDropShadow dx="0" dy="24" stdDeviation="22" flood-color="#4d3c2a" flood-opacity=".22"/></filter>
                        <linearGradient id="pageLeft" x1="0" x2="1"><stop stop-color="#f5eddf"/><stop offset="1" stop-color="#fffdf7"/></linearGradient>
                        <linearGradient id="pageRight" x1="1" x2="0"><stop stop-color="#f1e7d7"/><stop offset="1" stop-color="#fffdf8"/></linearGradient>
                        <linearGradient id="skin" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#a9613d"/><stop offset="1" stop-color="#7f432d"/></linearGradient>
                    </defs>
                    <g filter="url(#bookShadow)" transform="translate(32 42) rotate(-3 300 250)">
                        <path d="M74 84C156 52 244 64 295 113v330c-72-40-145-49-231-17L74 84Z" fill="url(#pageLeft)" stroke="#cbbda9" stroke-width="3"/>
                        <path d="M295 113c70-50 156-61 244-22l4 341c-86-35-162-28-248 11V113Z" fill="url(#pageRight)" stroke="#cbbda9" stroke-width="3"/>
                        <path d="M294 112v332" stroke="#b5a68f" stroke-width="5"/>
                        <path d="M82 96c84-28 156-20 212 25M82 111c78-24 149-17 211 23M534 103c-83-29-160-18-238 25M535 119c-83-26-158-15-239 27" fill="none" stroke="#ded3c3" stroke-width="4"/>
                        <g stroke="#c8bba8" stroke-width="2" opacity=".68">
                            <path d="M105 147h147M105 164h163M105 181h152M105 198h166M105 215h145M105 232h158M105 249h166M105 266h150M105 283h161M105 300h153M105 317h166M105 334h144M105 351h160M105 368h150"/>
                            <path d="M331 149h158M331 166h145M331 183h166M331 200h152M331 217h162M331 234h145M331 251h164M331 268h153M331 285h166M331 302h142M331 319h160M331 336h151M331 353h164M331 370h146"/>
                        </g>
                    </g>
                    <g class="hero-hand">
                        <path d="M340 410c9-44 16-85 23-124 4-20 26-26 37-9 8 12 5 31 2 48l-4 27 18-85c4-21 28-26 39-8 7 12 2 32-1 47l-13 67 22-77c6-20 30-22 39-3 5 12-1 29-5 43l-19 69 20-43c8-17 30-17 38-1 7 14-1 31-8 46l-34 76c-17 38-43 70-78 91l-14 9-91-43 13-31c11-27 15-54 10-82l-9-53c-4-23 22-37 38-20 8 8 12 22 15 35l3 17Z" fill="url(#skin)"/>
                        <path d="M286 516l98 46-34 73-98-45 34-74Z" fill="#171611"/>
                        <path d="M279 537l83 38" stroke="#ff7043" stroke-width="8" opacity=".9"/>
                    </g>
                </svg>
                <div class="hero-note"><span>COURSEHUB</span><strong>Learn clearly.<br>Build confidently.</strong></div>
            </div>
        </section>

        <section class="promise-section" id="promise">
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

        <section class="category-section" id="categories">
            <div class="section-intro" data-reveal>
                <span>CHOOSE A DIRECTION</span>
                <h2>Start with a subject.<br><em>Build from there.</em></h2>
                <p>Categories overlap as you scroll, keeping the choices visible without turning the page into a wall of tiny boxes.</p>
            </div>
            <div class="category-stack"><?= $categoryCards ?></div>
        </section>

        <section class="course-section">
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
        <div><small>EXPLORE</small><a href="/courses">Courses</a><a href="/about">About</a><a href="/contact">Contact</a></div>
        <div><small>STUDENT</small><a href="/learn/sign-in">Sign in</a><a href="/register/student">Create account</a></div>
        <p>© <?= date('Y') ?> CourseHub. Practical learning, clear payment service and lifetime access to approved purchases.</p>
    </footer>

    <script src="/room-assets/Public/Landing/page.js?v=20260728-1" defer></script>
</body>
</html>
        <?php
        return Response::html((string) ob_get_clean());
    }
}
