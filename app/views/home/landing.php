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
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=24">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.real-book-stage{position:relative;min-height:610px;display:grid;place-items:center;overflow:visible}
.real-book-stage::before{content:"";position:absolute;width:78%;height:78%;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.72),rgba(238,230,217,.2) 56%,transparent 76%);filter:blur(18px)}
.real-book-object{position:relative;z-index:2;width:min(480px,94%);animation:realBookFloat 4.8s ease-in-out infinite}
.real-book-object img{display:block;width:100%;height:auto;max-height:610px;object-fit:contain;object-position:center;filter:drop-shadow(0 24px 30px rgba(34,22,13,.22))}
@keyframes realBookFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
@media(max-width:980px){.real-book-stage{min-height:560px}.real-book-object{width:min(440px,92%)}}
@media(max-width:620px){.real-book-stage{min-height:460px}.real-book-object{width:min(350px,94%)}.real-book-object img{max-height:450px}}
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
                    <img src="data:image/webp;base64,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...TRUNCATED..." alt="A real hand turning the page of an open book">
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
