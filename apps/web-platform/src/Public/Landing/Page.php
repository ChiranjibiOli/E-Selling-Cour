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
            $courseCards = '<div class="landing-empty reveal"><h3>New courses are on the way.</h3><p>Published courses will appear here automatically.</p></div>';
        }

        $categoryPills = '';
        foreach ($model->categories as $category) {
            $categoryPills .= LandingCategoryPill::render($category);
        }
        if ($categoryPills === '') {
            $categoryPills = '<a class="category-pill" href="/courses"><strong>All courses</strong><span>Browse the complete catalog</span></a>';
        }

        $serviceNotice = $model->catalogAvailable
            ? ''
            : '<div class="service-notice">The course catalog is temporarily unavailable. The public experience remains online.</div>';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="description" content="Discover practical, expert-led courses and build skills that move your future forward."><title>CourseHub | Learn today. Shape tomorrow.</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Landing/page.css"></head><body class="landing-body">'
            . '<div class="ambient-layer ambient-one" aria-hidden="true"></div><div class="ambient-layer ambient-two" aria-hidden="true"></div><div class="ambient-layer ambient-three" aria-hidden="true"></div>'
            . '<header class="public-header"><div class="header-side header-left"><button class="menu-button" type="button" aria-label="Open navigation" aria-expanded="false"><span></span><span></span></button></div>'
            . '<a class="public-brand" href="/" aria-label="CourseHub home"><span class="brand-mark">CH</span><span>CourseHub</span></a>'
            . '<div class="header-side header-right"><a class="quiet-link" href="/learn/sign-in">Sign in</a><a class="join-link" href="/register/student">Join CourseHub</a></div>'
            . '<nav class="glass-nav" aria-label="Primary navigation"><a href="/courses">Courses</a><a href="#categories">Categories</a><a href="#experience">Experience</a><a href="/about">About</a></nav></header>'
            . $serviceNotice
            . '<main><section class="hero" id="top"><div class="hero-sky" aria-hidden="true"><div class="cloud cloud-a"></div><div class="cloud cloud-b"></div><div class="cloud cloud-c"></div><div class="mountain mountain-back"></div><div class="mountain mountain-front"></div></div>'
            . '<div class="hero-copy reveal"><span class="eyebrow">CURATED ONLINE LEARNING</span><h1>Learn today.<em>Shape tomorrow.</em></h1><p>Build practical skills through clear lessons, trusted instructors and learning paths designed around real progress.</p>'
            . '<div class="hero-intent"><span class="intent-label">Start with what matters</span><div class="intent-options"><a href="/courses?sort=popular">Popular now</a><a href="/courses?level=beginner">Beginner friendly</a><a href="/courses?sort=newest">Recently added</a></div></div></div>'
            . '<div class="hero-orbit reveal" aria-hidden="true"><div class="orbit-card orbit-card-main"><span>Focused learning</span><strong>One course.<br>Real momentum.</strong><small>Lifetime access • Clear progress</small></div><div class="orbit-card orbit-card-small"><b>4.9</b><span>learner rating</span></div></div>'
            . '<form class="course-finder reveal" action="/courses" method="get"><div class="finder-glow" aria-hidden="true"></div><div class="finder-field finder-keyword"><label for="course-query">Find your next course</label><div><span class="search-icon" aria-hidden="true"></span><input id="course-query" name="q" type="search" placeholder="Search by skill, topic or keyword" autocomplete="off"></div></div>'
            . '<div class="finder-field"><label for="course-level">Level</label><select id="course-level" name="level"><option value="">All levels</option><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></div>'
            . '<div class="finder-field"><label for="course-duration">Duration</label><select id="course-duration" name="duration"><option value="">Any duration</option><option value="short">Under 3 hours</option><option value="medium">3–10 hours</option><option value="long">10+ hours</option></select></div>'
            . '<button class="finder-submit" type="submit"><span>Search courses</span><i aria-hidden="true">↗</i></button></form>'
            . '<div class="hero-metrics reveal"><div><strong>Lifetime</strong><span>access to purchased courses</span></div><div><strong>Structured</strong><span>lessons and visible progress</span></div><div><strong>Secure</strong><span>student-first checkout flow</span></div></div></section>'
            . '<section class="category-section reveal" id="categories"><div class="section-heading"><span>EXPLORE BY DIRECTION</span><h2>Choose a path that feels like yours.</h2><p>Slide through the categories and begin with a subject that fits your next goal.</p></div><div class="category-shell"><button class="rail-control rail-prev" type="button" aria-label="Previous categories">←</button><div class="category-grid" data-drag-scroll>' . $categoryPills . '</div><button class="rail-control rail-next" type="button" aria-label="Next categories">→</button></div></section>'
            . '<section class="experience-section" id="experience"><div class="experience-copy reveal"><span>LEARNING, WITHOUT THE NOISE</span><h2>A calmer place to build serious skills.</h2><p>CourseHub keeps discovery, purchase, lessons and progress connected so students can focus on learning instead of wrestling with the platform.</p><div class="experience-points"><div><b>01</b><span><strong>Discover clearly</strong><small>Useful details before you commit.</small></span></div><div><b>02</b><span><strong>Learn continuously</strong><small>Your progress stays with you.</small></span></div><div><b>03</b><span><strong>Grow deliberately</strong><small>Skills shaped around real outcomes.</small></span></div></div></div><div class="experience-visual reveal" aria-hidden="true"><div class="glass-stack stack-back"></div><div class="glass-stack stack-mid"></div><div class="glass-stack stack-front"><span>YOUR LEARNING SPACE</span><strong>Quiet design.<br>Clear direction.</strong><div class="progress-line"><i></i></div><small>Progress that feels visible, not overwhelming.</small></div></div></section>'
            . '<section class="featured-section reveal"><div class="section-heading split"><div><span>FEATURED COURSES</span><h2>Thoughtfully selected for useful progress.</h2></div><a href="/courses">View the full catalog <span>↗</span></a></div><div class="course-grid">' . $courseCards . '</div></section>'
            . '<section class="closing-section reveal"><div><span>YOUR NEXT CHAPTER</span><h2>Start with curiosity.<br><em>Continue with confidence.</em></h2></div><a href="/register/student">Create your student account <span>↗</span></a></section></main>'
            . '<footer class="public-footer"><a class="footer-brand" href="/"><span class="brand-mark">CH</span><span>CourseHub</span></a><p>Practical learning, arranged with care.</p><nav><a href="/privacy">Privacy</a><a href="/terms">Terms</a><a href="/contact">Contact</a></nav></footer>'
            . '<script src="/room-assets/Public/Landing/page.js" defer></script></body></html>';

        return Response::html($html);
    }
}
