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

$demoCourse = $featuredCourses[0] ?? [
    'title' => 'Complete Web Development',
    'short_description' => 'Build modern websites through structured, practical lessons.',
    'price' => 2499,
    'level' => 'beginner',
    'duration' => '24 hours',
    'language' => 'English',
    'instructor_name' => 'Verified instructor',
    'slug' => '',
];
?>
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=14">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=15">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.hero-product-demo{position:relative;min-height:520px;overflow:hidden;border:1px solid rgba(69,52,26,.12);border-radius:34px;background:linear-gradient(145deg,#171917,#22251f 58%,#2c2f28);box-shadow:0 34px 86px rgba(27,22,15,.24);isolation:isolate}.hero-product-demo::before{content:"";position:absolute;inset:-35%;background:radial-gradient(circle at 72% 30%,rgba(208,157,67,.2),transparent 24%),radial-gradient(circle at 24% 86%,rgba(82,119,107,.2),transparent 28%);animation:heroDemoGlow 8s ease-in-out infinite alternate}.demo-browser{position:absolute;inset:24px;overflow:hidden;border:1px solid rgba(255,255,255,.12);border-radius:24px;background:#f6f2e9;box-shadow:0 18px 46px rgba(0,0,0,.24)}.demo-topbar{height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 18px;border-bottom:1px solid #e1d9ca;background:rgba(255,255,255,.82)}.demo-dots{display:flex;gap:6px}.demo-dots i{width:8px;height:8px;border-radius:50%;background:#d5c9b7}.demo-brand{font-size:.68rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.demo-cart-button{position:relative;width:42px;height:42px;display:grid;place-items:center;border:0;border-radius:14px;background:#171511;color:#f5c469;box-shadow:0 8px 18px rgba(23,21,17,.18);cursor:pointer}.demo-cart-button svg{width:20px;height:20px}.demo-cart-count{position:absolute;top:-5px;right:-5px;width:18px;height:18px;display:grid;place-items:center;border-radius:50%;background:#d79b33;color:#171511;font-size:.58rem;font-weight:900}.demo-stage{position:relative;height:calc(100% - 54px)}.demo-screen{position:absolute;inset:0;padding:24px;opacity:0;transform:translateX(36px) scale(.985);transition:opacity .52s ease,transform .52s ease;pointer-events:none}.demo-screen.is-active{opacity:1;transform:none;pointer-events:auto}.demo-home-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:18px;height:100%}.demo-copy{display:flex;flex-direction:column;justify-content:center;padding:12px}.demo-eyebrow{color:#9a6e23;font-size:.62rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.demo-copy h3{max-width:340px;margin:12px 0 10px;font-family:Georgia,"Times New Roman",serif;font-size:clamp(2.2rem,4vw,3.5rem);font-weight:500;line-height:.92;letter-spacing:-.05em}.demo-copy p{max-width:320px;color:#6d655b;font-size:.78rem;line-height:1.65}.demo-mini-card{display:flex;align-items:center;gap:10px;margin-top:18px;padding:12px;border:1px solid #e2d7c7;border-radius:16px;background:#fff}.demo-mini-cover{width:52px;height:52px;display:grid;place-items:center;border-radius:13px;background:linear-gradient(145deg,#ce9b43,#8b6021);color:#fff;font-size:1.25rem}.demo-mini-card strong{display:block;font-size:.76rem}.demo-mini-card span{display:block;margin-top:3px;color:#847b70;font-size:.64rem}.demo-book-stack{position:relative;display:grid;place-items:center}.demo-book{position:absolute;width:74%;height:42px;border-radius:7px 12px 12px 7px;box-shadow:0 12px 24px rgba(56,37,15,.17);transform-origin:left center}.demo-book:nth-child(1){bottom:28%;background:#b57f2c;transform:rotate(-7deg)}.demo-book:nth-child(2){bottom:38%;background:#f0e2c8;transform:rotate(4deg)}.demo-book:nth-child(3){bottom:48%;background:#263b35;transform:rotate(-2deg)}.demo-book::after{content:"";position:absolute;inset:6px 10px 6px 16px;border-radius:3px;background:rgba(255,255,255,.22)}.demo-open-book{position:absolute;top:46%;left:50%;width:148px;height:92px;opacity:0;transform:translate(-50%,-50%) scale(.35) rotate(-12deg);transition:opacity .35s ease,transform .6s cubic-bezier(.2,.9,.25,1.2)}.demo-open-book::before,.demo-open-book::after{content:"";position:absolute;top:0;width:50%;height:100%;background:#fff9eb;box-shadow:0 16px 28px rgba(20,15,8,.2)}.demo-open-book::before{left:0;border-radius:18px 3px 3px 18px;transform:skewY(-7deg)}.demo-open-book::after{right:0;border-radius:3px 18px 18px 3px;transform:skewY(7deg)}.demo-open-book i{position:absolute;z-index:2;top:13px;width:42%;height:2px;background:#c6b79f;box-shadow:0 11px #d8cbb6,0 22px #d8cbb6,0 33px #d8cbb6}.demo-open-book i:first-child{left:5%}.demo-open-book i:last-child{right:5%}.hero-product-demo.is-book-open .demo-open-book{opacity:1;transform:translate(-50%,-50%) scale(1) rotate(0)}.hero-product-demo.is-book-open .demo-book{animation:demoBooksDrop .5s ease forwards}.demo-course-screen{display:grid;grid-template-columns:.9fr 1.1fr;gap:18px}.demo-course-cover{position:relative;overflow:hidden;border-radius:20px;background:linear-gradient(145deg,#1e2723,#405a50);color:#fff}.demo-course-cover::before{content:"";position:absolute;width:180px;height:180px;right:-35px;top:-35px;border-radius:50%;background:rgba(223,170,77,.24)}.demo-course-cover-content{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:flex-end;padding:24px}.demo-course-cover span{font-size:.62rem;font-weight:900;letter-spacing:.15em;text-transform:uppercase;color:#f0c36b}.demo-course-cover strong{display:block;margin-top:10px;font-family:Georgia,"Times New Roman",serif;font-size:2rem;font-weight:500;line-height:1}.demo-course-content{display:flex;flex-direction:column;justify-content:center;padding:10px}.demo-course-content h3{margin:8px 0 10px;font-family:Georgia,"Times New Roman",serif;font-size:2rem;font-weight:500;line-height:1}.demo-course-content p{color:#746c62;font-size:.76rem;line-height:1.62}.demo-progress{height:8px;margin:18px 0 8px;overflow:hidden;border-radius:999px;background:#ddd5c8}.demo-progress span{display:block;width:68%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#bf8730,#e6b65c)}.demo-lesson-list{display:grid;gap:8px;margin-top:13px}.demo-lesson{display:flex;align-items:center;gap:10px;padding:10px;border:1px solid #e3dacd;border-radius:12px;background:#fff;font-size:.67rem;font-weight:800}.demo-lesson b{width:24px;height:24px;display:grid;place-items:center;border-radius:50%;background:#f3e6cd;color:#95661d}.demo-cursor{position:absolute;z-index:20;width:28px;height:34px;left:35%;top:58%;filter:drop-shadow(0 5px 5px rgba(0,0,0,.28));transform:rotate(-18deg);transition:left .8s cubic-bezier(.2,.8,.2,1),top .8s cubic-bezier(.2,.8,.2,1),transform .2s ease}.demo-cursor svg{width:100%;height:100%}.hero-product-demo.is-targeting .demo-cursor{left:86%;top:8%;transform:rotate(-8deg)}.hero-product-demo.is-clicking .demo-cursor{transform:rotate(-8deg) scale(.8)}.hero-product-demo.is-clicking .demo-cart-button{animation:demoCartPulse .35s ease}.demo-replay{position:absolute;right:18px;bottom:16px;z-index:30;padding:8px 11px;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(16,17,15,.72);color:#fff;font-size:.62rem;font-weight:900;cursor:pointer;backdrop-filter:blur(12px)}@keyframes heroDemoGlow{to{transform:translate3d(4%,2%,0) scale(1.05)}}@keyframes demoCartPulse{50%{transform:scale(.88);box-shadow:0 0 0 12px rgba(215,155,51,.2)}}@keyframes demoBooksDrop{to{opacity:.15;transform:translateY(38px) scale(.9)}}@media(max-width:980px){.hero-product-demo{min-height:470px}.demo-browser{inset:18px}}@media(max-width:620px){.hero-product-demo{min-height:390px;border-radius:24px}.demo-browser{inset:10px;border-radius:18px}.demo-topbar{height:48px;padding:0 12px}.demo-stage{height:calc(100% - 48px)}.demo-screen{padding:14px}.demo-home-grid,.demo-course-screen{grid-template-columns:1fr}.demo-book-stack{display:none}.demo-copy{padding:8px}.demo-copy h3{font-size:2.2rem}.demo-course-cover{min-height:130px}.demo-course-cover-content{padding:16px}.demo-course-cover strong{font-size:1.35rem}.demo-course-content h3{font-size:1.45rem}.demo-lesson-list{gap:5px}.demo-lesson{padding:7px}.demo-cursor{width:24px;height:30px}.hero-product-demo.is-targeting .demo-cursor{left:82%;top:7%}}@media(prefers-reduced-motion:reduce){.hero-product-demo::before,.demo-cart-button,.demo-book{animation:none!important}.demo-cursor,.demo-screen,.demo-open-book{transition:none!important}}
</style>

<main class="landing-page">
    <section class="editorial-hero">
        <div class="container editorial-hero-grid">
            <div class="editorial-hero-copy">
                <span class="editorial-kicker">Learn from the best</span>
                <h1>
                    Education<br>
                    that <em>transforms</em><br>
                    your life.
                </h1>
                <p>
                    Handpicked courses from approved instructors, designed for real progress.
                    Purchase once, complete payment verification, and keep lifetime access.
                </p>
                <div class="editorial-actions">
                    <a class="editorial-button editorial-button-gold" href="courses.php">Discover courses</a>
                    <a class="editorial-button editorial-button-light" href="how-it-works.php">How it works</a>
                </div>
            </div>

            <div class="hero-product-demo" id="heroProductDemo" aria-label="Animated demonstration of adding a course and opening its lessons">
                <div class="demo-browser">
                    <div class="demo-topbar">
                        <div class="demo-dots" aria-hidden="true"><i></i><i></i><i></i></div>
                        <span class="demo-brand">CourseHub learning</span>
                        <button class="demo-cart-button" id="demoCartButton" type="button" aria-label="Open animated course preview">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2.4 10.2a2 2 0 0 0 2 1.5h7.7a2 2 0 0 0 2-1.6L21 7H7"/><circle cx="10" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg>
                            <span class="demo-cart-count">1</span>
                        </button>
                    </div>

                    <div class="demo-stage">
                        <section class="demo-screen demo-home-screen is-active" data-demo-screen="home">
                            <div class="demo-home-grid">
                                <div class="demo-copy">
                                    <span class="demo-eyebrow">Choose. Learn. Progress.</span>
                                    <h3>One click starts a new skill.</h3>
                                    <p>Watch the cart open, the learning book appear, and the course lesson page load automatically.</p>
                                    <div class="demo-mini-card">
                                        <div class="demo-mini-cover">⌘</div>
                                        <div><strong><?php echo landing_h($demoCourse['title']); ?></strong><span><?php echo landing_price($demoCourse['price']); ?> · Lifetime access</span></div>
                                    </div>
                                </div>
                                <div class="demo-book-stack" aria-hidden="true">
                                    <div class="demo-book"></div><div class="demo-book"></div><div class="demo-book"></div>
                                    <div class="demo-open-book"><i></i><i></i></div>
                                </div>
                            </div>
                        </section>

                        <section class="demo-screen demo-course-screen" data-demo-screen="course">
                            <div class="demo-course-cover">
                                <div class="demo-course-cover-content">
                                    <span><?php echo landing_h(strtoupper((string) $demoCourse['level'])); ?> COURSE</span>
                                    <strong><?php echo landing_h($demoCourse['title']); ?></strong>
                                </div>
                            </div>
                            <div class="demo-course-content">
                                <span class="demo-eyebrow">Your course is ready</span>
                                <h3>Continue learning</h3>
                                <p><?php echo landing_h($demoCourse['short_description']); ?></p>
                                <div class="demo-progress" aria-label="Course progress 68 percent"><span></span></div>
                                <small>68% complete · <?php echo landing_h($demoCourse['duration'] ?: 'Self paced'); ?></small>
                                <div class="demo-lesson-list">
                                    <div class="demo-lesson"><b>✓</b> Introduction and setup</div>
                                    <div class="demo-lesson"><b>✓</b> Build the first project</div>
                                    <div class="demo-lesson"><b>▶</b> Continue your next lesson</div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="demo-cursor" aria-hidden="true">
                        <svg viewBox="0 0 32 40"><path d="M3 2l24 20-11 2 7 12-6 3-7-12-7 8z" fill="#fff" stroke="#171511" stroke-width="2"/></svg>
                    </div>
                </div>
                <button class="demo-replay" id="demoReplay" type="button">Replay animation</button>
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
                                <span class="editorial-course-number">
                                    <?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?> ·
                                    <?php echo landing_h(strtoupper((string) $course['level'])); ?>
                                </span>
                                <h3><?php echo landing_h($course['title']); ?></h3>
                                <p><?php echo landing_h($course['short_description']); ?></p>
                                <div class="editorial-course-meta">
                                    <span><?php echo landing_h($course['instructor_name']); ?></span>
                                    <span><?php echo landing_price($course['price']); ?></span>
                                </div>
                                <a class="editorial-button editorial-button-dark" href="course-details.php?slug=<?php echo urlencode((string) $course['slug']); ?>">View course</a>
                            </div>

                            <a class="editorial-course-art" href="course-details.php?slug=<?php echo urlencode((string) $course['slug']); ?>" aria-label="View <?php echo landing_h($course['title']); ?>">
                                <img src="<?php echo landing_h(landing_thumbnail($course)); ?>" alt="<?php echo landing_h($course['title']); ?>">
                                <span><?php echo landing_h(strtoupper(substr((string) $course['title'], 0, 1))); ?></span>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <article class="editorial-empty-state">
                        <span>Courses are being prepared</span>
                        <h3>Published courses will appear here.</h3>
                        <p>Explore the full catalogue or return after instructors publish approved courses.</p>
                        <a class="editorial-button editorial-button-dark" href="courses.php">Open courses</a>
                    </article>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="editorial-directory">
        <div class="container">
            <div class="editorial-directory-intro">
                <h2>Everything important has its own page.</h2>
                <p>Students can move from discovery to course browsing, instructor profiles, payment guidance, and account creation without dead navigation.</p>
            </div>

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
    const demo = document.getElementById('heroProductDemo');
    const cartButton = document.getElementById('demoCartButton');
    const replayButton = document.getElementById('demoReplay');
    if (!demo || !cartButton || !replayButton) return;

    const home = demo.querySelector('[data-demo-screen="home"]');
    const course = demo.querySelector('[data-demo-screen="course"]');
    let timers = [];

    function later(callback, delay) {
        const timer = window.setTimeout(callback, delay);
        timers.push(timer);
    }

    function resetDemo() {
        timers.forEach(window.clearTimeout);
        timers = [];
        demo.classList.remove('is-targeting', 'is-clicking', 'is-book-open');
        home.classList.add('is-active');
        course.classList.remove('is-active');
    }

    function playDemo() {
        resetDemo();
        later(() => demo.classList.add('is-targeting'), 650);
        later(() => demo.classList.add('is-clicking'), 1500);
        later(() => {
            demo.classList.remove('is-clicking');
            demo.classList.add('is-book-open');
        }, 1820);
        later(() => {
            home.classList.remove('is-active');
            course.classList.add('is-active');
        }, 2750);
        later(() => playDemo(), 7200);
    }

    cartButton.addEventListener('click', playDemo);
    replayButton.addEventListener('click', playDemo);

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        home.classList.remove('is-active');
        course.classList.add('is-active');
    } else {
        playDemo();
    }
})();
</script>