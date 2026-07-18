<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

require_once __DIR__ . '/Components/CourseCard.php';
require_once __DIR__ . '/Components/CategoryPill.php';

final class LandingPage
{
    public static function render(LandingViewModel $model): Response
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $courseCards = '';
        foreach ($model->courses as $course) {
            $courseCards .= LandingCourseCard::render($course);
        }
        if ($courseCards === '') {
            $courseCards = '<div class="landing-empty reveal"><span>Catalog update</span><h3>New courses are being prepared.</h3><p>Published courses will appear here automatically.</p></div>';
        }

        $categoryPills = '';
        $categoryOptions = '<option value="">Every category</option>';
        foreach ($model->categories as $category) {
            $categoryPills .= LandingCategoryPill::render($category);
            $slug = (string) ($category['slug'] ?? '');
            $categoryOptions .= '<option value="' . $escape($slug) . '">' . $escape($category['name'] ?? 'Category') . '</option>';
        }
        if ($categoryPills === '') {
            $categoryPills = '<a class="category-pill" href="/courses"><strong>All courses</strong><span>Open the complete catalog</span></a>';
        }

        $heroCourse = $model->courses[0] ?? [];
        $heroImage = trim((string) ($heroCourse['thumbnail_url'] ?? ''));
        $heroTitle = $escape($heroCourse['title'] ?? 'A clearer way to build useful skills');
        $heroCategory = $escape($heroCourse['category_name'] ?? 'Featured learning path');
        $heroInstructor = $escape($heroCourse['instructor_name'] ?? 'CourseHub');
        $heroCourseId = (int) ($heroCourse['id'] ?? 0);
        $heroHref = $heroCourseId > 0 ? '/course?id=' . $heroCourseId : '/courses';
        $heroMedia = $heroImage !== ''
            ? '<img src="' . $escape($heroImage) . '" alt="" loading="eager" fetchpriority="high">'
            : '<div class="hero-art" aria-hidden="true"><i></i><i></i><i></i><span>CH</span></div>';

        $serviceNotice = $model->catalogAvailable
            ? ''
            : '<div class="service-notice">The live course catalog is temporarily unavailable. The public experience remains online.</div>';

        ob_start();
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Discover practical, expert-led courses and build skills with a clear learning journey.">
    <title>CourseHub | Build skills with direction</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/room-assets/Public/Landing/page.css">
    <link rel="stylesheet" href="/room-assets/Public/Landing/motion-v2.css?v=20260718-2">
</head>
<body class="landing-body" data-motion="full">
    <div class="page-noise" aria-hidden="true"></div>
    <div class="moving-word moving-word-one" aria-hidden="true">COURSEHUB</div>
    <div class="moving-word moving-word-two" aria-hidden="true">LEARNING</div>

    <header class="public-header">
        <div class="header-utility" aria-hidden="true"><span>01 HOME</span><span>02 CATALOG</span></div>
        <a class="public-brand" href="/" aria-label="CourseHub home"><span class="brand-monogram">CH</span><span>CourseHub</span></a>
        <div class="header-account"><a href="/learn/sign-in">Sign in</a><a class="student-join" href="/register/student">Join</a></div>
        <button class="menu-button" type="button" aria-label="Open navigation" aria-expanded="false"><span></span><span></span></button>
        <nav class="primary-nav" aria-label="Primary navigation">
            <a href="/courses">Courses</a>
            <a href="#categories">Categories</a>
            <a href="#method">How it works</a>
            <a href="/about">About</a>
            <a href="/contact">Contact</a>
        </nav>
    </header>

    <?= $serviceNotice ?>

    <main>
        <section class="hero" id="top" data-hero-scrub>
            <div class="hero-intro reveal">
                <div class="hero-kicker"><span>CURATED LEARNING</span><b>FOR PRACTICAL PROGRESS</b></div>
                <h1>Build skills for<br><em>what comes next.</em></h1>
                <p>Clear courses, trusted instructors and lifetime access, arranged around one simple goal: helping students make useful progress.</p>
            </div>

