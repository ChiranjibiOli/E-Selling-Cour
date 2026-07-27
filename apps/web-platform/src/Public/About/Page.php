<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class AboutPage
{
    public static function render(): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Learn how CourseHub connects students with reviewed courses and practical lifetime access.">
    <title>About CourseHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/room-assets/Public/About/page.css">
    <link rel="stylesheet" href="/assets/css/public-unified.css?v=20260728-1">
</head>
<body class="about-body">
<header class="public-nav" data-public-nav>
    <a class="public-brand" href="/" aria-label="CourseHub home"><span class="public-brand-mark"><img src="/assets/images/coursehub-robot.svg" alt=""></span><strong>CourseHub</strong></a>
    <button class="public-menu" type="button" aria-label="Open navigation" aria-expanded="false" data-public-menu><span></span><span></span></button>
    <nav class="public-links" aria-label="Primary navigation" data-public-links>
        <a href="/">Home</a>
        <a href="/courses">Courses</a>
        <a href="/#categories">Categories</a>
        <a class="active" href="/about">About</a>
        <a href="/contact">Contact</a>
    </nav>
    <div class="public-account"><a class="public-login" href="/login">Log in</a><a class="public-create" href="/register/student">Create account</a></div>
</header>
<main>
    <section class="about-hero">
        <div class="about-hero-copy">
            <span>ABOUT COURSEHUB</span>
            <h1>Practical learning, controlled quality, and a clearer path forward.</h1>
            <p>CourseHub is a Nepal-focused course marketplace where reviewed courses are published with clear learning information and students purchase only what they need, with lifetime access after verified payment.</p>
            <div class="about-actions">
                <a class="about-primary" href="/courses">Explore courses →</a>
                <a class="about-secondary" href="/contact">Contact support</a>
            </div>
        </div>
        <div class="about-hero-panel" aria-label="CourseHub platform model">
            <span>ONE CONNECTED PLATFORM</span>
            <article><b>01</b><div><strong>Reviewed publishing</strong><small>Teaching identity, course structure and required information are checked before public access.</small></div></article>
            <article><b>02</b><div><strong>Approved courses</strong><small>Drafts remain private and only reviewed courses reach the catalog.</small></div></article>
            <article><b>03</b><div><strong>Verified lifetime access</strong><small>Enrollment is created only after the server verifies payment.</small></div></article>
        </div>
    </section>

    <section class="about-story">
        <div class="about-section-heading">
            <span>WHY WE EXIST</span>
            <h2>Less confusion between finding a course and actually learning from it.</h2>
        </div>
        <div class="about-story-grid">
            <article><span>FOR STUDENTS</span><h3>Choose with context.</h3><p>Students can compare the level, curriculum, creator, price, language, and preview lessons before making a purchase.</p></article>
            <article><span>FOR COURSE CREATORS</span><h3>Build through a controlled studio.</h3><p>Approved creators prepare private drafts, submit complete courses for review, support enrolled learners, and track verified earnings.</p></article>
            <article><span>FOR THE PLATFORM</span><h3>Keep business records connected.</h3><p>Orders, payments, enrollments, progress, reviews, earnings, and withdrawals stay linked instead of becoming unrelated database decorations.</p></article>
        </div>
    </section>

    <section class="about-method">
        <div class="about-section-heading">
            <span>HOW COURSEHUB WORKS</span>
            <h2>Every important action follows a visible workflow.</h2>
        </div>
        <div class="about-steps">
            <article><b>1</b><h3>Discover</h3><p>Browse only active, published courses and review their structure before purchase.</p></article>
            <article><b>2</b><h3>Purchase</h3><p>The server calculates the real price, validates discounts, and creates an auditable order.</p></article>
            <article><b>3</b><h3>Verify</h3><p>Manual or gateway payment is checked before access is granted.</p></article>
            <article><b>4</b><h3>Learn</h3><p>Enrolled students open protected lessons, continue from their course library, and record progress.</p></article>
            <article><b>5</b><h3>Improve</h3><p>Verified reviews and support messages help the platform improve without inventing fake trust.</p></article>
        </div>
    </section>

    <section class="about-values">
        <div>
            <span>PLATFORM PRINCIPLES</span>
            <h2>Built around clarity rather than decorative noise.</h2>
        </div>
        <ul>
            <li><strong>Course-specific purchase</strong><span>Buying one course never unlocks another.</span></li>
            <li><strong>Lifetime access</strong><span>Verified purchases remain available unless access is lawfully revoked or refunded.</span></li>
            <li><strong>Private unfinished work</strong><span>Drafts never appear to students or the approval queue before submission.</span></li>
            <li><strong>Server-side trust</strong><span>Prices, ownership, payment success, and access are never trusted from browser input alone.</span></li>
        </ul>
    </section>

    <section class="about-closing">
        <div><span>YOUR NEXT STEP</span><h2>Choose one useful course and begin properly.</h2></div>
        <div class="about-actions"><a class="about-primary" href="/register/student">Create student account →</a><a class="about-secondary" href="/contact">Contact support</a></div>
    </section>
</main>
<footer class="about-footer">
    <a href="/">CourseHub</a>
    <div><a href="/courses">Courses</a><a href="/faq">FAQ</a><a href="/contact">Support</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a></div>
    <span>Practical learning, arranged with care.</span>
</footer>
<script src="/assets/js/public-nav.js?v=20260728-1" defer></script>
</body>
</html>
HTML;
        return Response::html($html);
    }
}
