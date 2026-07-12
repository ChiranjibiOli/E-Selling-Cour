<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);
$statusFilter = (string) ($_GET['status'] ?? 'all');
$allowedFilters = ['all', 'draft', 'pending', 'published', 'rejected'];

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

function instructor_course_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function instructor_course_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Private draft',
        'pending' => 'Pending review',
        'published' => 'Published',
        'rejected' => 'Needs revision',
        default => ucfirst($status),
    };
}

$counts = array_fill_keys(['all', 'draft', 'pending', 'published', 'rejected'], 0);
$countStmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM courses WHERE instructor_id = ? GROUP BY status");
$countStmt->bind_param('i', $instructorId);
$countStmt->execute();
$countResult = $countStmt->get_result();
while ($row = $countResult->fetch_assoc()) {
    $status = (string) $row['status'];
    $total = (int) $row['total'];
    if (isset($counts[$status])) {
        $counts[$status] = $total;
    }
    $counts['all'] += $total;
}
$countStmt->close();

$whereStatus = $statusFilter === 'all' ? '' : ' AND c.status = ?';
$sql = "
    SELECT c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
           c.level, c.language, c.status, c.updated_at, cat.name AS category_name,
           (SELECT COUNT(*) FROM course_sections s WHERE s.course_id = c.id) AS chapter_count,
           (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON s.id = l.section_id WHERE s.course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
           (SELECT COALESCE(SUM(ie.instructor_amount), 0) FROM instructor_earnings ie WHERE ie.course_id = c.id AND ie.instructor_id = c.instructor_id AND ie.earning_status <> 'refunded') AS earnings
    FROM courses c
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE c.instructor_id = ? {$whereStatus}
    ORDER BY c.updated_at DESC, c.id DESC
";
$stmt = $conn->prepare($sql);
if ($statusFilter === 'all') {
    $stmt->bind_param('i', $instructorId);
} else {
    $stmt->bind_param('is', $instructorId, $statusFilter);
}
$stmt->execute();
$result = $stmt->get_result();
$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

$pageTitle = 'My courses';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>

<style>
.course-library-page{min-height:calc(100vh - 72px);padding:34px 18px 68px}.course-library-shell{width:min(1280px,100%);margin:auto}.library-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:27px;border:1px solid #e5eaf1;border-radius:24px;background:linear-gradient(135deg,#fff,#f7f8ff);box-shadow:0 16px 45px rgba(15,23,42,.07)}.library-head h1{margin:4px 0 6px;color:#101828;font-size:clamp(1.8rem,3vw,2.5rem);letter-spacing:-.04em}.library-head p{margin:0;color:#667085}.library-kicker{color:#4f46e5!important;font-size:.72rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.create-button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;padding:0 17px;border-radius:12px;color:#fff;background:#4f46e5;text-decoration:none;font-weight:900;box-shadow:0 10px 22px rgba(79,70,229,.22)}.status-tabs{display:flex;gap:8px;margin-bottom:20px;overflow-x:auto;padding:4px}.status-tabs a{display:inline-flex;min-height:39px;align-items:center;gap:7px;padding:0 13px;border:1px solid #e2e8f0;border-radius:999px;color:#475467;background:#fff;text-decoration:none;font-size:.78rem;font-weight:850;white-space:nowrap}.status-tabs a.active{border-color:#4f46e5;color:#fff;background:#4f46e5}.status-tabs span{min-width:23px;padding:3px 6px;border-radius:999px;background:rgba(148,163,184,.18);text-align:center}.library-message{margin-bottom:18px;padding:13px 15px;border:1px solid #a7f3d0;border-radius:14px;color:#065f46;background:#ecfdf5}.course-library-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.empty-library{padding:55px 22px;border:2px dashed #cbd5e1;border-radius:22px;background:#fff;text-align:center}.empty-library h2{margin:0 0 8px}.empty-library p{margin:0 0 18px;color:#667085}@media(max-width:1050px){.course-library-grid{grid-template-columns:1fr}}@media(max-width:680px){.course-library-page{padding:20px 12px 48px}.library-head{align-items:flex-start;flex-direction:column;padding:21px}.create-button{width:100%}}
</style>

<main class="course-library-page">
    <section class="course-library-shell">
        <header class="library-head">
            <div>
                <p class="library-kicker">Instructor workspace</p>
                <h1>Course library</h1>
                <p>Manage each course from one record, from private draft to admin approval and publication.</p>
            </div>
            <a class="create-button" href="instructor-create-course.php">Create course</a>
        </header>

        <?php if (isset($_GET['submitted'])): ?>
            <div class="library-message">Course submitted to admin for quality review.</div>
        <?php endif; ?>

        <nav class="status-tabs" aria-label="Course status filters">
            <?php foreach ($allowedFilters as $filter): ?>
                <a href="instructor-courses.php?status=<?php echo $filter; ?>" class="<?php echo $statusFilter === $filter ? 'active' : ''; ?>">
                    <?php echo $filter === 'all' ? 'All courses' : instructor_course_status_label($filter); ?>
                    <span><?php echo (int) $counts[$filter]; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$courses): ?>
            <div class="empty-library"><h2>No courses in this view</h2><p>Create a course or choose another status filter.</p><a class="create-button" href="instructor-create-course.php">Create your first course</a></div>
        <?php else: ?>
            <div class="course-library-grid">
                <?php foreach ($courses as $course): ?>
                    <?php
                    $thumbnail = basename((string) ($course['thumbnail'] ?? ''));
                    $thumbnailPath = $thumbnail !== '' ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail) : 'assets/images/course-placeholder.svg';
                    $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $course['slug']);
                    $actions = [
                        ['label' => 'Preview', 'href' => $detailsUrl, 'style' => 'secondary'],
                    ];
                    if (in_array($course['status'], ['draft', 'rejected'], true)) {
                        $actions[] = [
                            'label' => $course['status'] === 'rejected' ? 'Revise course' : 'Continue draft',
                            'href' => 'instructor-create-course.php?draft_id=' . (int) $course['id'],
                            'style' => 'primary',
                        ];
                    } elseif ($course['status'] === 'published') {
                        $actions[] = [
                            'label' => 'Manage lessons',
                            'href' => 'instructor-edit-course.php?id=' . (int) $course['id'],
                            'style' => 'primary',
                        ];
                    } else {
                        $actions[] = ['label' => 'Locked during review', 'style' => 'muted', 'disabled' => true];
                    }

                    $courseCard = [
                        'context' => 'instructor',
                        'title' => $course['title'],
                        'summary' => $course['short_description'] ?: 'Course description not completed yet.',
                        'thumbnail' => $thumbnailPath,
                        'category' => $course['category_name'] ?: 'General',
                        'badge' => instructor_course_status_label((string) $course['status']),
                        'status_class' => $course['status'],
                        'eyebrow' => 'Course #' . (int) $course['id'] . ' · ' . ucfirst((string) $course['level']),
                        'language' => $course['language'] ?: 'Language not set',
                        'duration' => 'Updated ' . date('M d, Y', strtotime((string) $course['updated_at'])),
                        'price' => 'Rs. ' . number_format((float) $course['price'], 2),
                        'href' => $detailsUrl,
                        'metrics' => [
                            ['label' => 'Curriculum', 'value' => (int) $course['chapter_count'] . ' chapters · ' . (int) $course['lesson_count'] . ' lessons'],
                            ['label' => 'Students', 'value' => number_format((int) $course['student_count'])],
                            ['label' => 'Earnings', 'value' => 'Rs. ' . number_format((float) $course['earnings'], 2)],
                        ],
                        'actions' => $actions,
                    ];
                    require __DIR__ . '/../components/course_card.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>