            <div class="hero-stage reveal" data-hero-stage>
                <a class="hero-media" href="<?= $escape($heroHref) ?>" aria-label="Open featured course">
                    <?= $heroMedia ?>
                    <span class="hero-image-shade" aria-hidden="true"></span>
                </a>
                <div class="hero-stage-copy">
                    <span><?= $heroCategory ?></span>
                    <h2><?= $heroTitle ?></h2>
                    <p>With <?= $heroInstructor ?></p>
                </div>
                <a class="round-link" href="<?= $escape($heroHref) ?>" aria-label="View featured course">↗</a>
                <div class="hero-index"><span>FEATURED</span><b>01</b></div>
            </div>

            <form class="course-finder reveal" action="/courses" method="get">
                <div class="finder-progress" aria-hidden="true"></div>
                <div class="finder-main">
                    <label for="course-query">What do you want to learn?</label>
                    <input id="course-query" name="q" type="search" placeholder="Search a skill, subject or course" autocomplete="off">
                </div>
                <div class="finder-select">
                    <label for="course-category">Direction</label>
                    <select id="course-category" name="category"><?= $categoryOptions ?></select>
                </div>
                <div class="finder-select">
                    <label for="course-level">Level</label>
                    <select id="course-level" name="level">
                        <option value="">All levels</option>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <button class="finder-submit" type="submit"><span>Find courses</span><i aria-hidden="true">→</i></button>
            </form>
        </section>

        <section class="chapter-scroll" data-chapter-scroll aria-label="CourseHub learning experience">
            <div class="chapter-sticky">
                <div class="chapter-rings" aria-hidden="true"><i></i><i></i><i></i></div>
                <div class="chapter-copy">
                    <span>THE COURSEHUB EXPERIENCE</span>
                    <h2>Learning,<br><em>without friction.</em></h2>
                    <p>Keep scrolling to move through the complete student journey.</p>
                </div>
                <div class="chapter-progress" aria-hidden="true"><i></i></div>
            </div>
        </section>

        <section class="story-scroll" id="method" data-story-scroll>
            <div class="story-sticky">
                <div class="story-heading">
                    <span>ONE CONNECTED JOURNEY</span>
                    <h2>From curiosity<br><em>to real progress.</em></h2>
                </div>

                <div class="story-stage" aria-live="polite">
                    <article class="story-card story-card-one" data-story-card>
                        <div class="story-card-copy">
                            <span>01 · DISCOVER</span>
                            <h3>Choose with context.</h3>
                            <p>Search by subject, compare course details and understand the level before committing.</p>
                        </div>
                        <div class="story-ui story-search-ui" aria-hidden="true">
                            <div class="story-ui-top"><b>CourseHub</b><i></i><i></i></div>
                            <div class="story-search-line"><span></span><strong>Search a skill or subject</strong></div>
                            <div class="story-result-grid"><i></i><i></i><i></i><i></i></div>
                        </div>
                    </article>

                    <article class="story-card story-card-two" data-story-card>
                        <div class="story-card-copy">
                            <span>02 · ENROLL</span>
                            <h3>Purchase with clarity.</h3>
                            <p>See the price, complete the secure flow and keep a clear record of what you bought.</p>
                        </div>
                        <div class="story-ui story-checkout-ui" aria-hidden="true">
                            <div class="checkout-course"><span></span><div><b>Your selected course</b><small>Lifetime access</small></div></div>
                            <div class="checkout-row"><span>Course price</span><b>NPR 2,499</b></div>
                            <div class="checkout-row"><span>Access</span><b>Lifetime</b></div>
                            <div class="checkout-button">Confirm enrollment <i>→</i></div>
                        </div>
                    </article>

                    <article class="story-card story-card-three" data-story-card>
                        <div class="story-card-copy">
                            <span>03 · LEARN</span>
                            <h3>See your momentum.</h3>
                            <p>Lessons, progress and completed work stay connected, so the next step is always visible.</p>
                        </div>
                        <div class="story-ui story-progress-ui" aria-hidden="true">
                            <div class="progress-orbit"><i></i><i></i><strong>68%</strong></div>
                            <div class="lesson-lines"><span></span><span></span><span></span><span></span></div>
                        </div>
                    </article>
                </div>

                <div class="story-footer">
                    <div class="story-count"><strong data-story-index>01</strong><span>/ 03</span></div>
                    <div class="story-line"><i></i></div>
                    <span>Scroll up or down</span>
                </div>
            </div>
        </section>

