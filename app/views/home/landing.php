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
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=22">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.knowledge-4d-stage{position:relative;min-height:560px;display:grid;place-items:center;perspective:1500px;overflow:visible;isolation:isolate}
.knowledge-4d-stage::before{content:"";position:absolute;width:430px;height:430px;border-radius:50%;background:radial-gradient(circle,rgba(196,139,45,.18),rgba(196,139,45,.06) 46%,transparent 72%);filter:blur(18px)}
.knowledge-4d-object{position:relative;z-index:2;width:min(390px,88%);transform-style:preserve-3d;animation:bookFloat 4.6s ease-in-out infinite;transition:transform .18s ease-out}
.knowledge-4d-object img{width:100%;display:block;object-fit:contain;filter:drop-shadow(0 28px 34px rgba(32,19,10,.34))}
.knowledge-4d-cover{position:absolute;z-index:4;top:14%;left:50%;width:48%;transform:translateX(-50%) translateZ(42px);padding:14px 10px;text-align:center;color:#e0aa52;text-shadow:0 2px 10px rgba(0,0,0,.62);font-family:Georgia,"Times New Roman",serif;font-size:clamp(.9rem,1.8vw,1.25rem);font-weight:700;letter-spacing:.13em;line-height:1.25;text-transform:uppercase;pointer-events:none}
.knowledge-4d-cover::before{content:"";position:absolute;inset:-10px -8px;border:1px solid rgba(226,174,85,.72);box-shadow:inset 0 0 0 2px rgba(87,48,22,.5)}
.knowledge-4d-cover small{display:block;margin-bottom:8px;font-family:Arial,sans-serif;font-size:.43rem;font-weight:900;letter-spacing:.24em;color:#bd873b}
.knowledge-4d-cover span{display:block;margin-top:10px;font-family:Arial,sans-serif;font-size:.44rem;font-weight:900;letter-spacing:.17em;color:#b47c34}
.knowledge-4d-light{position:absolute;z-index:5;inset:7% 16% 24% 16%;background:linear-gradient(110deg,transparent 30%,rgba(255,229,174,.42) 46%,rgba(255,255,255,.5) 50%,transparent 60%);transform:translateX(-150%) translateZ(70px);mix-blend-mode:screen;pointer-events:none;animation:coverShine 5s ease-in-out infinite}
.knowledge-4d-shadow{position:absolute;left:50%;bottom:48px;width:250px;height:42px;transform:translateX(-50%);border-radius:50%;background:rgba(48,28,15,.24);filter:blur(18px);animation:shadowPulse 4.6s ease-in-out infinite}
@keyframes bookFloat{0%,100%{transform:translateY(0) rotateX(1deg) rotateY(-2deg)}50%{transform:translateY(-14px) rotateX(-1deg) rotateY(2deg)}}
@keyframes shadowPulse{0%,100%{transform:translateX(-50%) scale(1);opacity:.62}50%{transform:translateX(-50%) scale(.84);opacity:.4}}
@keyframes coverShine{0%,20%{transform:translateX(-150%) translateZ(70px)}58%,100%{transform:translateX(150%) translateZ(70px)}}
@media(max-width:980px){.knowledge-4d-stage{min-height:500px}.knowledge-4d-object{width:min(360px,86%)}}
@media(max-width:620px){.knowledge-4d-stage{min-height:430px}.knowledge-4d-object{width:min(300px,88%)}.knowledge-4d-cover{top:14%;width:50%;font-size:.82rem;padding:10px 7px}.knowledge-4d-shadow{bottom:32px;width:190px}}
@media(prefers-reduced-motion:reduce){.knowledge-4d-object,.knowledge-4d-light,.knowledge-4d-shadow{animation:none}}
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

            <div class="knowledge-4d-stage" id="knowledge4dStage" aria-label="Interactive Book of Knowledge held by a real hand">
                <div class="knowledge-4d-shadow" aria-hidden="true"></div>
                <div class="knowledge-4d-object" id="knowledge4dObject">
                    <img src="data:image/webp;base64,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" alt="A real hand holding the Book of Knowledge">
                    <div class="knowledge-4d-cover"><small>COURSEHUB EDITION</small>THE BOOK OF<br>KNOWLEDGE<span>LEARN · BUILD · GROW</span></div>
                    <div class="knowledge-4d-light" aria-hidden="true"></div>
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
    const stage = document.getElementById('knowledge4dStage');
    const object = document.getElementById('knowledge4dObject');
    if (!stage || !object || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    stage.addEventListener('pointermove', function (event) {
        const rect = stage.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        object.style.animation = 'none';
        object.style.transform = 'translateY(-8px) rotateX(' + (-y * 10).toFixed(2) + 'deg) rotateY(' + (x * 14).toFixed(2) + 'deg)';
    });

    stage.addEventListener('pointerleave', function () {
        object.style.transform = '';
        object.style.animation = '';
    });
})();
</script>
