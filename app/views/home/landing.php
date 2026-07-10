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
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=1">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=1">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=1">
<link rel="stylesheet" href="assets/css/components/footer.css?v=1">

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
                    <div>
                        <strong><?php echo number_format($stats['students']); ?>+</strong>
                        <span>Students</span>
                    </div>
                    <div>
                        <strong><?php echo number_format($stats['courses']); ?>+</strong>
                        <span>Courses</span>
                    </div>
                    <div>
                        <strong>Lifetime</strong>
                        <span>Access</span>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-image">
                    <img src="assets/images/hero-banner.jpg" alt="Student learning online">
                </div>

                <div class="floating-card progress-card">
                    <span>📈</span>
                    <div>
                        <strong>75%</strong>
                        <small>Learning progress</small>
                    </div>
                </div>

                <div class="floating-card instructor-card">
                    <span>⭐</span>
                    <div>
                        <strong>Approved</strong>
                        <small>Instructor courses</small>
                    </div>
                </div>

                <div class="floating-card payment-card">
                    <span>💳</span>
                    <div>
                        <strong>Verified</strong>
                        <small>Payment access</small>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <
    <section class="stats-section" aria-label="Platform totals">
        <div class="container stats-grid">
            <div class="stat-card">
                <span>👨‍🎓</span>
                <h2><?php echo number_format($stats['students']); ?>+</h2>
                <p>Active students</p>
            </div>

            <div class="stat-card">
                <span>📚</span>
                <h2><?php echo number_format($stats['courses']); ?>+</h2>
                <p>Published courses</p>
            </div>

            <div class="stat-card">
                <span>👨‍🏫</span>
                <h2><?php echo number_format($stats['instructors']); ?>+</h2>
                <p>Approved instructors</p>
            </div>

            <div class="stat-card">
                <span>🎯</span>
                <h2><?php echo number_format($stats['enrollments']); ?>+</h2>
                <p>Active enrollments</p>
            </div>
        </div>
    </section>

    
    <?php if (!empty($categories)): ?>
        <section class="categories section-padding">
            <div class="container">

                <div class="section-heading">
                    <span class="section-kicker">Explore subjects</span>
                    <h2>Popular course categories</h2>
                    <p>Choose a subject and start learning from approved instructors.</p>
                </div>

                <div class="category-grid">
                    <a class="category-card all-category" href="courses.php">
                        <span>🚀</span>
                        <strong>All Courses</strong>
                        <small>Browse everything</small>
                    </a>

                    <?php foreach ($categories as $index => $category): ?>
                        <?php
                            $icons = ['💻', '🛡️', '📊', '🎨', '📱', '🌐', '📷', '🧠'];
                            $icon = $icons[$index % count($icons)];
                        ?>
                        <a
                            class="category-card"
                            href="courses.php?category_id=<?php echo (int) $category['id']; ?>"
                        >
                            <span><?php echo $icon; ?></span>
                            <strong><?php echo landing_h($category['name']); ?></strong>
                            <small>View courses</small>
                        </a>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>
    <?php endif; ?>


    <section class="featured-courses section-padding">
        <div class="container">

            <div class="section-heading section-between">
                <div>
                    <span class="section-kicker">Featured learning</span>
                    <h2>Courses students are exploring</h2>
                    <p>Recently published and highlighted courses from approved instructors.</p>
                </div>

                <a href="courses.php" class="section-link">View all courses →</a>
            </div>

            <?php if (empty($featuredCourses)): ?>
                <div class="empty-state">
                    <span>📚</span>
                    <h3>Courses are being prepared</h3>
                    <p>Approved courses will appear here after instructors publish them.</p>
                    <a href="register.php?role=instructor" class="btn btn-primary">Become an instructor</a>
                </div>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($featuredCourses as $course): ?>
                        <article class="course-card">
                            <div class="course-thumb">
                                <img
                                    src="<?php echo landing_h(landing_thumbnail($course)); ?>"
                                    alt="<?php echo landing_h($course['title']); ?>"
                                
                                <span class="course-badge">
                                    <?php echo landing_h(ucfirst($course['level'] ?? 'Beginner')); ?>
                                </span>
                            </div>

                            <div class="course-card-content">
                                <div class="course-mini-meta">
                                    <span>👨‍🏫 <?php echo landing_h($course['instructor_name']); ?></span>
                                    <span>⭐ 4.8</span>
                                </div>

                                <h3><?php echo landing_h($course['title']); ?></h3>

                                <p>
                                    <?php echo landing_h($course['short_description'] ?: 'Course details coming soon.'); ?>
                                </p>

                                <div class="course-info-row">
                                    <span>🌐 <?php echo landing_h($course['language'] ?: 'English'); ?></span>
                                    <span>⏱️ <?php echo landing_h($course['duration'] ?: 'Flexible'); ?></span>
                                </div>

                                <div class="course-footer">
                                    <strong><?php echo landing_price($course['price']); ?></strong>
                                    <a
                                        href="course-details.php?slug=<?php echo urlencode($course['slug']); ?>"
                                        class="btn btn-primary"
                                    >
                                        View details
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>


    <section class="learning-paths section-padding">
        <div class="container">

            <div class="section-heading">
                <span class="section-kicker">Learning paths</span>
                <h2>Start from your goal</h2>
                <p>Find courses based on what you want to become or improve.</p>
            </div>

            <div class="path-grid">
                <?php foreach ($learningPaths as $path): ?>
                    <a class="path-card" href="courses.php?search=<?php echo urlencode($path['search']); ?>">
                        <span><?php echo landing_h($path['icon']); ?></span>
                        <h3><?php echo landing_h($path['title']); ?></h3>
                        <p><?php echo landing_h($path['description']); ?></p>
                        <strong>Explore path →</strong>
                    </a>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    
    <section class="how-it-works section-padding">
        <div class="container">

            <div class="section-heading">
                <span class="section-kicker">Simple process</span>
                <h2>How learning works here</h2>
                <p>Clear workflow for students, instructors, and administrators.</p>
            </div>

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

    
    <section class="why-choose-us section-padding">
        <div class="container">

            <div class="section-heading">
                <span class="section-kicker">Why choose us</span>
                <h2>A trusted course platform workflow</h2>
                <p>Designed for course selling, access control, instructor approval, and payment verification.</p>
            </div>

            <div class="features-grid">
                <?php foreach ($features as $feature): ?>
                    <article class="feature-card">
                        <span><?php echo landing_h($feature['icon']); ?></span>
                        <h3><?php echo landing_h($feature['title']); ?></h3>
                        <p><?php echo landing_h($feature['description']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    
    <section class="instructor-cta section-padding">
        <div class="container">
            <div class="instructor-box">

                <div>
                    <span class="section-kicker light">For instructors</span>
                    <h2>Teach your skills and grow your online presence</h2>
                    <p>
                        Create your instructor profile, submit your account for approval,
                        prepare your course, and publish after admin review.
                    </p>

                    <div class="instructor-points">
                        <span>✅ Admin-approved instructor account</span>
                        <span>✅ Create courses and lessons</span>
                        <span>✅ Track students and sales</span>
                    </div>

                    <a href="register.php?role=instructor" class="btn btn-light">Start teaching</a>
                </div>

                <div class="instructor-panel">
                    <div class="panel-top">
                        <strong>Instructor Panel</strong>
                        <span>Approved</span>
                    </div>

                    <div class="panel-stat">
                        <small>Total students</small>
                        <strong>2,540</strong>
                    </div>

                    <div class="panel-stat">
                        <small>Course sales</small>
                        <strong>Rs. 98,450</strong>
                    </div>

                    <div class="panel-chart">
                        <i></i><i></i><i></i><i></i><i></i>
                    </div>
                </div>

            </div>
        </div>
    </section>

    
    <section class="testimonials section-padding">
        <div class="container">

            <div class="section-heading">
                <span class="section-kicker">User feedback</span>
                <h2>Built for students, instructors, and admins</h2>
                <p>A simple platform experience for real academic project requirements.</p>
            </div>

            <div class="testimonial-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                    <article class="testimonial-card">
                        <div class="stars">★★★★★</div>
                        <p>“<?php echo landing_h($testimonial['quote']); ?>”</p>
                        <div class="testimonial-user">
                            <span><?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?></span>
                            <div>
                                <strong><?php echo landing_h($testimonial['name']); ?></strong>
                                <small><?php echo landing_h($testimonial['role']); ?></small>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>


    <section class="faq-section section-padding">
        <div class="container">

            <div class="section-heading">
                <span class="section-kicker">Questions</span>
                <h2>Frequently asked questions</h2>
            </div>

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
    <section class="final-cta section-padding">
        <div class="container">
            <div class="final-box">
                <h2>Ready to start learning?</h2>
                <p>Explore courses, choose your skill path, and begin after payment verification.</p>

                <div class="hero-buttons center-buttons">
                    <a href="courses.php" class="btn btn-primary">Browse courses</a>
                    <a href="register.php" class="btn btn-secondary">Create account</a>
                </div>
            </div>
        </div>
    </section>

</main>