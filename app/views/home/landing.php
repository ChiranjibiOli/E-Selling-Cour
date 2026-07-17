<?php

require_once __DIR__ . '/../../config/database.php';

$conn = database_connection();
$landingCategories = [];
$featuredCourses = [];
$stats = ['students' => 0, 'courses' => 0, 'enrollments' => 0];

function landing_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function landing_category_mark(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $mark = '';

    foreach (array_slice($words, 0, 2) as $word) {
        $mark .= function_exists('mb_substr')
            ? mb_substr($word, 0, 1, 'UTF-8')
            : substr($word, 0, 1);
    }

    return strtoupper($mark !== '' ? $mark : 'C');
}

try {
    $categoryResult = $conn->query("
        SELECT
            cat.id,
            cat.name,
            cat.slug,
            cat.description,
            cat.created_at,
            COUNT(DISTINCT c.id) AS published_course_count,
            COUNT(DISTINCT c.instructor_id) AS instructor_count
        FROM categories cat
        LEFT JOIN courses c
            ON c.category_id = cat.id
           AND c.status = 'published'
        WHERE cat.status = 'active'
        GROUP BY cat.id, cat.name, cat.slug, cat.description, cat.created_at
        ORDER BY published_course_count DESC, cat.created_at DESC, cat.name ASC
        LIMIT 6
    ");

    while ($categoryResult && $row = $categoryResult->fetch_assoc()) {
        $landingCategories[] = $row;
    }
} catch (Throwable $exception) {
    error_log('Landing category query failed: ' . $exception->getMessage());
}

try {
    $featuredResult = $conn->query("
        SELECT
            c.id,
            c.title,
            c.slug,
            c.short_description,
            c.thumbnail,
            c.price,
            c.level,
            c.duration,
            c.language,
            c.is_featured,
            c.updated_at,
            cat.name AS category_name,
            u.full_name AS instructor_name,
            COALESCE(AVG(r.rating), 0) AS average_rating,
            COUNT(r.id) AS review_count
        FROM courses c
        INNER JOIN users u
            ON u.id = c.instructor_id
           AND u.role = 'instructor'
           AND u.status = 'active'
        INNER JOIN categories cat
            ON cat.id = c.category_id
           AND cat.status = 'active'
        LEFT JOIN reviews r
            ON r.course_id = c.id
           AND r.status = 'visible'
        WHERE c.status = 'published'
        GROUP BY
            c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
            c.level, c.duration, c.language, c.is_featured, c.updated_at,
            cat.name, u.full_name
        ORDER BY c.is_featured DESC, c.updated_at DESC, c.id DESC
        LIMIT 3
    ");

    while ($featuredResult && $row = $featuredResult->fetch_assoc()) {
        $featuredCourses[] = $row;
    }
} catch (Throwable $exception) {
    error_log('Landing featured-course query failed: ' . $exception->getMessage());
}

$statQueries = [
    'students' => "SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'active'",
    'courses' => "SELECT COUNT(*) AS total FROM courses WHERE status = 'published'",
    'enrollments' => "SELECT COUNT(*) AS total FROM enrollments WHERE status = 'active'",
];

foreach ($statQueries as $key => $query) {
    try {
        $result = $conn->query($query);
        $stats[$key] = $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
    } catch (Throwable $exception) {
        error_log('Landing statistic query failed for ' . $key . ': ' . $exception->getMessage());
        $stats[$key] = 0;
    }
}
?>
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=18">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=31">
<link rel="stylesheet" href="assets/css/components/footer.css?v=12">
<script src="assets/js/landing.js?v=1" defer></script>

<main class="landing-page">
    <section class="luxury-hero" aria-labelledby="landing-title">
        <div class="luxury-hero-glow" data-parallax data-parallax-speed="-0.08" aria-hidden="true"></div>
        <div class="luxury-hero-grid landing-shell">
            <div class="luxury-hero-copy reveal-on-scroll">
                <p class="luxury-eyebrow"><span>CourseHub</span> Curated digital learning</p>
                <h1 id="landing-title">Learn what<br><em>moves you</em><br>forward.</h1>
                <p class="luxury-hero-intro">Approved instructors. Carefully reviewed courses. One purchase, lifetime access, and a learning experience that respects your time.</p>
                <div class="luxury-actions">
                    <a class="luxury-button luxury-button-primary" href="courses.php">Explore courses <span aria-hidden="true">↗</span></a>
                    <a class="luxury-text-link" href="how-it-works.php">See how access works <span aria-hidden="true">→</span></a>
                </div>
                <dl class="luxury-hero-stats" aria-label="Platform statistics">
                    <div><dt><?php echo number_format($stats['courses']); ?></dt><dd>Published courses</dd></div>
                    <div><dt><?php echo number_format($stats['students']); ?></dt><dd>Active learners</dd></div>
                    <div><dt>Lifetime</dt><dd>Course access</dd></div>
                </dl>
            </div>

            <div class="luxury-hero-visual reveal-on-scroll" data-parallax data-parallax-speed="0.07">
                <div class="luxury-visual-frame">
                    <img src="assets/images/image.png" alt="An open book representing focused learning">
                    <div class="luxury-visual-shade" aria-hidden="true"></div>
                    <div class="luxury-visual-caption">
                        <span>01 / The learning object</span>
                        <strong>Knowledge, presented with intention.</strong>
                    </div>
                </div>
                <div class="luxury-orbit luxury-orbit-one" aria-hidden="true"></div>
                <div class="luxury-orbit luxury-orbit-two" aria-hidden="true"></div>
            </div>
        </div>
        <a class="luxury-scroll-cue" href="#manifesto"><span>Scroll to discover</span><i aria-hidden="true"></i></a>
    </section>

    <section class="luxury-marquee" aria-label="CourseHub promises">
        <div class="luxury-marquee-track">
            <span>Verified instructors</span><i></i><span>Practical learning</span><i></i><span>Lifetime ownership</span><i></i><span>Secure access</span><i></i>
            <span aria-hidden="true">Verified instructors</span><i aria-hidden="true"></i><span aria-hidden="true">Practical learning</span><i aria-hidden="true"></i><span aria-hidden="true">Lifetime ownership</span><i aria-hidden="true"></i><span aria-hidden="true">Secure access</span><i aria-hidden="true"></i>
        </div>
    </section>

    <section class="luxury-manifesto" id="manifesto">
        <div class="landing-shell luxury-manifesto-grid">
            <div class="luxury-manifesto-label reveal-on-scroll">
                <span>Our standard</span>
                <small>02 / Built for serious progress</small>
            </div>
            <div class="luxury-manifesto-copy reveal-on-scroll">
                <p>Education should feel valuable <em>before</em> you buy it.</p>
                <p class="luxury-manifesto-note">That means clear information, trusted instructors, transparent access, and no theatrical clutter standing between a learner and the right course.</p>
            </div>
        </div>
    </section>

    <section class="luxury-categories" id="categories">
        <div class="landing-shell">
            <header class="luxury-section-heading reveal-on-scroll">
                <div>
                    <span class="luxury-section-index">03 / Disciplines</span>
                    <h2>Choose a direction.<br>Then go deeper.</h2>
                </div>
                <p>Active categories are pulled directly from the platform. Every published course remains connected to the same approval and access rules already used by your system.</p>
            </header>

            <?php if ($landingCategories): ?>
                <div class="luxury-category-stack">
                    <?php foreach ($landingCategories as $index => $category): ?>
                        <?php
                        $courseCount = (int) $category['published_course_count'];
                        $instructorCount = (int) $category['instructor_count'];
                        $description = trim((string) ($category['description'] ?? ''));
                        if ($description === '') {
                            $description = 'A focused collection of current and upcoming learning experiences in ' . $category['name'] . '.';
                        }
                        ?>
                        <article class="luxury-category-card reveal-on-scroll" style="--stack-index:<?php echo (int) $index; ?>;" data-stack-card>
                            <div class="luxury-category-copy">
                                <div class="luxury-category-topline">
                                    <span><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                                    <span><?php echo number_format($courseCount); ?> course<?php echo $courseCount === 1 ? '' : 's'; ?></span>
                                </div>
                                <h3><?php echo landing_h($category['name']); ?></h3>
                                <p><?php echo landing_h($description); ?></p>
                                <div class="luxury-category-footer">
                                    <span><?php echo number_format($instructorCount); ?> verified instructor<?php echo $instructorCount === 1 ? '' : 's'; ?></span>
                                    <a href="courses.php?category_id=<?php echo (int) $category['id']; ?>">Explore category <span aria-hidden="true">↗</span></a>
                                </div>
                            </div>
                            <div class="luxury-category-art" data-parallax data-parallax-speed="0.035" aria-hidden="true">
                                <span class="luxury-category-mark"><?php echo landing_h(landing_category_mark((string) $category['name'])); ?></span>
                                <span class="luxury-category-ring"></span>
                                <small>CourseHub / <?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <article class="luxury-empty-state reveal-on-scroll">
                    <span>Categories are being prepared</span>
                    <h3>The collection is taking shape.</h3>
                    <p>Active categories will appear automatically when they are available.</p>
                    <a class="luxury-button luxury-button-primary" href="courses.php">Open course catalog</a>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($featuredCourses): ?>
        <section class="luxury-featured">
            <div class="landing-shell">
                <header class="luxury-section-heading luxury-section-heading-light reveal-on-scroll">
                    <div>
                        <span class="luxury-section-index">04 / Selected courses</span>
                        <h2>Made to be used,<br>not merely watched.</h2>
                    </div>
                    <a class="luxury-text-link luxury-text-link-light" href="courses.php">View every course <span aria-hidden="true">→</span></a>
                </header>

                <div class="luxury-course-grid">
                    <?php foreach ($featuredCourses as $index => $course): ?>
                        <?php
                        $thumbnail = basename((string) ($course['thumbnail'] ?? ''));
                        $thumbnailPath = $thumbnail !== ''
                            ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail)
                            : 'assets/images/course-placeholder.svg';
                        if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
                            $thumbnailPath = 'assets/images/course-placeholder.svg';
                        }
                        $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $course['slug']);
                        $rating = (float) $course['average_rating'];
                        ?>
                        <article class="luxury-course-card reveal-on-scroll">
                            <a class="luxury-course-image" href="<?php echo landing_h($detailsUrl); ?>" aria-label="View <?php echo landing_h($course['title']); ?>">
                                <img src="<?php echo landing_h($thumbnailPath); ?>" alt="">
                                <span><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                            </a>
                            <div class="luxury-course-meta">
                                <span><?php echo landing_h($course['category_name']); ?></span>
                                <span><?php echo landing_h(ucfirst((string) $course['level'])); ?></span>
                            </div>
                            <h3><a href="<?php echo landing_h($detailsUrl); ?>"><?php echo landing_h($course['title']); ?></a></h3>
                            <p><?php echo landing_h($course['short_description'] ?: 'A structured course designed for practical, self-paced learning.'); ?></p>
                            <div class="luxury-course-bottom">
                                <span>By <?php echo landing_h($course['instructor_name']); ?></span>
                                <strong><?php echo (float) $course['price'] > 0 ? 'Rs. ' . number_format((float) $course['price'], 0) : 'Free'; ?></strong>
                            </div>
                            <div class="luxury-course-rating">
                                <span><?php echo $rating > 0 ? number_format($rating, 1) . ' / 5' : 'New course'; ?></span>
                                <span><?php echo number_format((int) $course['review_count']); ?> review<?php echo (int) $course['review_count'] === 1 ? '' : 's'; ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="luxury-process">
        <div class="landing-shell">
            <header class="luxury-section-heading reveal-on-scroll">
                <div>
                    <span class="luxury-section-index">05 / The process</span>
                    <h2>Simple where it matters.</h2>
                </div>
                <p>The public experience remains connected to the real workflow: discover, purchase, verify payment, and learn with lifetime access.</p>
            </header>

            <div class="luxury-process-grid">
                <article class="reveal-on-scroll"><span>01</span><h3>Discover</h3><p>Browse published courses from active, approved instructors.</p></article>
                <article class="reveal-on-scroll"><span>02</span><h3>Purchase</h3><p>Choose a course, complete checkout, and submit payment through the supported flow.</p></article>
                <article class="reveal-on-scroll"><span>03</span><h3>Own the access</h3><p>Once payment is verified, the course stays available for lifetime learning.</p></article>
            </div>
        </div>
    </section>

    <section class="luxury-final-cta">
        <div class="luxury-final-cta-orb" data-parallax data-parallax-speed="-0.05" aria-hidden="true"></div>
        <div class="landing-shell luxury-final-cta-inner reveal-on-scroll">
            <span>CourseHub / Begin with intention</span>
            <h2>Your next skill should<br>change what comes next.</h2>
            <div class="luxury-actions luxury-actions-centered">
                <a class="luxury-button luxury-button-ivory" href="courses.php">Find your course <span aria-hidden="true">↗</span></a>
                <a class="luxury-text-link luxury-text-link-light" href="register.php?role=instructor">Teach on CourseHub <span aria-hidden="true">→</span></a>
            </div>
        </div>
    </section>
</main>