        <section class="manifesto reveal" data-scrub-section>
            <div class="manifesto-heading"><span>ONE PLATFORM, LESS FRICTION</span><h2>A learning journey designed to stay clear.</h2></div>
            <div class="manifesto-grid">
                <article class="manifesto-card manifesto-card-wide">
                    <span class="card-number">01</span>
                    <div><h3>Choose with context.</h3><p>Preview the course, understand the structure, check the level and know what you are paying for before you begin.</p></div>
                </article>
                <article class="manifesto-card manifesto-card-blue"><span class="card-number">02</span><div><h3>Learn at your pace.</h3><p>Purchased courses remain available for life, so progress can follow your schedule instead of fighting it.</p></div></article>
                <article class="manifesto-card manifesto-card-image"><div class="soft-orbit" aria-hidden="true"><i></i><i></i><b>CH</b></div><span>Focused by design</span></article>
                <article class="manifesto-card manifesto-card-dark"><span class="card-number">03</span><div><h3>See momentum.</h3><p>Lessons and progress stay connected, making the next useful step easier to see.</p></div></article>
            </div>
        </section>

        <section class="category-section reveal" id="categories" data-scrub-section>
            <div class="section-heading editorial-heading"><div><span>DISCOVER BY DIRECTION</span><h2>Start with a subject.<br><em>Build from there.</em></h2></div><p>Move through the live categories and open the path that fits your next goal.</p></div>
            <div class="category-shell">
                <button class="rail-control rail-prev" type="button" aria-label="Previous categories">←</button>
                <div class="category-grid" data-drag-scroll><?= $categoryPills ?></div>
                <button class="rail-control rail-next" type="button" aria-label="Next categories">→</button>
            </div>
        </section>

        <section class="featured-section reveal" data-scrub-section>
            <div class="section-heading editorial-heading"><div><span>LIVE COURSE CATALOG</span><h2>Courses chosen for<br><em>useful progress.</em></h2></div><a class="text-link" href="/courses">See the complete catalog <span>↗</span></a></div>
            <div class="course-grid" data-course-rail><?= $courseCards ?></div>
            <div class="course-rail-controls"><button type="button" data-course-prev aria-label="Previous courses">←</button><span><i></i></span><button type="button" data-course-next aria-label="Next courses">→</button></div>
        </section>

        <section class="proof-section reveal" data-scrub-section>
            <div class="proof-profile"><span class="profile-mark">CH</span><div><strong>Built around the student</strong><small>CourseHub learning experience</small></div></div>
            <blockquote>“Good learning platforms do not demand attention for themselves. They make the subject easier to understand, the next step easier to find, and progress easier to continue.”</blockquote>
            <div class="proof-rule"></div>
            <div class="proof-metrics"><div><strong>Lifetime</strong><span>Access to every purchased course</span></div><div><strong>Clear</strong><span>Course details before purchase</span></div><div><strong>Visible</strong><span>Lesson and progress tracking</span></div></div>
        </section>

        <section class="closing-section reveal" data-scrub-section>
            <div class="closing-art" aria-hidden="true"><div class="closing-device"><span>CH</span><i></i><i></i><i></i></div></div>
            <div class="closing-copy"><span>YOUR NEXT CHAPTER</span><h2>Begin with one course.<br><em>Keep the skill.</em></h2><p>Create a student account, choose a course and build at a pace that lasts.</p><a href="/register/student">Create your student account <b>→</b></a></div>
        </section>
    </main>

    <footer class="public-footer">
        <a class="footer-brand" href="/"><span class="brand-monogram">CH</span><span>CourseHub</span></a>
        <div><span>EXPLORE</span><a href="/courses">Courses</a><a href="#categories">Categories</a><a href="/about">About</a></div>
        <div><span>STUDENT</span><a href="/learn/sign-in">Sign in</a><a href="/register/student">Create account</a><a href="/contact">Support</a></div>
        <div><span>LEGAL</span><a href="/privacy">Privacy</a><a href="/terms">Terms</a></div>
        <p>© <?= date('Y') ?> CourseHub. Practical learning, arranged with care.</p>
    </footer>

    <script src="/room-assets/Public/Landing/page-v2.js?v=20260718-2" defer></script>
</body>
</html>
        <?php
        $html = ob_get_clean();
        return Response::html($html === false ? '' : $html);
    }
}
