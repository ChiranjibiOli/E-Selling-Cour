<?php

require_once __DIR__ . '/../../config/database.php';

$conn = database_connection();
$landingCategories = [];
$stats = ['students' => 0, 'courses' => 0, 'enrollments' => 0];

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
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=18">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=30">
<link rel="stylesheet" href="assets/css/components/footer.css?v=12">

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
                <article class="editorial-empty-state">
                    <span>Categories are being prepared</span>
                    <h3>Learning categories will appear here.</h3>
                    <p>Instructors can create a category while building a course, and active categories will become visible in this collection.</p>
                    <a class="editorial-button editorial-button-dark" href="courses.php">Open courses</a>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <section class="editorial-directory">
        <div class="container">
            <div class="editorial-directory-intro">
                <h2>Everything important has its own page.</h2>
                <p>Students can move from discovery to browsing, payment guidance, and account creation without dead navigation.</p>
            </div>
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
            <div><strong><?php echo number_format($stats['enrollments']); ?></strong><span>Enrollments</span></div>
        </div>
    </section>
</main>
