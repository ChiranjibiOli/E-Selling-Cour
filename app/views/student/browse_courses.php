<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

/** @var mysqli $conn */
$conn = database_connection();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$courses = [];

$stmt = $conn->prepare("
    SELECT
        c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
        c.level, c.duration, c.language, cat.name AS category_name,
        instructor.full_name AS instructor_name,
        EXISTS(
            SELECT 1 FROM enrollments e
            WHERE e.student_id = ? AND e.course_id = c.id AND e.status = 'active'
        ) AS is_enrolled,
        EXISTS(
            SELECT 1 FROM cart student_cart
            WHERE student_cart.student_id = ? AND student_cart.course_id = c.id
        ) AS is_in_cart,
        EXISTS(
            SELECT 1
            FROM orders pending_order
            INNER JOIN order_items pending_item ON pending_item.order_id = pending_order.id
            WHERE pending_order.student_id = ?
              AND pending_item.course_id = c.id
              AND pending_order.order_status = 'pending'
        ) AS has_pending_order,
        (SELECT COUNT(*)
         FROM course_lessons lesson
         INNER JOIN course_sections section ON section.id = lesson.section_id
         WHERE section.course_id = c.id) AS lesson_count,
        (SELECT COUNT(*)
         FROM enrollments active_enrollment
         WHERE active_enrollment.course_id = c.id AND active_enrollment.status = 'active') AS student_count
    FROM courses c
    INNER JOIN users instructor ON instructor.id = c.instructor_id
    INNER JOIN categories cat ON cat.id = c.category_id
    WHERE c.status = 'published'
      AND instructor.role = 'instructor'
      AND instructor.status = 'active'
      AND cat.status = 'active'
    ORDER BY c.created_at DESC, c.id DESC
");
$stmt->bind_param('iii', $studentId, $studentId, $studentId);
$stmt->execute();
$result = $stmt->get_result();
while ($result && $row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

function student_browse_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$pageTitle = 'Browse courses';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>
<link rel="stylesheet" href="assets/css/pages/student/browse-courses.css?v=3">

<main class="student-page student-browse-page">
    <section class="student-section">
        <div class="container">
            <header class="dashboard-header">
                <div>
                    <p class="dashboard-subtitle">Student marketplace</p>
                    <h1>Browse published courses</h1>
                    <p>Choose an approved course, save a paid course for later, buy it now, or enroll in a free course instantly.</p>
                </div>
                <a class="btn btn-secondary" href="cart.php">Open cart</a>
            </header>

            <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success">Course added to your cart.</div>
            <?php elseif (isset($_GET['cart_error']) || isset($_GET['buy_error']) || isset($_GET['free_error'])): ?>
                <div class="alert alert-error">The course action could not be completed. Refresh and try again.</div>
            <?php elseif (isset($_GET['notfound']) || isset($_GET['invalid'])): ?>
                <div class="alert alert-warning">That course is no longer available.</div>
            <?php endif; ?>

            <?php if ($courses === []): ?>
                <div class="empty-state">
                    <h3>No published courses yet</h3>
                    <p>Courses appear here only after admin approval while the instructor and category remain active.</p>
                </div>
            <?php else: ?>
                <div class="student-course-grid" data-page-size="12">
                    <?php foreach ($courses as $course): ?>
                        <?php
                        $thumbnail = basename((string) ($course['thumbnail'] ?? ''));
                        $thumbnailPath = $thumbnail !== ''
                            ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail)
                            : 'assets/images/course-placeholder.svg';

                        if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
                            $thumbnailPath = 'assets/images/course-placeholder.svg';
                        }

                        $courseId = (int) $course['id'];
                        $isFree = (float) $course['price'] <= 0;
                        $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $course['slug']);
                        $actions = [
                            ['label' => 'View details', 'href' => $detailsUrl, 'style' => 'secondary'],
                        ];

                        if ((int) $course['is_enrolled'] === 1) {
                            $actions[] = [
                                'label' => 'Continue learning',
                                'href' => 'student-course-view.php?course_id=' . $courseId,
                                'style' => 'primary',
                            ];
                        } elseif ($isFree) {
                            $actions[] = [
                                'label' => 'Enroll Free',
                                'href' => 'enroll-free-course.php',
                                'method' => 'post',
                                'style' => 'primary',
                                'hidden' => ['course_id' => $courseId],
                            ];
                        } elseif ((int) $course['has_pending_order'] === 1) {
                            $actions[] = [
                                'label' => 'Payment Pending',
                                'style' => 'muted',
                                'disabled' => true,
                            ];
                        } elseif ((int) $course['is_in_cart'] === 1) {
                            $actions[] = ['label' => 'View cart', 'href' => 'cart.php', 'style' => 'primary'];
                        } else {
                            $actions[] = [
                                'label' => 'Add to cart',
                                'href' => 'add-to-cart.php',
                                'method' => 'post',
                                'style' => 'primary',
                                'hidden' => ['course_id' => $courseId],
                            ];
                        }

                        $courseCard = [
                            'context' => 'student',
                            'title' => $course['title'],
                            'summary' => $course['short_description'] ?: 'No description available.',
                            'thumbnail' => $thumbnailPath,
                            'category' => $course['category_name'] ?: 'General',
                            'badge' => ucfirst((string) $course['level']),
                            'eyebrow' => 'By ' . $course['instructor_name'],
                            'language' => $course['language'] ?: 'Language not set',
                            'duration' => $course['duration'] ?: 'Self-paced',
                            'price' => $isFree ? 'Free' : 'Rs. ' . number_format((float) $course['price'], 2),
                            'href' => $detailsUrl,
                            'rating_label' => $isFree
                                ? 'Instant lifetime access'
                                : ((int) $course['has_pending_order'] === 1 ? 'Waiting for verification' : 'Published course'),
                            'metrics' => [
                                ['label' => 'Lessons', 'value' => (string) (int) $course['lesson_count']],
                                ['label' => 'Students', 'value' => number_format((int) $course['student_count'])],
                            ],
                            'actions' => $actions,
                        ];

                        require __DIR__ . '/../components/course_card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
