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
           c.level, c.duration, c.language, u.full_name AS instructor_name
    FROM courses c
    INNER JOIN users u ON c.instructor_id = u.id
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
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=26">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.real-book-stage{position:relative;min-height:610px;display:grid;place-items:center;overflow:visible;background:transparent}
.real-book-object{position:relative;z-index:2;width:min(520px,98%);animation:realBookFloat 4.8s ease-in-out infinite}
.real-book-object img{display:block;width:100%;height:auto;max-height:610px;object-fit:contain;object-position:center;background:transparent;filter:drop-shadow(0 24px 30px rgba(34,22,13,.18))}
@keyframes realBookFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
@media(max-width:980px){.real-book-stage{min-height:560px}.real-book-object{width:min(470px,96%)}}
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
                    <img src="data:image/webp;base64,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" alt="A real hand turning the page of an open book">
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
            <div class="editorial-course-list">
                <?php if ($featuredCourses): ?>
                    <?php foreach ($featuredCourses as $index => $course): ?>
                        <article class="editorial-course-card editorial-tone-<?php echo ($index % 3) + 1; ?>" style="--stack-index: <?php echo $index; ?>;">
                            <div class="editorial-course-copy">
                                <span class="editorial-course-number"><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?> · <?php echo landing_h(strtoupper((string) $course['level'])); ?></span>
                                <h3><?php echo landing_h($course['title']); ?></h3>
                                <p><?php echo landing_h($course['short_description']); ?></p>
                                <div class="editorial-course-meta"><span><?php echo landing_h($course['instructor_name']); ?></span><span><?php echo landing_price($course['price']); ?></span></div>
                                <a class="editorial-button editorial-button-dark" href="course-details.php?slug=<?php echo urlencode((string) $course['slug']); ?>">View course</a>
                            </div>
                            <a class="editorial-course-art" href="course-details.php?slug=<?php echo urlencode((string) $course['slug']); ?>" aria-label="View <?php echo landing_h($course['title']); ?>">
                                <img src="<?php echo landing_h(landing_thumbnail($course)); ?>" alt="<?php echo landing_h($course['title']); ?>">
                                <span><?php echo landing_h(strtoupper(substr((string) $course['title'], 0, 1))); ?></span>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <article class="editorial-empty-state"><span>Courses are being prepared</span><h3>Published courses will appear here.</h3><p>Explore the full catalogue or return after instructors publish approved courses.</p><a class="editorial-button editorial-button-dark" href="courses.php">Open courses</a></article>
                <?php endif; ?>
            </div>
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
