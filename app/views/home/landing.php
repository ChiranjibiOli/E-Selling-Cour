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
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=19">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.knowledge-book-visual{position:relative;min-height:520px;overflow:hidden;border-radius:30px;background:#2b180d;box-shadow:0 32px 80px rgba(38,24,13,.24)}
.knowledge-book-visual img{width:100%;height:520px;display:block;object-fit:cover;object-position:center 43%;filter:saturate(.94) contrast(1.04)}
.knowledge-book-visual::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,8,3,.05),rgba(15,8,3,.22));pointer-events:none}
.knowledge-book-title{position:absolute;z-index:2;top:24%;left:50%;width:58%;transform:translateX(-50%);padding:19px 14px 17px;text-align:center;background:linear-gradient(180deg,rgba(31,16,9,.94),rgba(25,13,7,.97));box-shadow:0 12px 28px rgba(0,0,0,.28);color:#d7a657;font-family:Georgia,"Times New Roman",serif;font-size:clamp(1rem,2vw,1.5rem);font-weight:600;letter-spacing:.18em;line-height:1.25;text-transform:uppercase}
.knowledge-book-title small{display:block;margin-bottom:8px;color:#a97839;font-family:Arial,sans-serif;font-size:.48rem;font-weight:800;letter-spacing:.28em}
.knowledge-book-title span{display:block;margin-top:10px;font-family:Arial,sans-serif;font-size:.5rem;font-weight:800;letter-spacing:.2em;color:#9d6f35}
@media(max-width:980px){.knowledge-book-visual,.knowledge-book-visual img{min-height:470px;height:470px}}
@media(max-width:620px){.knowledge-book-visual{min-height:390px;border-radius:22px}.knowledge-book-visual img{height:390px;min-height:390px}.knowledge-book-title{top:23%;width:62%;padding:13px 10px;font-size:.92rem}}
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

            <div class="knowledge-book-visual" aria-label="A real hand holding the Book of Knowledge">
                <img src="data:image/webp;base64,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" alt="A real hand holding a dark hardcover book">
                <div class="knowledge-book-title"><small>THE</small>BOOK OF KNOWLEDGE<span>LEARN · BUILD · GROW</span></div>
            </div>
        </div>
    </section>

    <section class="editorial-courses">
        <div class="container">
            <div class="editorial-section-heading">
                <h2>Featured learning,<br>presented like a collection.</h2>
                <p>Courses unfold as layered editorial sheets while you scroll, preserving the clean visual direction from the reference.</p>
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
            <div class="editorial-directory-intro"><h2>Everything important has its own page.</h2><p>Students can move from discovery to course browsing, instructor profiles, payment guidance, and account creation without dead navigation.</p></div>
            <div class="editorial-directory-grid">
                <article><span>Courses</span><h3>Browse and filter</h3><p>Search, sort, change view, and filter by category and level.</p><a href="courses.php">Open courses</a></article>
                <article><span>Process</span><h3>Understand access</h3><p>See how payment verification and lifetime access work.</p><a href="how-it-works.php">See process</a></article>
                <article><span>Instructors</span><h3>Teach with structure</h3><p>Create course content, submit it for review, and manage enrolled students.</p><a href="register.php?role=instructor">Become instructor</a></article>
                <article><span>Account</span><h3>Continue learning</h3><p>Sign in to access purchases, learning progress, and notifications.</p><a href="login.php">Log in</a></article>
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
