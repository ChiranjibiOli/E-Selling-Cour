<?php

require_once __DIR__ . '/../../config/database.php';

$conn = database_connection();
$landingCategories = [];
$stats = ['students' => 0, 'courses' => 0, 'instructors' => 0, 'enrollments' => 0];

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
        ORDER BY cat.created_at DESC, published_course_count DESC, cat.name ASC
        LIMIT 6
    ");

    while ($categoryResult && $row = $categoryResult->fetch_assoc()) {
        $landingCategories[] = $row;
    }
} catch (Throwable $exception) {
    error_log('Landing category query failed: ' . $exception->getMessage());
}

$statQueries = [
    'students' => "SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'active'",
    'courses' => "SELECT COUNT(*) AS total FROM courses WHERE status = 'published'",
    'instructors' => "SELECT COUNT(*) AS total FROM users WHERE role = 'instructor' AND status = 'active'",
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
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=14">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=28">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.real-book-stage{position:relative;min-height:610px;display:grid;place-items:center;overflow:visible;background:transparent}
.real-book-object{position:relative;z-index:2;width:min(500px,92%);height:540px;overflow:hidden;animation:realBookFloat 4.8s ease-in-out infinite}
.real-book-object img{display:block;width:100%;height:auto;max-height:610px;transform:translateY(-10px);object-fit:contain;object-position:top center;background:transparent;filter:drop-shadow(0 18px 24px rgba(34,22,13,.16))}
@keyframes realBookFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

.landing-category-stack{display:grid;gap:0;margin-top:28px;padding-bottom:120px}
.landing-category-card{--category-tone:#f8f1e6;position:sticky;top:calc(94px + (var(--stack-index) * 13px));z-index:calc(20 + var(--stack-index));display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);gap:34px;align-items:stretch;min-height:320px;margin-bottom:54px;padding:38px;border:1px solid rgba(72,58,39,.1);border-radius:28px;background:var(--category-tone);box-shadow:0 26px 66px rgba(39,31,21,.16);transform-origin:center top}
.landing-category-card:nth-child(1){--category-tone:#f8f1e6}
.landing-category-card:nth-child(2){--category-tone:#efe0c8}
.landing-category-card:nth-child(3){--category-tone:#e4cfad}
.landing-category-card:nth-child(4){--category-tone:#d8c09a}
.landing-category-card:nth-child(5){--category-tone:#ccb184}
.landing-category-card:nth-child(6){--category-tone:#bfa16e}
.landing-category-copy{display:flex;min-width:0;flex-direction:column;align-items:flex-start;justify-content:center}
.landing-category-number{display:inline-flex;align-items:center;gap:10px;color:#765018;font-size:.67rem;font-weight:950;letter-spacing:.15em;text-transform:uppercase}
.landing-category-number::before{content:"";width:34px;height:1px;background:#9a6e23}
.landing-category-copy h3{max-width:700px;margin:18px 0 13px;font-family:Georgia,"Times New Roman",serif;font-size:clamp(2.7rem,5vw,5.2rem);font-weight:500;letter-spacing:-.055em;line-height:.92}
.landing-category-copy>p{max-width:590px;margin:0 0 19px;color:#675e53;line-height:1.72}
.landing-category-meta{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:24px}
.landing-category-meta span{display:inline-flex;min-height:31px;align-items:center;padding:0 11px;border:1px solid rgba(39,31,21,.14);border-radius:999px;color:#4f473d;background:rgba(255,255,255,.32);font-size:.7rem;font-weight:850}
.landing-category-art{position:relative;display:grid;min-height:240px;overflow:hidden;place-items:center;border-radius:22px;background:linear-gradient(145deg,#181512,#2d261d);color:#f7eddb;box-shadow:inset 0 0 0 1px rgba(255,255,255,.06)}
.landing-category-art::before,.landing-category-art::after{content:"";position:absolute;border-radius:999px;border:1px solid rgba(216,179,110,.28)}
.landing-category-art::before{width:210px;height:210px;top:-75px;right:-45px}
.landing-category-art::after{width:150px;height:150px;bottom:-62px;left:-42px}
.landing-category-mark{position:relative;z-index:2;font-family:Georgia,"Times New Roman",serif;font-size:clamp(4.8rem,10vw,8.8rem);font-weight:500;letter-spacing:-.08em}
.landing-category-art small{position:absolute;right:18px;bottom:16px;color:#cdbb9d;font-size:.57rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}

@media(max-width:980px){.real-book-stage{min-height:560px}.real-book-object{width:min(470px,96%)}.landing-category-card{position:relative;top:auto;grid-template-columns:1fr;gap:24px;margin-bottom:24px}.landing-category-art{min-height:220px;order:-1}}
@media(max-width:620px){.real-book-stage{min-height:470px}.real-book-object{width:min(370px,98%)}.real-book-object img{max-height:460px}.landing-category-stack{padding-bottom:40px}.landing-category-card{min-height:0;padding:23px;border-radius:22px}.landing-category-copy h3{font-size:clamp(2.35rem,13vw,3.8rem)}.landing-category-art{min-height:180px}.landing-category-mark{font-size:5.4rem}}
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

    <section class="editorial-courses" id="categories">
        <div class="container">
            <div class="editorial-section-heading">
                <h2>Explore learning,<br>one category at a time.</h2>
                <p>Six active categories are presented as an overlapping collection. New categories created from the instructor course builder enter this collection automatically.</p>
            </div>

            <?php if ($landingCategories): ?>
                <div class="landing-category-stack">
                    <?php foreach ($landingCategories as $index => $category): ?>
                        <?php
                        $courseCount = (int) $category['published_course_count'];
                        $instructorCount = (int) $category['instructor_count'];
                        $description = trim((string) ($category['description'] ?? ''));
                        if ($description === '') {
                            $description = 'Discover current and upcoming learning experiences organized under ' . $category['name'] . '.';
                        }
                        ?>
                        <article class="landing-category-card" style="--stack-index:<?php echo (int) $index; ?>">
                            <div class="landing-category-copy">
                                <span class="landing-category-number">Category <?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                                <h3><?php echo landing_h($category['name']); ?></h3>
                                <p><?php echo landing_h($description); ?></p>
                                <div class="landing-category-meta">
                                    <span><?php echo number_format($courseCount); ?> published course<?php echo $courseCount === 1 ? '' : 's'; ?></span>
                                    <span><?php echo number_format($instructorCount); ?> instructor<?php echo $instructorCount === 1 ? '' : 's'; ?></span>
                                    <?php if ($courseCount === 0): ?><span>New category</span><?php endif; ?>
                                </div>
                                <a class="editorial-button editorial-button-dark" href="courses.php?category_id=<?php echo (int) $category['id']; ?>">Browse category</a>
                            </div>
                            <div class="landing-category-art" aria-hidden="true">
                                <span class="landing-category-mark"><?php echo landing_h(landing_category_mark((string) $category['name'])); ?></span>
                                <small>CourseHub category</small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <article class="editorial-empty-state"><span>Categories are being prepared</span><h3>Learning categories will appear here.</h3><p>Instructors can create a category while building a course, and active categories will become visible in this collection.</p><a class="editorial-button editorial-button-dark" href="courses.php">Open courses</a></article>
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
