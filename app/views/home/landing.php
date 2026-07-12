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
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=17">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.magic-learning-demo{position:relative;min-height:520px;overflow:hidden}.magic-cart{position:absolute;top:74px;left:50%;width:86px;height:86px;display:grid;place-items:center;transform:translateX(-50%);border:0;background:transparent;color:#171511;z-index:6}.magic-cart svg{width:68px;height:68px;filter:drop-shadow(0 12px 20px rgba(23,21,17,.14))}.magic-finger{position:absolute;top:24px;left:calc(50% - 118px);font-size:3rem;z-index:7;animation:fingerFloat 2s ease-in-out infinite}.magic-cursor{position:absolute;top:300px;left:16%;width:34px;height:42px;z-index:20;filter:drop-shadow(0 5px 5px rgba(0,0,0,.25));opacity:0}.magic-cursor svg{width:100%;height:100%}.magic-burst{position:absolute;top:116px;left:50%;width:14px;height:14px;transform:translate(-50%,-50%);border-radius:50%;opacity:0;box-shadow:0 -70px 0 #d39b3a,58px -38px 0 #6b5bd2,70px 20px 0 #d86a69,34px 68px 0 #55a678,-35px 68px 0 #e0b84c,-70px 14px 0 #5a83c6,-56px -42px 0 #b66bd0}.magic-book{position:absolute;top:176px;left:50%;width:280px;height:190px;transform:translateX(-50%) scale(.2) rotate(-10deg);opacity:0;perspective:1000px;z-index:8}.book-half{position:absolute;top:0;width:50%;height:100%;background:#fff9eb;box-shadow:0 22px 50px rgba(34,25,12,.18);overflow:hidden}.book-left{left:0;border-radius:22px 4px 4px 22px;transform:rotateY(8deg)}.book-right{right:0;border-radius:4px 22px 22px 4px;transform:rotateY(-8deg)}.book-half::before{content:"";position:absolute;inset:18px 16px;background:repeating-linear-gradient(to bottom,#d7ccb9 0 2px,transparent 2px 19px);opacity:.72}.book-spine{position:absolute;left:50%;top:3px;width:4px;height:184px;transform:translateX(-50%);border-radius:999px;background:#b79d73;z-index:3}.book-page{position:absolute;top:0;left:50%;width:50%;height:100%;transform-origin:left center;background:#fffdf6;border-radius:3px 20px 20px 3px;box-shadow:0 14px 30px rgba(34,25,12,.16);z-index:4}.course-sheet{position:absolute;top:242px;left:50%;width:min(330px,84%);padding:18px 20px;transform:translate(-50%,44px) scale(.84);opacity:0;border-radius:20px;background:rgba(255,255,255,.98);box-shadow:0 24px 60px rgba(30,23,14,.18);z-index:12}.course-sheet small{display:block;margin-bottom:10px;color:#9a6e23;font-size:.65rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.course-sheet ul{display:grid;gap:10px;margin:0;padding:0;list-style:none}.course-sheet li{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;background:#f6f0e6;color:#2c2822;font-size:.76rem;font-weight:850}.course-sheet li::before{content:'✦';color:#b9832f}.magic-caption{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);color:#72685d;font-size:.72rem;font-weight:800;white-space:nowrap}.magic-replay{position:absolute;right:18px;bottom:16px;border:0;background:transparent;color:#9a6e23;font-size:.68rem;font-weight:900;cursor:pointer;z-index:30}
.magic-learning-demo.is-running .magic-cursor{animation:cursorSequence 8s cubic-bezier(.2,.8,.2,1) both}.magic-learning-demo.is-running .magic-cart{animation:cartSequence 8s ease both}.magic-learning-demo.is-running .magic-burst{animation:burstSequence 8s ease-out both}.magic-learning-demo.is-running .magic-book{animation:bookSequence 8s cubic-bezier(.2,.85,.2,1) both}.magic-learning-demo.is-running .book-page{animation:pageSequence 8s cubic-bezier(.4,0,.2,1) both}.magic-learning-demo.is-running .course-sheet{animation:sheetSequence 8s cubic-bezier(.2,.9,.2,1) both}
@keyframes fingerFloat{50%{transform:translateY(-8px) rotate(8deg)}}
@keyframes cursorSequence{0%{top:300px;left:16%;opacity:0;transform:rotate(-15deg) scale(1)}6%{opacity:1}24%{top:106px;left:58%;opacity:1;transform:rotate(-7deg) scale(1)}29%{top:106px;left:58%;transform:rotate(-7deg) scale(.76)}34%{top:106px;left:58%;transform:rotate(-7deg) scale(1)}55%{opacity:1}68%,100%{opacity:0}}
@keyframes cartSequence{0%,24%{transform:translateX(-50%) scale(1)}29%{transform:translateX(-50%) scale(.82)}34%{transform:translateX(-50%) scale(1)}46%{opacity:1}58%,100%{opacity:0}}
@keyframes burstSequence{0%,28%{opacity:0;transform:translate(-50%,-50%) scale(.2)}34%{opacity:1}45%{opacity:0;transform:translate(-50%,-50%) scale(1.5)}100%{opacity:0}}
@keyframes bookSequence{0%,34%{opacity:0;transform:translateX(-50%) scale(.2) rotate(-10deg)}45%{opacity:1;transform:translateX(-50%) scale(1.04) rotate(1deg)}51%,88%{opacity:1;transform:translateX(-50%) scale(1) rotate(0)}100%{opacity:0;transform:translateX(-50%) scale(.94)}}
@keyframes pageSequence{0%,49%{transform:rotateY(0)}61%{transform:rotateY(-178deg)}88%,100%{transform:rotateY(-178deg)}}
@keyframes sheetSequence{0%,58%{opacity:0;transform:translate(-50%,44px) scale(.84)}69%,88%{opacity:1;transform:translate(-50%,-8px) scale(1)}100%{opacity:0;transform:translate(-50%,-22px) scale(.97)}}
@media(max-width:620px){.magic-learning-demo{min-height:430px}.magic-cart{top:48px}.magic-finger{top:10px;left:calc(50% - 96px);font-size:2.5rem}.magic-book{top:142px;width:238px;height:164px}.book-spine{height:158px}.course-sheet{top:205px}.magic-caption{bottom:10px}@keyframes cursorSequence{0%{top:285px;left:10%;opacity:0;transform:rotate(-15deg)}6%{opacity:1}24%{top:82px;left:62%;opacity:1;transform:rotate(-7deg) scale(1)}29%{top:82px;left:62%;transform:rotate(-7deg) scale(.76)}34%{top:82px;left:62%;transform:rotate(-7deg) scale(1)}55%{opacity:1}68%,100%{opacity:0}}}
@media(prefers-reduced-motion:reduce){.magic-learning-demo.is-running .magic-cursor,.magic-learning-demo.is-running .magic-cart,.magic-learning-demo.is-running .magic-burst,.magic-learning-demo.is-running .magic-book,.magic-learning-demo.is-running .book-page,.magic-learning-demo.is-running .course-sheet{animation:none}.magic-book{opacity:1;transform:translateX(-50%) scale(1)}.course-sheet{opacity:1;transform:translate(-50%,-8px) scale(1)}}
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

            <div class="magic-learning-demo" id="magicLearningDemo" aria-label="Animated cart transforming into a book with course names">
                <div class="magic-finger" aria-hidden="true">👉</div>
                <button class="magic-cart" type="button" aria-label="Animated course cart">
                    <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 11h7l7 29h25l7-22H18"/><circle cx="27" cy="49" r="3"/><circle cx="47" cy="49" r="3"/></svg>
                </button>
                <div class="magic-burst" aria-hidden="true"></div>
                <div class="magic-cursor" aria-hidden="true"><svg viewBox="0 0 32 40"><path d="M3 2l24 20-11 2 7 12-6 3-7-12-7 8z" fill="#fff" stroke="#171511" stroke-width="2"/></svg></div>
                <div class="magic-book" aria-hidden="true">
                    <div class="book-half book-left"></div>
                    <div class="book-half book-right"></div>
                    <div class="book-page"></div>
                    <div class="book-spine"></div>
                </div>
                <div class="course-sheet">
                    <small>Courses inside</small>
                    <ul>
                        <?php foreach (array_slice($demoCourses, 0, 3) as $course): ?>
                            <li><?php echo landing_h($course['title']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="magic-caption">Cart → book → courses</div>
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
        if (loopTimer) {
            window.clearTimeout(loopTimer);
        }

        demo.classList.remove('is-running');
        void demo.offsetWidth;
        demo.classList.add('is-running');

        loopTimer = window.setTimeout(runAnimation, 8200);
    }

    replay.addEventListener('click', runAnimation);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runAnimation, { once: true });
    } else {
        runAnimation();
    }
})();
</script>
