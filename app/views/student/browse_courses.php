<?php

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();

if (!$user) {
    Auth::redirect('login.php');
}

$sql = "
    SELECT 
        id,
        title,
        short_description,
        thumbnail,
        price,
        level,
        duration,
        language
    FROM courses
    WHERE status = 'published'
    ORDER BY created_at DESC
";

$courses = $conn->query($sql);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>
<link rel="stylesheet" href="assets/css/navbars/student-navbar.css?v=1">
<link rel="stylesheet" href="assets/css/components/filter-bar.css?v=1">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=1">
<link rel="stylesheet" href="assets/css/pages/student/browse-courses.css?v=1">

<main class="student-page">
    <section class="student-section">
        <div class="container">

            <div class="dashboard-header">
                <div>
                    <p class="dashboard-subtitle">Student Panel</p>
                    <h1>Browse Courses</h1>
                    <p>Explore available courses without leaving your student panel.</p>
                </div>
            </div>

            <?php if ($courses && $courses->num_rows > 0): ?>
                <div class="student-course-grid">

                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <div class="student-course-card">

                            <div class="student-course-image">
                                <?php if (!empty($course['thumbnail'])): ?>
                                    <img 
                                        src="assets/uploads/course_thumbnails/<?php echo htmlspecialchars($course['thumbnail']); ?>" 
                                        alt="Course thumbnail"
                                    >
                                <?php else: ?>
                                    <img 
                                        src="assets/images/course-placeholder.svg"
                                        alt="Default course"
                                    >
                                <?php endif; ?>
                            </div>

                            <div class="student-course-body">
                                <h3><?php echo htmlspecialchars($course['title']); ?></h3>

                                <p>
                                    <?php echo htmlspecialchars($course['short_description'] ?? 'No description available.'); ?>
                                </p>

                                <div class="student-course-meta">
                                    <span><?php echo htmlspecialchars($course['level']); ?></span>
                                    <span><?php echo htmlspecialchars($course['duration'] ?? 'N/A'); ?></span>
                                    <span><?php echo htmlspecialchars($course['language'] ?? 'English'); ?></span>
                                </div>

                                <p class="course-price">
                                    Rs. <?php echo htmlspecialchars($course['price']); ?>
                                </p>

                                <button class="btn btn-primary" disabled>
                                    Purchase Feature Coming Soon
                                </button>
                            </div>

                        </div>
                    <?php endwhile; ?>

                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No published courses yet</h3>
                    <p>Courses will appear here after admin/instructor publishes them.</p>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>

</body>
</html>
