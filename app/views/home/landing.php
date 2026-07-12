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

$demoCourses = $featuredCourses ?: [
    ['title' => 'Complete Web Development'],
    ['title' => 'UI/UX Design Masterclass'],
    ['title' => 'Digital Marketing Essentials'],
];
?>
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=14">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=18">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.magic-learning-demo{position:relative;min-height:520px;overflow:hidden}
.magic-cart{position:absolute;top:82px;left:50%;width:96px;height:96px;display:grid;place-items:center;transform:translateX(-50%);border:0;background:transparent;color:#171511;z-index:8}
.magic-cart svg{width:72px;height:72px;filter:drop-shadow(0 12px 20px rgba(23,21,17,.14))}
.magic-cursor{position:absolute;top:330px;left:10%;width:36px;height:44px;z-index:20;opacity:0;filter:drop-shadow(0 5px 5px rgba(0,0,0,.25))}
.magic-cursor svg{width:100%;height:100%}
.magic-book{position:absolute;top:168px;left:50%;width:290px;height:196px;opacity:0;transform:translateX(-50%) scale(.2);perspective:1200px;z-index:10}
.book-cover{position:absolute;inset:0;border-radius:18px;background:linear-gradient(145deg,#1d2b27,#3e5b51);box-shadow:0 26px 60px rgba(30,23,14,.22);transform-origin:left center}
.book-pages{position:absolute;inset:12px 16px;background:#fff9eb;border-radius:14px;box-shadow:inset 0 0 0 1px rgba(120,92,46,.12)}
.book-pages::before{content:"";position:absolute;inset:18px;background:repeating-linear-gradient(to bottom,#d8ccb8 0 2px,transparent 2px 18px);opacity:.7}
.book-page-turn{position:absolute;top:12px;left:50%;width:45%;height:172px;transform-origin:left center;background:#fffdf6;border-radius:3px 14px 14px 3px;box-shadow:0 12px 24px rgba(35,25,12,.16);z-index:3}
.course-sheet{position:absolute;top:176px;left:50%;width:min(340px,84%);padding:20px 22px;opacity:0;transform:translate(-50%,48px) scale(.88);border-radius:20px;background:#fff;box-shadow:0 28px 66px rgba(30,23,14,.2);z-index:14}
.course-sheet small{display:block;margin-bottom:11px;color:#9a6e23;font-size:.65rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.course-sheet ul{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.course-sheet li{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:12px;background:#f6f0e6;color:#2c2822;font-size:.76rem;font-weight:850}
.course-sheet li::before{content:'✦';color:#b9832f}
.magic-replay{position:absolute;right:18px;bottom:16px;border:0;background:transparent;color:#9a6e23;font-size:.68rem;font-weight:900;cursor:pointer;z-index:30}
.magic-learning-demo.is-running .magic-cursor{animation:cursorFlow 8s cubic-bezier(.2,.8,.2,1) both}
.magic-learning-demo.is-running .magic-cart{animation:cartFlow 8s ease both}
.magic-learning-demo.is-running .magic-book{animation:bookFlow 8s cubic-bezier(.2,.85,.2,1) both}
.magic-learning-demo.is-running .book-cover{animation:coverFlow 8s cubic-bezier(.4,0,.2,1) both}
.magic-learning-demo.is-running .book-page-turn{animation:pageFlow 8s cubic-bezier(.4,0,.2,1) both}
.magic-learning-demo.is-running .course-sheet{animation:sheetFlow 8s cubic-bezier(.2,.9,.2,1) both}
@keyframes cursorFlow{0%{top:330px;left:10%;opacity:0;transform:rotate(-16deg) scale(1)}6%{opacity:1}24%{top:116px;left:58%;opacity:1;transform:rotate(-7deg) scale(1)}29%{top:116px;left:58%;transform:rotate(-7deg) scale(.75)}34%{top:116px;left:58%;transform:rotate(-7deg) scale(1)}40%,100%{opacity:0}}
@keyframes cartFlow{0%,24%{opacity:1;transform:translateX(-50%) scale(1)}29%{transform:translateX(-50%) scale(.82)}34%{transform:translateX(-50%) scale(1)}40%,100%{opacity:0}}
@keyframes bookFlow{0%,37%{opacity:0;transform:translateX(-50%) scale(.2)}47%{opacity:1;transform:translateX(-50%) scale(1.05)}53%,70%{opacity:1;transform:translateX(-50%) scale(1)}76%,100%{opacity:0;transform:translateX(-50%) scale(.92)}}
@keyframes coverFlow{0%,49%{transform:rotateY(0)}58%,70%{transform:rotateY(-165deg)}76%,100%{transform:rotateY(-165deg)}}
@keyframes pageFlow{0%,55%{transform:rotateY(0)}66%,70%{transform:rotateY(-178deg)}76%,100%{transform:rotateY(-178deg)}}
@keyframes sheetFlow{0%,73%{opacity:0;transform:translate(-50%,48px) scale(.88)}82%,94%{opacity:1;transform:translate(-50%,0) scale(1)}100%{opacity:0;transform:translate(-50%,-18px) scale(.98)}}
@media(max-width:620px){.magic-learning-demo{min-height:430px}.magic-cart{top:62px}.magic-book{top:142px;width:238px;height:164px}.book-page-turn{height:140px}.course-sheet{top:154px}@keyframes cursorFlow{0%{top:290px;left:8%;opacity:0;transform:rotate(-16deg)}6%{opacity:1}24%{top:96px;left:62%;opacity:1;transform:rotate(-7deg) scale(1)}29%{top:96px;left:62%;transform:rotate(-7deg) scale(.75)}34%{top:96px;left:62%;transform:rotate(-7deg) scale(1)}40%,100%{opacity:0}}}
@media(prefers-reduced-motion:reduce){.magic-learning-demo.is-running .magic-cursor,.magic-learning-demo.is-running .magic-cart,.magic-learning-demo.is-running .magic-book,.magic-learning-demo.is-running .book-cover,.magic-learning-demo.is-running .book-page-turn,.magic-learning-demo.is-running .course-sheet{animation:none}.course-sheet{opacity:1;transform:translate(-50%,0) scale(1)}}
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

            <div class="magic-learning-demo" id="magicLearningDemo" aria-label="Animated cart, book, and course page sequence">
                <button class="magic-cart" type="button" aria-label="Animated course cart">
                    <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 11h7l7 29h25l7-22H18"/><circle cx="27" cy="49" r="3"/><circle cx="47" cy="49" r="3"/></svg>
                </button>
                <div class="magic-cursor" aria-hidden="true"><svg viewBox="0 0 32 40"><path d="M3 2l24 20-11 2 7 12-6 3-7-12-7 8z" fill="#fff" stroke="#171511" stroke-width="2"/></svg></div>
                <div class="magic-book" aria-hidden="true">
                    <div class="book-pages"></div>
                    <div class="book-cover"></div>
                    <div class="book-page-turn"></div>
                </div>
                <div class="course-sheet">
                    <small>Featured courses</small>
                    <ul>
                        <?php foreach (array_slice($demoCourses, 0, 3) as $course): ?>
                            <li><?php echo landing_h($course['title']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <button class="magic-replay" id="magicReplay" type="button">Replay</button>
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

<script>
(function () {
    const demo = document.getElementById('magicLearningDemo');
    const replay = document.getElementById('magicReplay');
    if (!demo || !replay) return;

    let loopTimer = null;

    function runAnimation() {
        if (loopTimer) window.clearTimeout(loopTimer);
        demo.classList.remove('is-running');
        void demo.offsetWidth;
        demo.classList.add('is-running');
        loopTimer = window.setTimeout(runAnimation, 8300);
    }

    replay.addEventListener('click', runAnimation);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runAnimation, { once: true });
    } else {
        runAnimation();
    }
})();
</script>
