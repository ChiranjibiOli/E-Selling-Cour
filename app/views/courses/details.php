<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$currentUser = Auth::user();
$isStudent = $currentUser && ($currentUser['role'] ?? '') === 'student';
$isInstructor = $currentUser && ($currentUser['role'] ?? '') === 'instructor';
$isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
$viewerId = (int) ($currentUser['id'] ?? 0);
$studentId = $isStudent ? (int) $currentUser['id'] : 0;
$viewerRole = (string) ($currentUser['role'] ?? 'guest');
$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
    Auth::redirect($isStudent ? 'student-browse-courses.php' : 'courses.php');
}

$course = null;
$stmt = $conn->prepare("
    SELECT
        c.*,
        cat.name AS category_name,
        COALESCE(cat.is_active, 0) AS category_active,
        u.full_name AS instructor_name,
        u.email AS instructor_email,
        u.role AS instructor_role,
        u.status AS instructor_status
    FROM courses c
    INNER JOIN users u ON c.instructor_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.slug = ?
      AND (
          (
              c.status = 'published'
              AND u.role = 'instructor'
              AND u.status = 'active'
              AND COALESCE(cat.is_active, 0) = 1
          )
          OR (? = 'instructor' AND c.instructor_id = ?)
          OR (? = 'admin' AND c.status <> 'draft')
      )
    LIMIT 1
");

if (!$stmt) {
    error_log('Course query preparation failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load this course right now.');
}

$stmt->bind_param('ssis', $slug, $viewerRole, $viewerId, $viewerRole);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows === 1) {
    $course = $result->fetch_assoc();
}
$stmt->close();

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function level_label(mixed $level): string
{
    return match ((string) $level) {
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
        default => ucfirst((string) $level),
    };
}

function type_label(mixed $type): string
{
    return match ((string) $type) {
        'text' => 'Lecture Page',
        'pdf' => 'PDF Resource',
        'word' => 'Word Resource',
        'video' => 'Video',
        'link' => 'Link',
        default => ucfirst((string) $type),
    };
}

require_once __DIR__ . '/../layouts/header.php';

if ($isStudent) {
    require_once __DIR__ . '/../layouts/student_navbar.php';
} elseif ($isInstructor) {
    require_once __DIR__ . '/../layouts/instructor_navbar.php';
} elseif ($isAdmin) {
    require_once __DIR__ . '/../layouts/admin_navbar.php';
} else {
    require_once __DIR__ . '/../layouts/navbar.php';
}

echo '<link rel="stylesheet" href="assets/css/pages/public/course-details.css?v=2">';

if (!$course) {
    http_response_code(404);
    ?>
    <main class="student-courses-page">
        <section class="student-courses-wrapper">
            <div class="empty-course-box">
                <div class="empty-icon">Not found</div>
                <h2>Course not found</h2>
                <p>This course is unavailable, inactive, or does not exist.</p>
                <a href="<?php echo $isStudent ? 'student-browse-courses.php' : 'courses.php'; ?>" class="details-btn">Back to Courses</a>
            </div>
        </section>
    </main>
    <?php if ($currentUser): ?>
        <?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
    <?php else: ?>
        <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
    <?php endif; ?>
    <?php
    exit;
}

$courseId = (int) $course['id'];
$isInstructorOwner = $isInstructor && (int) $course['instructor_id'] === $viewerId;
$isPubliclyAvailable = (string) $course['status'] === 'published'
    && (string) $course['instructor_role'] === 'instructor'
    && (string) $course['instructor_status'] === 'active'
    && (int) $course['category_active'] === 1;

$isEnrolled = false;
$isInCart = false;
$hasPendingOrder = false;

