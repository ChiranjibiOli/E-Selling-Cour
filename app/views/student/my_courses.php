<?php

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$myCourses = [];
$pendingOrders = [];

$sql = "
    SELECT e.id AS enrollment_id, e.granted_at, e.access_type, e.status AS enrollment_status,
           c.id AS course_id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
           c.level, c.language, c.duration,
           cat.name AS category_name, u.full_name AS instructor_name,
           (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON l.section_id = s.id WHERE s.course_id = c.id) AS lesson_count
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    INNER JOIN users u ON c.instructor_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE e.student_id = ? AND e.status = 'active'
    ORDER BY e.granted_at DESC
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && $row = $result->fetch_assoc()) {
        $myCourses[] = $row;
    }
    $stmt->close();
}

$pendingSql = "
    SELECT o.id AS order_id, o.final_amount, o.order_status, o.created_at,
           GROUP_CONCAT(c.title SEPARATOR ', ') AS course_titles
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN courses c ON oi.course_id = c.id
    WHERE o.student_id = ? AND o.order_status = 'pending'
    GROUP BY o.id, o.final_amount, o.order_status, o.created_at
    ORDER BY o.created_at DESC
";
$pendingStmt = $conn->prepare($pendingSql);
if ($pendingStmt) {
    $pendingStmt->bind_param('i', $studentId);
    $pendingStmt->execute();
    $pendingResult = $pendingStmt->get_result();
    while ($pendingResult && $row = $pendingResult->fetch_assoc()) {
        $pendingOrders[] = $row;
    }
    $pendingStmt->close();
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function level_label($level)
{
    return ucfirst((string) $level);
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>

<main class="student-my-courses-page">
    <section class="student-my-courses-wrapper">
        <div class="my-courses-header">
            <div>
                <p class="page-label">Student Panel</p>
                <h1>My Courses</h1>
                <p>Courses appear here only after admin verifies your payment and activates your enrollment.</p>
            </div>
        </div>

        <?php if ($pendingOrders): ?>
            <div class="pending-orders-box">
                <h2>Pending Payment Verification</h2>
                <p>These orders are waiting for admin approval.</p>
                <div class="pending-order-list">
                    <?php foreach ($pendingOrders as $order): ?>
                        <div class="pending-order-card">
                            <div><strong>Order #<?php echo (int) $order['order_id']; ?></strong><p><?php echo h($order['course_titles']); ?></p></div>
                            <span>Rs. <?php echo number_format((float) $order['final_amount'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$myCourses): ?>
            <div class="empty-my-courses-box">
                <div class="empty-icon">No courses</div>
                <h2>No active courses yet</h2>
                <p>If you already submitted payment proof, wait for admin verification. After admin verifies payment, your course will appear here.</p>
                <a href="courses.php" class="browse-btn">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="my-courses-grid">
                <?php foreach ($myCourses as $course): ?>
                    <?php
                    $thumbnail = basename((string) ($course['thumbnail'] ?? ''));
                    $thumbnailPath = $thumbnail !== '' ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail) : 'assets/images/course-placeholder.svg';
                    if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
                        $thumbnailPath = 'assets/images/course-placeholder.svg';
                    }
                    $learningUrl = 'student-course-view.php?course_id=' . (int) $course['course_id'];
                    $courseCard = [
                        'context' => 'student',
                        'title' => $course['title'],
                        'summary' => $course['short_description'] ?: 'No description added.',
                        'thumbnail' => $thumbnailPath,
                        'category' => $course['category_name'] ?: 'General',
                        'badge' => level_label($course['level']),
                        'eyebrow' => 'By ' . $course['instructor_name'],
                        'language' => $course['language'] ?: 'Language not set',
                        'duration' => $course['duration'] ?: 'Self-paced',
                        'price' => ucfirst((string) $course['access_type']) . ' access',
                        'href' => $learningUrl,
                        'metrics' => [
                            ['label' => 'Lessons', 'value' => (string) ((int) $course['lesson_count'])],
                            ['label' => 'Enrolled', 'value' => !empty($course['granted_at']) ? date('M d, Y', strtotime((string) $course['granted_at'])) : 'Recently'],
                        ],
                        'actions' => [
                            ['label' => 'Continue learning', 'href' => $learningUrl, 'style' => 'primary'],
                        ],
                    ];
                    require __DIR__ . '/../components/course_card.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>