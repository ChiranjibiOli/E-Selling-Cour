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
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=23">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.code-book-stage{position:relative;min-height:560px;display:grid;place-items:center;perspective:1400px;overflow:visible;isolation:isolate}
.code-book-scene{position:relative;width:min(420px,92%);height:540px;transform-style:preserve-3d;animation:sceneFloat 5s ease-in-out infinite;transition:transform .2s ease-out}
.code-book-shadow{position:absolute;left:50%;bottom:34px;width:260px;height:48px;transform:translateX(-50%);border-radius:50%;background:rgba(55,32,16,.22);filter:blur(18px);animation:shadowBreath 5s ease-in-out infinite}
.code-book{position:absolute;top:52px;left:50%;width:250px;height:330px;transform:translateX(-50%) rotateY(-8deg) rotateX(2deg);transform-style:preserve-3d;z-index:4}
.book-front{position:absolute;inset:0;border-radius:8px 16px 16px 8px;background:linear-gradient(135deg,#2b160b 0%,#5b2c13 46%,#2a1409 100%);box-shadow:inset 0 0 0 2px rgba(223,172,84,.38),inset 0 0 34px rgba(255,191,95,.08),0 28px 44px rgba(44,25,12,.26);transform:translateZ(18px);overflow:hidden}
.book-front::before{content:"";position:absolute;inset:16px;border:1px solid rgba(227,177,89,.48);box-shadow:inset 0 0 0 3px rgba(63,31,12,.72)}
.book-front::after{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 28%,rgba(255,231,176,.18) 46%,transparent 62%);transform:translateX(-120%);animation:bookShine 5.4s ease-in-out infinite}
.book-spine{position:absolute;left:-18px;top:6px;width:28px;height:318px;border-radius:10px 0 0 10px;background:linear-gradient(90deg,#1a0d07,#3c1d0e 55%,#6a3519);transform:rotateY(90deg);transform-origin:right center}
.book-pages{position:absolute;right:-10px;top:10px;width:22px;height:310px;border-radius:0 8px 8px 0;background:repeating-linear-gradient(to bottom,#f4ead8 0 3px,#d8c7ad 3px 4px);transform:translateZ(6px) rotateY(2deg)}
.book-title{position:absolute;inset:58px 26px auto;text-align:center;color:#e2b261;text-shadow:0 2px 8px rgba(0,0,0,.6);font-family:Georgia,"Times New Roman",serif;letter-spacing:.12em;text-transform:uppercase;line-height:1.18;z-index:2}
.book-title small{display:block;margin-bottom:14px;font-family:Arial,sans-serif;font-size:.56rem;font-weight:900;letter-spacing:.24em;color:#be8740}
.book-title strong{display:block;font-size:1.42rem;font-weight:600}
.book-title span{display:block;margin-top:16px;font-family:Arial,sans-serif;font-size:.52rem;font-weight:900;letter-spacing:.18em;color:#b67f38}
.book-emblem{position:absolute;left:50%;bottom:58px;width:58px;height:58px;transform:translateX(-50%) rotate(45deg);border:1px solid rgba(224,173,83,.7);box-shadow:inset 0 0 0 5px rgba(72,35,14,.64)}
.book-emblem::before{content:"";position:absolute;inset:13px;border:1px solid rgba(224,173,83,.6)}
.hand-arm{position:absolute;left:50%;top:306px;width:116px;height:250px;transform:translateX(-50%) rotate(2deg);border-radius:54px 54px 28px 28px;background:linear-gradient(90deg,#9f5d3a 0%,#d9976d 26%,#f0b58e 52%,#c57d55 78%,#8d4f31 100%);box-shadow:inset 16px 0 18px rgba(92,44,24,.18),inset -14px 0 18px rgba(255,221,193,.18);z-index:2}
.hand-palm{position:absolute;left:50%;top:286px;width:145px;height:112px;transform:translateX(-50%);border-radius:46% 46% 36% 36%;background:radial-gradient(circle at 50% 28%,#f4bea0 0 28%,#d8946f 58%,#a45f3e 100%);box-shadow:inset 0 -14px 20px rgba(101,49,27,.18);z-index:5}
.finger{position:absolute;top:248px;width:34px;height:118px;border-radius:22px;background:linear-gradient(90deg,#a96140,#e7a984 48%,#b56c49);box-shadow:inset -5px 0 7px rgba(96,45,25,.16);z-index:7}
.finger::after{content:"";position:absolute;top:5px;left:8px;width:18px;height:25px;border-radius:10px;background:linear-gradient(#f6cfbd,#d9a18a);box-shadow:inset 0 -3px 4px rgba(135,77,52,.2)}
.finger-1{left:112px;transform:rotate(6deg)}
.finger-2{left:151px;top:242px;height:124px;transform:rotate(2deg)}
.finger-3{right:151px;top:242px;height:124px;transform:rotate(-2deg)}
.finger-4{right:112px;transform:rotate(-6deg)}
.thumb{position:absolute;left:65px;top:292px;width:44px;height:104px;border-radius:24px;background:linear-gradient(90deg,#9d5c3d,#e3a17c 55%,#ae6544);transform:rotate(-35deg);z-index:8}
.thumb.right{left:auto;right:65px;transform:rotate(35deg)}
@keyframes sceneFloat{0%,100%{transform:translateY(0) rotateX(0) rotateY(-1deg)}50%{transform:translateY(-14px) rotateX(-1deg) rotateY(1deg)}}
@keyframes shadowBreath{0%,100%{transform:translateX(-50%) scale(1);opacity:.62}50%{transform:translateX(-50%) scale(.84);opacity:.4}}
@keyframes bookShine{0%,18%{transform:translateX(-120%)}56%,100%{transform:translateX(135%)}}
@media(max-width:620px){.code-book-stage{min-height:470px}.code-book-scene{width:320px;height:455px;transform:scale(.82);transform-origin:center top}.code-book-shadow{bottom:8px}}
@media(prefers-reduced-motion:reduce){.code-book-scene,.code-book-shadow,.book-front::after{animation:none}}
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

            <div class="code-book-stage" id="codeBookStage" aria-label="A code-built hand holding the Book of Knowledge">
                <div class="code-book-shadow" aria-hidden="true"></div>
                <div class="code-book-scene" id="codeBookScene">
                    <div class="code-book" aria-hidden="true">
                        <div class="book-spine"></div>
                        <div class="book-pages"></div>
                        <div class="book-front">
                            <div class="book-title"><small>COURSEHUB EDITION</small><strong>THE BOOK OF<br>KNOWLEDGE</strong><span>LEARN · BUILD · GROW</span></div>
                            <div class="book-emblem"></div>
                        </div>
                    </div>
                    <div class="finger finger-1"></div>
                    <div class="finger finger-2"></div>
                    <div class="finger finger-3"></div>
                    <div class="finger finger-4"></div>
                    <div class="thumb"></div>
                    <div class="thumb right"></div>
                    <div class="hand-palm"></div>
                    <div class="hand-arm"></div>
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
    const stage = document.getElementById('codeBookStage');
    const scene = document.getElementById('codeBookScene');
    if (!stage || !scene || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    stage.addEventListener('pointermove', function (event) {
        const rect = stage.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        scene.style.animation = 'none';
        scene.style.transform = 'translateY(-8px) rotateX(' + (-y * 10).toFixed(2) + 'deg) rotateY(' + (x * 14).toFixed(2) + 'deg)';
    });

    stage.addEventListener('pointerleave', function () {
        scene.style.transform = '';
        scene.style.animation = '';
    });
})();
</script>
