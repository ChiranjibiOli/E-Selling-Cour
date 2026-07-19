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
    <meta name="description" content="Learn how CourseHub connects students with reviewed instructors and practical lifetime-access courses.">
    <title>About CourseHub</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/room-assets/Public/About/page.css">
</head>
<body class="about-body">
<header class="about-header">
    <a class="about-brand" href="/">CourseHub</a>
    <nav aria-label="Primary navigation">
        <a href="/courses">Courses</a>
        <a class="active" href="/about">About</a>
        <a href="/contact">Contact</a>
        <a href="/learn/sign-in">Student sign in</a>
        <a class="about-join" href="/register/student">Join</a>
    </nav>
</header>
<main>
    <section class="about-hero">
        <div class="about-hero-copy">
            <span>ABOUT COURSEHUB</span>
            <h1>Practical learning, controlled quality, and a clearer path forward.</h1>
            <p>CourseHub is a Nepal-focused course marketplace where reviewed instructors publish useful courses and students purchase only the courses they need, with lifetime access after verified payment.</p>
            <div class="about-actions">
                <a class="about-primary" href="/courses">Explore courses →</a>
                <a class="about-secondary" href="/register/instructor">Apply to teach</a>
            </div>
        </div>
        <div class="about-hero-panel" aria-label="CourseHub platform model">
            <span>ONE CONNECTED PLATFORM</span>
            <article><b>01</b><div><strong>Reviewed instructors</strong><small>Teaching identity and experience are checked before studio access.</small></div></article>
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
            <article><span>FOR STUDENTS</span><h3>Choose with context.</h3><p>Students can compare the level, curriculum, instructor, price, language, and preview lessons before making a purchase.</p></article>
            <article><span>FOR INSTRUCTORS</span><h3>Teach through a controlled studio.</h3><p>Approved instructors build private drafts, submit complete courses for review, support enrolled learners, and track verified earnings.</p></article>
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
            <article><b>5</b><h3>Improve</h3><p>Verified reviews, support messages, and instructor responses help the platform improve without inventing fake trust.</p></article>
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
            <li><strong>Private unfinished work</strong><span>Instructor drafts never appear to students or the admin approval queue.</span></li>
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
</body>
</html>
HTML;
        return Response::html($html);
    }
}
