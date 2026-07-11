<?php

require_once __DIR__ . '/../../config/database.php';

$featuredCourses = [];
$categories = [];

$stats = [
    'students' => 0,
    'courses' => 0,
    'instructors' => 0,
    'enrollments' => 0,
];

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
    $publicRoot = defined('PUBLIC_PATH')
        ? PUBLIC_PATH
        : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';

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
    SELECT
        c.id,
        c.title,
        c.slug,
        c.short_description,
        c.thumbnail,
        c.price,
        c.level,
        c.duration,
        c.language,
        u.full_name AS instructor_name
    FROM courses c
    INNER JOIN users u ON c.instructor_id = u.id
    WHERE c.status = 'published'
    ORDER BY c.is_featured DESC, c.created_at DESC
    LIMIT 8
";

$featuredResult = $conn->query($featuredSql);

while ($featuredResult && $row = $featuredResult->fetch_assoc()) {
    $featuredCourses[] = $row;
}

$categoryResult = $conn->query("
    SELECT id, name, slug
    FROM categories
    WHERE status = 'active'
    ORDER BY name ASC
    LIMIT 8
");

while ($categoryResult && $row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
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

$learningPaths = [
    [
        'icon' => '💻',
        'title' => 'Web Development',
        'description' => 'Learn HTML, CSS, JavaScript, PHP, Laravel, and full-stack project skills.',
        'search' => 'web development',
    ],
    [
        'icon' => '🛡️',
        'title' => 'Cybersecurity',
        'description' => 'Start with networking, web security, ethical hacking, and practical labs.',
        'search' => 'cybersecurity',
    ],
    [
        'icon' => '📊',
        'title' => 'Business & Marketing',
        'description' => 'Build business, digital marketing, sales, and online growth skills.',
        'search' => 'business',
    ],
    [
        'icon' => '🎨',
        'title' => 'Design & Creativity',
        'description' => 'Learn UI/UX, Canva, graphics, content creation, and creative design.',
        'search' => 'design',
    ],
];

$steps = [
    [
        'number' => '01',
        'title' => 'Choose a course',
        'description' => 'Browse course details, instructor name, price, level, and course description.',
    ],
    [
        'number' => '02',
        'title' => 'Pay securely',
        'description' => 'Use supported payment methods and upload proof when manual verification is needed.',
    ],
    [
        'number' => '03',
        'title' => 'Admin verifies',
        'description' => 'Admin checks payment proof and activates your enrollment after confirmation.',
    ],
    [
        'number' => '04',
        'title' => 'Start learning',
        'description' => 'Access purchased courses anytime with lifetime access after approval.',
    ],
];

$features = [
    [
        'icon' => '👨‍🏫',
        'title' => 'Approved instructors',
        'description' => 'Instructor accounts need admin approval before they can publish paid courses.',
    ],
    [
        'icon' => '📚',
        'title' => 'Structured lessons',
        'description' => 'Courses can include sections, lessons, videos, text, links, and previews.',
    ],
    [
        'icon' => '💳',
        'title' => 'Nepal-friendly payments',
        'description' => 'Supports manual verification and local payment workflow for students.',
    ],
    [
        'icon' => '🔒',
        'title' => 'Protected learning',
        'description' => 'Only enrolled students can access purchased course content.',
    ],
];

$testimonials = [
    [
        'quote' => 'The platform makes online learning simple. I can find courses, pay, and continue learning from my dashboard.',
        'name' => 'Sukanta Thakuri',
        'role' => 'Student',
    ],
    [
        'quote' => 'As an instructor, I like that courses go through approval. It makes the platform more trusted.',
        'name' => 'Sushant Karki',
        'role' => 'Instructor',
    ],
    [
        'quote' => 'The admin panel can manage users, courses, payments, and reports in a clear workflow.',
        'name' => 'CourseHub Admin',
        'role' => 'Administrator',
    ],
];

$faqs = [
    [
        'question' => 'When does course access begin?',
        'answer' => 'Course access begins after an administrator verifies payment and activates enrollment.',
    ],
    [
        'question' => 'Can I preview a course before buying?',
        'answer' => 'Yes. Students can view course information, price, instructor, description, and available preview content.',
    ],
    [
        'question' => 'Can instructors publish directly?',
        'answer' => 'No. Instructor accounts and courses should be reviewed by an administrator before public access.',
    ],
    [
        'question' => 'Do students get lifetime access?',
        'answer' => 'Yes. Purchased and approved courses are available with lifetime access unless access is revoked by admin.',
    ],
];

?>
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=5">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=5">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=5">
<link rel="stylesheet" href="assets/css/components/footer.css?v=5">

<main class="landing-page">

    <section class="hero-section">
        <div class="container hero-content">

            <div class="hero-text">
                <span class="tagline">Nepal’s trusted online learning platform 🇳🇵</span>

                <h1>
                    Learn practical skills from
                    <span>approved instructors</span>
                </h1>

                <p>
                    Browse real courses, pay using supported methods, and get lifetime
                    access after your payment is verified by the admin.
                </p>

                <form action="courses.php" method="GET" class="hero-search">
                    <div class="search-field">
                        <span>🔍</span>
                        <input
                            type="text"
                            name="search"
                            placeholder="Search PHP, Networking, Cybersecurity, Design..."
                            aria-label="Search courses"
                        >
                    </div>

                    <select name="level" aria-label="Course level">
                        <option value="">All levels</option>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>

                    <button type="submit">Search</button>
                </form>

                <div class="hero-buttons">
                    <a href="courses.php" class="btn btn-primary">Explore courses</a>
                    <a href="register.php?role=instructor" class="btn btn-secondary">Become instructor</a>
                </div>

                <div class="hero-trust">
                    <div><strong><?php echo number_format($stats['students']); ?>+</strong><span>Students</span></div>
                    <div><strong><?php echo number_format($stats['courses']); ?>+</strong><span>Courses</span></div>
                    <div><strong><?php echo number_format($stats['instructors']); ?>+</strong><span>Instructors</span></div>
                    <div><strong><?php echo number_format($stats['enrollments']); ?>+</strong><span>Enrollments</span></div>
                </div>
            </div>

            <div class="hero-visual" aria-hidden="true">
                <div class="hero-image">
                    <img src="assets/images/hero-learning.svg" alt="">
                </div>
            </div>
        </div>
    </section>

    <section class="popular-categories">
        <div class="container">
            <span class="section-kicker">Explore by category</span>
            <h2 class="section-title">Popular learning categories</h2>
            <p class="section-description">Find a clear path based on the skill you want to build.</p>

            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <a class="category-card" href="courses.php?category=<?php echo urlencode((string) $category['slug']); ?>">
                        <span>📘</span>
                        <strong><?php echo landing_h($category['name']); ?></strong>
                        <small>Browse courses</small>
                    </a>
                <?php endforeach; ?>
                <a class="category-card all-category" href="courses.php">
                    <span>→</span>
                    <strong>View all courses</strong>
                    <small>Explore everything</small>
                </a>
            </div>
        </div>
    </section>

    <section class="featured-courses">
        <div class="container">
            <span class="section-kicker">Featured courses</span>
            <h2 class="section-title">Learn from approved instructors</h2>
            <p class="section-description">Published courses selected from the latest available catalogue.</p>

            <div class="course-grid">
                <?php foreach ($featuredCourses as $course): ?>
                    <article class="course-card">
                        <div class="course-thumb">
                            <img src="<?php echo landing_h(landing_thumbnail($course)); ?>" alt="<?php echo landing_h($course['title']); ?>">
                        </div>
                        <div class="course-card-content">
                            <div class="course-mini-meta">
                                <span><?php echo landing_h(ucfirst((string) $course['level'])); ?></span>
                                <span><?php echo landing_h($course['language']); ?></span>
                            </div>
                            <h3><?php echo landing_h($course['title']); ?></h3>
                            <p><?php echo landing_h($course['short_description']); ?></p>
                            <div class="course-footer">
                                <strong><?php echo landing_price($course['price']); ?></strong>
                                <a class="btn" href="course-details.php?slug=<?php echo urlencode((string) $course['slug']); ?>">View course</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="learning-paths">
        <div class="container">
            <span class="section-kicker">Learning paths</span>
            <h2 class="section-title">Choose where to begin</h2>
            <div class="path-grid">
                <?php foreach ($learningPaths as $path): ?>
                    <a class="path-card" href="courses.php?search=<?php echo urlencode($path['search']); ?>">
                        <span><?php echo $path['icon']; ?></span>
                        <h3><?php echo landing_h($path['title']); ?></h3>
                        <p><?php echo landing_h($path['description']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="how-it-works">
        <div class="container">
            <span class="section-kicker">How it works</span>
            <h2 class="section-title">From choosing a course to lifetime access</h2>
            <div class="steps-grid">
                <?php foreach ($steps as $step): ?>
                    <article class="step-card">
                        <span><?php echo landing_h($step['number']); ?></span>
                        <h3><?php echo landing_h($step['title']); ?></h3>
                        <p><?php echo landing_h($step['description']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="why-choose-us">
        <div class="container">
            <span class="section-kicker">Why CourseHub</span>
            <h2 class="section-title">A safer and clearer course workflow</h2>
            <div class="features-grid">
                <?php foreach ($features as $feature): ?>
                    <article class="feature-card">
                        <span><?php echo $feature['icon']; ?></span>
                        <h3><?php echo landing_h($feature['title']); ?></h3>
                        <p><?php echo landing_h($feature['description']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="instructor-cta">
        <div class="container">
            <div class="instructor-box">
                <div>
                    <span class="section-kicker">For instructors</span>
                    <h2>Turn your knowledge into a structured course</h2>
                    <p>Create lessons, submit courses for review, manage students, and request withdrawals from your instructor panel.</p>
                    <div class="instructor-points">
                        <span>Course builder</span>
                        <span>Student management</span>
                        <span>Sales reports</span>
                        <span>Withdrawal requests</span>
                    </div>
                    <a href="register.php?role=instructor" class="btn btn-secondary">Apply as instructor</a>
                </div>
                <div class="instructor-panel" aria-hidden="true">
                    <div class="panel-top"><strong>Instructor dashboard</strong><span>This month</span></div>
                    <div class="panel-stat"><span>Course sales</span><strong>Rs. 48,500</strong></div>
                    <div class="panel-chart"><span style="height:35%"></span><span style="height:60%"></span><span style="height:48%"></span><span style="height:75%"></span><span style="height:92%"></span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonials">
        <div class="container">
            <span class="section-kicker">Community feedback</span>
            <h2 class="section-title">Built around real platform workflows</h2>
            <div class="testimonial-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                    <article class="testimonial-card">
                        <div class="stars">★★★★★</div>
                        <p>“<?php echo landing_h($testimonial['quote']); ?>”</p>
                        <div class="testimonial-user">
                            <span><?php echo landing_h(substr($testimonial['name'], 0, 1)); ?></span>
                            <div><strong><?php echo landing_h($testimonial['name']); ?></strong><small><?php echo landing_h($testimonial['role']); ?></small></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="faq-section">
        <div class="container">
            <span class="section-kicker">Questions</span>
            <h2 class="section-title">Frequently asked questions</h2>
            <div class="faq-list">
                <?php foreach ($faqs as $faq): ?>
                    <article class="faq-item">
                        <h3><?php echo landing_h($faq['question']); ?></h3>
                        <p><?php echo landing_h($faq['answer']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="container">
            <div class="final-box">
                <h2>Start learning with CourseHub</h2>
                <p>Create an account, choose a course, and continue learning from your dashboard after enrollment is approved.</p>
                <div class="center-buttons">
                    <a href="register.php" class="btn btn-secondary">Create account</a>
                    <a href="courses.php" class="btn btn-outline">Browse courses</a>
                </div>
            </div>
        </div>
    </section>
</main>