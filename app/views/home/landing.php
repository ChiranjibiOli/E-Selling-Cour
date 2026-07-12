<?php

require_once __DIR__ . '/../../config/database.php';

$featuredCourses = [];
$stats = ['students' => 0, 'courses' => 0, 'instructors' => 0, 'enrollments' => 0];

function landing_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function landing_price(mixed $price): string
{
    return 'Rs. ' . number_format((float) $price, 0);
}

function landing_thumbnail(array $course): string
{
    $publicRoot = defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
    $thumbnailPath = 'assets/images/course-placeholder.svg';

    if (!empty($course['thumbnail'])) {
        $candidate = 'assets/uploads/course_thumbnails/' . basename((string) $course['thumbnail']);
        $fullPath = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (is_file($fullPath)) {
            $thumbnailPath = $candidate;
        }
    }

    return $thumbnailPath;
}

$featuredSql = "
    SELECT c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
           c.level, c.duration, c.language, cat.name AS category_name,
           u.full_name AS instructor_name,
           (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON s.id = l.section_id WHERE s.course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count
    FROM courses c
    INNER JOIN users u ON c.instructor_id = u.id
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE c.status = 'published'
    ORDER BY c.is_featured DESC, c.created_at DESC
    LIMIT 3
";

$featuredResult = $conn->query($featuredSql);
while ($featuredResult && $row = $featuredResult->fetch_assoc()) {
    $featuredCourses[] = $row;
}

$statQueries = [
    'students' => "SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'active'",
    'courses' => "SELECT COUNT(*) AS total FROM courses WHERE status = 'published'",
    'instructors' => "SELECT COUNT(*) AS total FROM users WHERE role = 'instructor' AND status = 'active'",
    'enrollments' => "SELECT COUNT(*) AS total FROM enrollments WHERE status = 'active'",
];

foreach ($statQueries as $key => $query) {
    $result = $conn->query($query);
    $stats[$key] = $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
}
?>
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=14">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=27">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.real-book-stage{position:relative;min-height:610px;display:grid;place-items:center;overflow:visible;background:transparent}
.real-book-object{position:relative;z-index:2;width:min(500px,92%);height:540px;overflow:hidden;animation:realBookFloat 4.8s ease-in-out infinite}
.real-book-object img{display:block;width:100%;height:auto;max-height:610px;transform:translateY(-10px);object-fit:contain;object-position:top center;background:transparent;filter:drop-shadow(0 18px 24px rgba(34,22,13,.16))}
@keyframes realBookFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.landing-course-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;margin-top:28px}
@media(max-width:980px){.real-book-stage{min-height:560px}.real-book-object{width:min(470px,96%)}.landing-course-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.landing-course-grid{grid-template-columns:1fr}}
@media(max-width:620px){.real-book-stage{min-height:470px}.real-book-object{width:min(370px,98%)}.real-book-object img{max-height:460px}}
@media(prefers-reduced-motion:reduce){.real-book-object{animation:none}}
</style>

<main class="landing-page">
    <section class="editorial-hero">
        <div class="container editorial-hero-grid">
            <div class="editorial-hero-copy">
                <span class="editorial-kicker">Learn from the best</span>
                <h1>Education<br>that <em>transforms</em><br>your life.</h1>
                <p>Handpicked courses from approved instructors, designed for real progress. Purchase once, complete payment verification, and keep lifetime access.</p>
                <div class="editorial-actions">
                    <a class="editorial-button editorial-button-gold" href="courses.php">Discover courses</a>
                    <a class="editorial-button editorial-button-light" href="how-it-works.php">How it works</a>
                </div>
            </div>

            <div class="real-book-stage" aria-label="A real hand turning the page of an open book">
                <div class="real-book-object">
                    <img src="assets/images/image.png" alt="A real hand turning the page of an open book">
                </div>
            </div>
        </div>
    </section>

    <section class="editorial-courses">
        <div class="container">
            <div class="editorial-section-heading">
                <h2>Featured learning,<br>presented like a collection.</h2>
                <p>Explore approved courses built for practical learning and lifetime access.</p>
            </div>

            <?php if ($featuredCourses): ?>
                <div class="landing-course-grid">
                    <?php foreach ($featuredCourses as $course): ?>
                        <?php
                        $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $course['slug']);
                        $courseCard = [
                            'context' => 'marketplace',
                            'title' => $course['title'],
                            'summary' => $course['short_description'] ?: 'Explore the complete course and its learning outcomes.',
                            'thumbnail' => landing_thumbnail($course),
                            'category' => $course['category_name'] ?: 'General',
                            'badge' => ucfirst((string) $course['level']),
                            'eyebrow' => 'By ' . $course['instructor_name'],
                            'language' => $course['language'] ?: 'Language not set',
                            'duration' => $course['duration'] ?: 'Self-paced',
                            'price' => landing_price($course['price']),
                            'href' => $detailsUrl,
                            'metrics' => [
                                ['label' => 'Lessons', 'value' => (string) ((int) $course['lesson_count'])],
                                ['label' => 'Students', 'value' => number_format((int) $course['student_count'])],
                            ],
                            'actions' => [
                                ['label' => 'View course', 'href' => $detailsUrl, 'style' => 'primary'],
                            ],
                        ];
                        require __DIR__ . '/../components/course_card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <article class="editorial-empty-state"><span>Courses are being prepared</span><h3>Published courses will appear here.</h3><p>Explore the full catalogue or return after instructors publish approved courses.</p><a class="editorial-button editorial-button-dark" href="courses.php">Open courses</a></article>
            <?php endif; ?>
        </div>
    </section>

    <section class="editorial-directory">
        <div class="container">
            <div class="editorial-directory-intro"><h2>Everything important has its own page.</h2><p>Students can move from discovery to browsing, payment guidance, and account creation without dead navigation.</p></div>
            <div class="editorial-directory-grid">
                <article><span>Courses</span><h3>Browse and filter</h3><p>Search, sort, change view, and filter by category and level.</p><a href="courses.php">Open courses</a></article>
                <article><span>Process</span><h3>Understand access</h3><p>See how payment verification and lifetime access work.</p><a href="how-it-works.php">See process</a></article>
                <article><span>Instructors</span><h3>Teach with structure</h3><p>Create course content, submit it for review, and manage enrolled students.</p><a href="register.php?role=instructor">Become instructor</a></article>
                <article><span>Account</span><h3>Continue learning</h3><p>Sign in to access purchases, progress, and notifications.</p><a href="login.php">Log in</a></article>
            </div>
        </div>
    </section>

    <section class="editorial-stats">
        <div class="container editorial-stats-grid">
            <div><strong><?php echo number_format($stats['students']); ?></strong><span>Students</span></div>
            <div><strong><?php echo number_format($stats['courses']); ?></strong><span>Courses</span></div>
            <div><strong><?php echo number_format($stats['instructors']); ?></strong><span>Instructors</span></div>
            <div><strong><?php echo number_format($stats['enrollments']); ?></strong><span>Enrollments</span></div>
        </div>
    </section>
</main>