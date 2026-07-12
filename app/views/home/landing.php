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
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=25">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.real-book-stage{position:relative;min-height:610px;display:grid;place-items:center;overflow:visible}
.real-book-object{position:relative;z-index:2;width:min(500px,96%);animation:realBookFloat 4.8s ease-in-out infinite}
.real-book-object img{display:block;width:100%;height:auto;max-height:610px;object-fit:contain;object-position:center;filter:drop-shadow(0 24px 30px rgba(34,22,13,.2))}
@keyframes realBookFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
@media(max-width:980px){.real-book-stage{min-height:560px}.real-book-object{width:min(460px,94%)}}
@media(max-width:620px){.real-book-stage{min-height:470px}.real-book-object{width:min(360px,96%)}.real-book-object img{max-height:460px}}
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
                    <img id="realBookHero" alt="A real hand turning the page of an open book">
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

<script>
(function () {
    const chunks = [
        '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'
    ];
    const image = document.getElementById('realBookHero');
    if (image) image.src = 'data:image/webp;base64,' + chunks.join('');
})();
</script>
