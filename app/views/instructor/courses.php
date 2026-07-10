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
$countStmt = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM courses
    WHERE instructor_id = ?
    GROUP BY status
");
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
    SELECT
        c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
        c.level, c.language, c.status, c.updated_at, cat.name AS category_name,
        (SELECT COUNT(*) FROM course_sections s WHERE s.course_id = c.id) AS chapter_count,
        (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON s.id = l.section_id WHERE s.course_id = c.id) AS lesson_count,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
        (SELECT COALESCE(SUM(ie.instructor_amount), 0) FROM instructor_earnings ie WHERE ie.course_id = c.id AND ie.instructor_id = c.instructor_id AND ie.earning_status <> 'refunded') AS earnings
    FROM courses c
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE c.instructor_id = ?
    {$whereStatus}
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
.course-library-page{min-height:calc(100vh - 72px);padding:34px 18px 68px}.course-library-shell{width:min(1280px,100%);margin:auto}.library-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:27px;border:1px solid #e5eaf1;border-radius:24px;background:linear-gradient(135deg,#fff,#f7f8ff);box-shadow:0 16px 45px rgba(15,23,42,.07)}.library-head h1{margin:4px 0 6px;color:#101828;font-size:clamp(1.8rem,3vw,2.5rem);letter-spacing:-.04em}.library-head p{margin:0;color:#667085}.library-kicker{color:#4f46e5!important;font-size:.72rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.create-button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;padding:0 17px;border-radius:12px;color:#fff;background:#4f46e5;text-decoration:none;font-weight:900;box-shadow:0 10px 22px rgba(79,70,229,.22)}.status-tabs{display:flex;gap:8px;margin-bottom:20px;overflow-x:auto;padding:4px}.status-tabs a{display:inline-flex;min-height:39px;align-items:center;gap:7px;padding:0 13px;border:1px solid #e2e8f0;border-radius:999px;color:#475467;background:#fff;text-decoration:none;font-size:.78rem;font-weight:850;white-space:nowrap}.status-tabs a.active{border-color:#4f46e5;color:#fff;background:#4f46e5}.status-tabs span{min-width:23px;padding:3px 6px;border-radius:999px;background:rgba(148,163,184,.18);text-align:center}.library-message{margin-bottom:18px;padding:13px 15px;border:1px solid #a7f3d0;border-radius:14px;color:#065f46;background:#ecfdf5}.course-library-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.library-course-card{display:grid;grid-template-columns:190px minmax(0,1fr);overflow:hidden;border:1px solid #e5eaf1;border-radius:21px;background:#fff;box-shadow:0 11px 30px rgba(15,23,42,.06);transition:.18s ease}.library-course-card:hover{transform:translateY(-3px);border-color:#c7d2fe;box-shadow:0 20px 48px rgba(79,70,229,.1)}.course-cover{position:relative;min-height:230px;background:#e2e8f0}.course-cover img{width:100%;height:100%;object-fit:cover}.course-status{position:absolute;top:12px;left:12px;padding:7px 10px;border-radius:999px;font-size:.68rem;font-weight:900;box-shadow:0 6px 16px rgba(15,23,42,.15)}.status-draft{color:#475467;background:#f1f5f9}.status-pending{color:#92400e;background:#fef3c7}.status-published{color:#065f46;background:#d1fae5}.status-rejected{color:#991b1b;background:#fee2e2}.course-card-body{display:flex;min-width:0;flex-direction:column;padding:18px}.course-id{color:#98a2b3;font-size:.68rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.course-card-body h2{display:-webkit-box;overflow:hidden;margin:6px 0 8px;color:#101828;font-size:1.1rem;line-height:1.35;-webkit-line-clamp:2;-webkit-box-orient:vertical}.course-summary{display:-webkit-box;overflow:hidden;margin:0;color:#667085;font-size:.8rem;line-height:1.55;-webkit-line-clamp:2;-webkit-box-orient:vertical}.course-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:15px 0}.course-meta div{padding:9px 10px;border-radius:10px;background:#f8fafc}.course-meta span{display:block;color:#98a2b3;font-size:.62rem;font-weight:850;text-transform:uppercase}.course-meta strong{display:block;margin-top:2px;color:#344054;font-size:.78rem}.course-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto}.course-actions a{display:inline-flex;min-height:37px;align-items:center;justify-content:center;padding:0 12px;border-radius:10px;text-decoration:none;font-size:.75rem;font-weight:900}.action-primary{color:#fff;background:#4f46e5}.action-secondary{color:#4338ca;background:#eef2ff}.action-muted{color:#475467;background:#f1f5f9}.empty-library{padding:55px 22px;border:2px dashed #cbd5e1;border-radius:22px;background:#fff;text-align:center}.empty-library h2{margin:0 0 8px}.empty-library p{margin:0 0 18px;color:#667085}@media(max-width:1050px){.course-library-grid{grid-template-columns:1fr}}@media(max-width:680px){.course-library-page{padding:20px 12px 48px}.library-head{align-items:flex-start;flex-direction:column;padding:21px}.create-button{width:100%}.library-course-card{grid-template-columns:1fr}.course-cover{height:180px;min-height:0}.course-meta{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<main class="course-library-page">
    <section class="course-library-shell">
        <header class="library-head">
            <div>
                <p class="library-kicker">Instructor workspace</p>
                <h1>Course library</h1>
                <p>Manage each course from one record—from private draft to admin approval and publication.</p>
            </div>
            <a class="create-button" href="instructor-create-course.php">Create course</a>
        </header>

        <?php if (isset($_GET['submitted'])): ?>
            <div class="library-message">Course submitted to admin for quality review.</div>
        <?php endif; ?>

        <nav class="status-tabs" aria-label="Course status filters">
            <?php foreach ($allowedFilters as $filter): ?>
                <a href="instructor-courses.php?status=<?php echo $filter; ?>"
                   class="<?php echo $statusFilter === $filter ? 'active' : ''; ?>">
                    <?php echo $filter === 'all' ? 'All courses' : instructor_course_status_label($filter); ?>
                    <span><?php echo (int) $counts[$filter]; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$courses): ?>
            <div class="empty-library">
                <h2>No courses in this view</h2>
                <p>Create a course or choose another status filter.</p>
                <a class="create-button" href="instructor-create-course.php">Create your first course</a>
            </div>
        <?php else: ?>
            <div class="course-library-grid">
                <?php foreach ($courses as $course): ?>
                    <?php
                    $thumbnail = basename((string) ($course['thumbnail'] ?? ''));
                    $thumbnailPath = $thumbnail !== ''
                        ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail)
                        : 'assets/images/course-placeholder.svg';
                    ?>
                    <article class="library-course-card">
                        <div class="course-cover">
                            <img src="<?php echo instructor_course_h($thumbnailPath); ?>"
                                 alt="<?php echo instructor_course_h($course['title']); ?>">
                            <span class="course-status status-<?php echo instructor_course_h($course['status']); ?>">
                                <?php echo instructor_course_h(instructor_course_status_label($course['status'])); ?>
                            </span>
                        </div>
                        <div class="course-card-body">
                            <span class="course-id">Course #<?php echo (int) $course['id']; ?></span>
                            <h2><?php echo instructor_course_h($course['title']); ?></h2>
                            <p class="course-summary">
                                <?php echo instructor_course_h($course['short_description'] ?: 'Course description not completed yet.'); ?>
                            </p>

                            <div class="course-meta">
                                <div><span>Curriculum</span><strong><?php echo (int) $course['chapter_count']; ?> chapters · <?php echo (int) $course['lesson_count']; ?> lessons</strong></div>
                                <div><span>Students</span><strong><?php echo (int) $course['student_count']; ?></strong></div>
                                <div><span>Price</span><strong>Rs. <?php echo number_format((float) $course['price'], 2); ?></strong></div>
                                <div><span>Earnings</span><strong>Rs. <?php echo number_format((float) $course['earnings'], 2); ?></strong></div>
                            </div>

                            <div class="course-actions">
                                <a class="action-secondary" href="course-details.php?slug=<?php echo rawurlencode($course['slug']); ?>">Preview</a>

                                <?php if (in_array($course['status'], ['draft', 'rejected'], true)): ?>
                                    <a class="action-primary" href="instructor-create-course.php?draft_id=<?php echo (int) $course['id']; ?>">
                                        <?php echo $course['status'] === 'rejected' ? 'Revise course' : 'Continue draft'; ?>
                                    </a>
                                <?php elseif ($course['status'] === 'published'): ?>
                                    <a class="action-primary" href="instructor-edit-course.php?id=<?php echo (int) $course['id']; ?>">Manage lessons</a>
                                <?php else: ?>
                                    <span class="action-muted">Locked during review</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