if ($isStudent) {
    $enrollStmt = $conn->prepare("
        SELECT id
        FROM enrollments
        WHERE student_id = ? AND course_id = ? AND status = 'active'
        LIMIT 1
    ");
    if ($enrollStmt) {
        $enrollStmt->bind_param('ii', $studentId, $courseId);
        $enrollStmt->execute();
        $isEnrolled = $enrollStmt->get_result()->num_rows > 0;
        $enrollStmt->close();
    }

    $cartStmt = $conn->prepare('SELECT id FROM cart WHERE student_id = ? AND course_id = ? LIMIT 1');
    if ($cartStmt) {
        $cartStmt->bind_param('ii', $studentId, $courseId);
        $cartStmt->execute();
        $isInCart = $cartStmt->get_result()->num_rows > 0;
        $cartStmt->close();
    }

    $pendingStmt = $conn->prepare("
        SELECT o.id
        FROM orders o
        INNER JOIN order_items oi ON oi.order_id = o.id
        WHERE o.student_id = ? AND oi.course_id = ? AND o.order_status = 'pending'
        LIMIT 1
    ");
    if ($pendingStmt) {
        $pendingStmt->bind_param('ii', $studentId, $courseId);
        $pendingStmt->execute();
        $hasPendingOrder = $pendingStmt->get_result()->num_rows > 0;
        $pendingStmt->close();
    }
}

$sections = [];
$contentStmt = $conn->prepare("
    SELECT
        s.id AS section_id,
        s.title AS section_title,
        s.sort_order AS section_order,
        l.id AS lesson_id,
        l.title AS lesson_title,
        l.content_type,
        l.duration_minutes,
        l.is_preview,
        l.sort_order AS lesson_order
    FROM course_sections s
    LEFT JOIN course_lessons l ON l.section_id = s.id
    WHERE s.course_id = ?
    ORDER BY s.sort_order ASC, l.sort_order ASC, l.id ASC
");

if (!$contentStmt) {
    error_log('Course content query preparation failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load the course outline right now.');
}

$contentStmt->bind_param('i', $courseId);
$contentStmt->execute();
$contentResult = $contentStmt->get_result();

while ($contentResult && $row = $contentResult->fetch_assoc()) {
    $sectionId = (int) $row['section_id'];

    if (!isset($sections[$sectionId])) {
        $sections[$sectionId] = [
            'section_title' => $row['section_title'],
            'lessons' => [],
        ];
    }

    if (!empty($row['lesson_id'])) {
        $sections[$sectionId]['lessons'][] = [
            'lesson_id' => $row['lesson_id'],
            'lesson_title' => $row['lesson_title'],
            'content_type' => $row['content_type'],
            'duration_minutes' => $row['duration_minutes'],
            'is_preview' => $row['is_preview'],
        ];
    }
}
$contentStmt->close();

$lessonCount = 0;
foreach ($sections as $section) {
    $lessonCount += count($section['lessons']);
}

$thumbnail = basename((string) ($course['thumbnail'] ?? ''));
$thumbnailPath = $thumbnail !== ''
    ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail)
    : 'assets/images/course-placeholder.svg';

if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
    $thumbnailPath = 'assets/images/course-placeholder.svg';
}
?>

<main class="course-details-page">
    <section class="course-details-wrapper">
        <div class="course-detail-hero">
            <div class="course-detail-info">
                <p class="page-label"><?php echo h($course['category_name'] ?: 'Course'); ?></p>
                <h1><?php echo h($course['title']); ?></h1>
                <p class="hero-short"><?php echo h($course['short_description'] ?: 'No short description added.'); ?></p>
                <div class="course-tags">
                    <span><?php echo h(level_label($course['level'])); ?></span>
                    <span><?php echo h($course['language']); ?></span>
                    <span><?php echo $lessonCount; ?> Lessons</span>
                    <span>Lifetime Access</span>
                </div>
                <div class="instructor-line">By <strong><?php echo h($course['instructor_name']); ?></strong></div>
            </div>

            <div class="course-buy-card">
                <div class="buy-image">
                    <img src="<?php echo h($thumbnailPath); ?>" alt="<?php echo h($course['title']); ?>">
                </div>
                <div class="buy-body">
                    <strong class="buy-price">Rs. <?php echo number_format((float) $course['price'], 2); ?></strong>

                    <?php if ($isInstructorOwner): ?>
                        <span class="owner-preview-badge">Instructor preview</span>
                        <a href="instructor-edit-course.php?id=<?php echo $courseId; ?>" class="buy-btn">Manage lessons</a>
                    <?php elseif ($isAdmin): ?>
                        <span class="owner-preview-badge">Admin quality review</span>
                        <a href="admin-courses.php" class="buy-btn">Back to course review</a>
                    <?php elseif ($isEnrolled): ?>
                        <a href="student-course-view.php?course_id=<?php echo $courseId; ?>" class="buy-btn">Go to Course</a>
                    <?php elseif ($isStudent && $isPubliclyAvailable): ?>
                        <?php if ($hasPendingOrder): ?>
                            <a href="student-my-courses.php" class="buy-btn pending-btn">Payment Pending</a>
                        <?php elseif ($isInCart): ?>
                            <a href="cart.php" class="buy-btn">Go to Cart</a>
                        <?php else: ?>
                            <form method="POST" action="add-to-cart.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                <button type="submit" class="buy-btn">Add to Cart</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (!$currentUser && $isPubliclyAvailable): ?>
                        <a href="login.php?redirect=<?php echo rawurlencode('course-details.php?slug=' . $course['slug']); ?>" class="buy-btn">Login to Buy</a>
                    <?php endif; ?>

                    <ul class="buy-features">
                        <li>Lifetime course access</li>
                        <li>Admin-approved course</li>
                        <li>Real content opens after purchase</li>
                        <li>PDF/Word resources if added</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="course-detail-grid">
            <div class="course-main-content">
                <div class="details-section">
                    <h2>Full Description</h2>
                    <div class="description-box"><?php echo nl2br(h($course['full_description'] ?: 'No full description added.')); ?></div>
                </div>

                <div class="details-section">
                    <h2>Course Outline</h2>
                    <p class="locked-note">You can see the outline before buying. Real lesson content is locked until purchase.</p>

                    <?php if ($sections === []): ?>
                        <div class="empty-outline">No lessons added yet.</div>
                    <?php else: ?>
                        <div class="outline-box">
                            <?php foreach ($sections as $section): ?>
                                <div class="outline-section">
                                    <h3><?php echo h($section['section_title']); ?></h3>
                                    <?php if ($section['lessons'] === []): ?>
                                        <p>No lessons in this section.</p>
                                    <?php else: ?>
                                        <div class="lesson-list">
                                            <?php foreach ($section['lessons'] as $lesson): ?>
                                                <div class="lesson-row">
                                                    <div>
                                                        <strong><?php echo h($lesson['lesson_title']); ?></strong>
                                                        <span><?php echo h(type_label($lesson['content_type'])); ?></span>
                                                    </div>
                                                    <span class="lock-badge"><?php echo ($isEnrolled || $isInstructorOwner || $isAdmin) ? 'Preview' : 'Locked'; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="course-side-content">
                <div class="side-card">
                    <h2>Course Info</h2>
                    <div class="side-info-list">
                        <div><span>Instructor</span><strong><?php echo h($course['instructor_name']); ?></strong></div>
                        <div><span>Category</span><strong><?php echo h($course['category_name'] ?: 'General'); ?></strong></div>
                        <div><span>Level</span><strong><?php echo h(level_label($course['level'])); ?></strong></div>
                        <div><span>Language</span><strong><?php echo h($course['language']); ?></strong></div>
                        <div><span>Duration</span><strong><?php echo h($course['duration'] ?: 'Not specified'); ?></strong></div>
                        <div><span>Total Lessons</span><strong><?php echo $lessonCount; ?></strong></div>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</main>

<?php if ($currentUser): ?>
    <?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
<?php else: ?>
    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
<?php endif; ?>
