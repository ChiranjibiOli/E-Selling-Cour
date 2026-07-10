<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/course_workflow_helper.php';

AdminMiddleware::handle();

$admin = Auth::user();
$adminId = (int) ($admin['id'] ?? 0);
$message = '';
$messageType = '';
$statusFilter = (string) ($_GET['status'] ?? 'pending');
$allowedFilters = ['pending', 'published', 'rejected', 'all'];

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'pending';
}

function admin_course_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_course_label(string $status): string
{
    return match ($status) {
        'pending' => 'Awaiting review',
        'published' => 'Published',
        'rejected' => 'Rejected',
        default => ucfirst($status),
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requirePost();
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
    $errors = [];

    if ($courseId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        $errors[] = 'Invalid course review request.';
    }

    if ($decision === 'reject' && $reviewNote === '') {
        $errors[] = 'Add a clear reason before rejecting the course.';
    }

    if (!$errors) {
        $courseStmt = $conn->prepare("
            SELECT id, instructor_id, title, status
            FROM courses
            WHERE id = ? AND status = 'pending'
            LIMIT 1
            FOR UPDATE
        ");

        try {
            $conn->begin_transaction();
            $courseStmt->bind_param('i', $courseId);
            $courseStmt->execute();
            $course = $courseStmt->get_result()->fetch_assoc() ?: null;
            $courseStmt->close();

            if (!$course) {
                throw new RuntimeException('This course is no longer waiting for review.');
            }

            $newStatus = $decision === 'approve' ? 'published' : 'rejected';
            $update = $conn->prepare("
                UPDATE courses
                SET status = ?, reviewed_at = NOW(), reviewed_by = ?, review_note = ?
                WHERE id = ? AND status = 'pending'
            ");
            $update->bind_param('sisi', $newStatus, $adminId, $reviewNote, $courseId);
            $update->execute();

            if ($update->affected_rows !== 1) {
                throw new RuntimeException('The course status changed before this review completed.');
            }

            $update->close();

            $markLogs = $conn->prepare("
                UPDATE course_change_logs
                SET reviewed_by = ?, reviewed_at = NOW()
                WHERE course_id = ? AND reviewed_at IS NULL
            ");
            $markLogs->bind_param('ii', $adminId, $courseId);
            $markLogs->execute();
            $markLogs->close();

            course_workflow_notify_instructor(
                $conn,
                (int) $course['instructor_id'],
                $courseId,
                (string) $course['title'],
                $newStatus,
                $reviewNote
            );

            $conn->commit();
            header('Location: admin-courses.php?status=' . rawurlencode($statusFilter) . '&reviewed=' . $newStatus);
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('Course review failed: ' . $exception->getMessage());
            $message = $exception->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

if (isset($_GET['reviewed'])) {
    $message = $_GET['reviewed'] === 'published'
        ? 'Course approved and published.'
        : 'Course rejected and returned to the instructor.';
    $messageType = 'success';
}

$counts = ['all' => 0, 'pending' => 0, 'published' => 0, 'rejected' => 0];
$countResult = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM courses
    WHERE status IN ('pending', 'published', 'rejected')
    GROUP BY status
");

while ($countResult && $row = $countResult->fetch_assoc()) {
    $status = (string) $row['status'];
    if (isset($counts[$status])) {
        $counts[$status] = (int) $row['total'];
        $counts['all'] += (int) $row['total'];
    }
}

$where = $statusFilter === 'all'
    ? "c.status IN ('pending', 'published', 'rejected')"
    : 'c.status = ?';

$sql = "
    SELECT
        c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
        c.level, c.language, c.status, c.submitted_at, c.updated_at,
        c.review_note, u.full_name AS instructor_name, u.email AS instructor_email,
        cat.name AS category_name,
        (SELECT COUNT(*) FROM course_sections s WHERE s.course_id = c.id) AS chapter_count,
        (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON s.id = l.section_id WHERE s.course_id = c.id) AS lesson_count,
        (SELECT COUNT(*) FROM course_change_logs cl WHERE cl.course_id = c.id AND cl.reviewed_at IS NULL) AS pending_change_count
    FROM courses c
    INNER JOIN users u ON u.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE {$where}
    ORDER BY
        CASE c.status WHEN 'pending' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END,
        COALESCE(c.submitted_at, c.updated_at) DESC
";
$stmt = $conn->prepare($sql);

if ($statusFilter !== 'all') {
    $stmt->bind_param('s', $statusFilter);
}

$stmt->execute();
$result = $stmt->get_result();
$courses = [];

while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

$stmt->close();
$pageTitle = 'Course review';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>

<style>
.review-page{min-height:calc(100vh - 72px);padding:34px 18px 68px}.review-shell{width:min(1320px,100%);margin:auto}.review-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:27px;border:1px solid #e5eaf1;border-radius:24px;background:linear-gradient(135deg,#fff,#f7f8ff);box-shadow:0 16px 45px rgba(15,23,42,.07)}.review-head h1{margin:4px 0 6px;color:#101828;font-size:clamp(1.8rem,3vw,2.5rem);letter-spacing:-.04em}.review-head p{margin:0;color:#667085}.review-kicker{color:#4f46e5!important;font-size:.72rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.review-tabs{display:flex;gap:8px;margin-bottom:20px;overflow-x:auto}.review-tabs a{display:inline-flex;min-height:40px;align-items:center;gap:8px;padding:0 14px;border:1px solid #e2e8f0;border-radius:999px;color:#475467;background:#fff;text-decoration:none;font-size:.78rem;font-weight:850;white-space:nowrap}.review-tabs a.active{border-color:#4f46e5;color:#fff;background:#4f46e5}.review-tabs span{padding:3px 7px;border-radius:999px;background:rgba(148,163,184,.18)}.review-alert{margin-bottom:18px;padding:14px 16px;border-radius:14px}.review-alert.success{border:1px solid #a7f3d0;color:#065f46;background:#ecfdf5}.review-alert.error{border:1px solid #fecaca;color:#991b1b;background:#fef2f2}.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.review-card{overflow:hidden;border:1px solid #e5eaf1;border-radius:21px;background:#fff;box-shadow:0 11px 30px rgba(15,23,42,.06)}.review-cover{position:relative;height:185px;background:#e2e8f0}.review-cover img{width:100%;height:100%;object-fit:cover}.review-status{position:absolute;top:12px;left:12px;padding:7px 10px;border-radius:999px;font-size:.68rem;font-weight:900}.review-status.pending{color:#92400e;background:#fef3c7}.review-status.published{color:#065f46;background:#d1fae5}.review-status.rejected{color:#991b1b;background:#fee2e2}.change-flag{position:absolute;top:12px;right:12px;padding:7px 10px;border-radius:999px;color:#3730a3;background:#eef2ff;font-size:.68rem;font-weight:900}.review-body{padding:18px}.review-id{color:#98a2b3;font-size:.68rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.review-body h2{margin:6px 0 6px;color:#101828;font-size:1.15rem}.instructor-line{margin:0;color:#667085;font-size:.78rem}.review-summary{display:-webkit-box;overflow:hidden;margin:12px 0;color:#667085;font-size:.82rem;line-height:1.55;-webkit-line-clamp:2;-webkit-box-orient:vertical}.review-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:14px 0}.review-meta div{padding:9px;border-radius:10px;background:#f8fafc}.review-meta span{display:block;color:#98a2b3;font-size:.61rem;font-weight:850;text-transform:uppercase}.review-meta strong{display:block;margin-top:2px;color:#344054;font-size:.75rem}.review-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:15px}.review-actions a,.review-actions button{display:inline-flex;min-height:39px;align-items:center;justify-content:center;border:0;border-radius:10px;padding:0 13px;text-decoration:none;font:inherit;font-size:.75rem;font-weight:900;cursor:pointer}.view-course{color:#4338ca;background:#eef2ff}.approve-course{color:#fff;background:#059669}.reject-toggle{color:#b91c1c;background:#fee2e2}.reject-form{display:none;margin-top:12px;padding:12px;border-radius:12px;background:#fef2f2}.reject-form.open{display:grid;gap:10px}.reject-form textarea{width:100%;min-height:85px;border:1px solid #fca5a5;border-radius:10px;padding:10px;resize:vertical}.reject-submit{min-height:39px;border:0;border-radius:10px;color:#fff;background:#dc2626;font-weight:900;cursor:pointer}.empty-review{padding:55px 22px;border:2px dashed #cbd5e1;border-radius:22px;background:#fff;text-align:center;color:#667085}@media(max-width:950px){.review-grid{grid-template-columns:1fr}}@media(max-width:650px){.review-page{padding:20px 12px 48px}.review-head{align-items:flex-start;flex-direction:column;padding:21px}.review-meta{grid-template-columns:1fr 1fr}.review-cover{height:160px}}
</style>

<main class="review-page">
    <section class="review-shell">
        <header class="review-head">
            <div>
                <p class="review-kicker">Quality control</p>
                <h1>Course review</h1>
                <p>Only instructor submissions and previously reviewed courses appear here. Private drafts never enter admin review.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="review-alert <?php echo admin_course_h($messageType); ?>"><?php echo admin_course_h($message); ?></div>
        <?php endif; ?>

        <nav class="review-tabs" aria-label="Course review filters">
            <?php foreach ($allowedFilters as $filter): ?>
                <a href="admin-courses.php?status=<?php echo $filter; ?>" class="<?php echo $statusFilter === $filter ? 'active' : ''; ?>">
                    <?php echo $filter === 'all' ? 'All reviewed courses' : admin_course_label($filter); ?>
                    <span><?php echo (int) $counts[$filter]; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$courses): ?>
            <div class="empty-review">No courses match this review status.</div>
        <?php else: ?>
            <div class="review-grid">
                <?php foreach ($courses as $course): ?>
                    <?php
                    $thumbnail = basename((string) ($course['thumbnail'] ?? ''));
                    $image = $thumbnail !== ''
                        ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail)
                        : 'assets/images/course-placeholder.svg';
                    ?>
                    <article class="review-card">
                        <div class="review-cover">
                            <img src="<?php echo admin_course_h($image); ?>" alt="<?php echo admin_course_h($course['title']); ?>">
                            <span class="review-status <?php echo admin_course_h($course['status']); ?>">
                                <?php echo admin_course_h(admin_course_label($course['status'])); ?>
                            </span>
                            <?php if ((int) $course['pending_change_count'] > 0): ?>
                                <span class="change-flag"><?php echo (int) $course['pending_change_count']; ?> change log</span>
                            <?php endif; ?>
                        </div>

                        <div class="review-body">
                            <span class="review-id">Course #<?php echo (int) $course['id']; ?></span>
                            <h2><?php echo admin_course_h($course['title']); ?></h2>
                            <p class="instructor-line">
                                <?php echo admin_course_h($course['instructor_name']); ?> · <?php echo admin_course_h($course['instructor_email']); ?>
                            </p>
                            <p class="review-summary"><?php echo admin_course_h($course['short_description']); ?></p>

                            <div class="review-meta">
                                <div><span>Curriculum</span><strong><?php echo (int) $course['chapter_count']; ?> chapters</strong></div>
                                <div><span>Lessons</span><strong><?php echo (int) $course['lesson_count']; ?></strong></div>
                                <div><span>Price</span><strong>Rs. <?php echo number_format((float) $course['price'], 2); ?></strong></div>
                            </div>

                            <?php if ($course['review_note']): ?>
                                <p class="review-summary"><strong>Last admin note:</strong> <?php echo admin_course_h($course['review_note']); ?></p>
                            <?php endif; ?>

                            <div class="review-actions">
                                <a class="view-course" href="course-details.php?slug=<?php echo rawurlencode($course['slug']); ?>">View full course</a>

                                <?php if ((int) $course['pending_change_count'] > 0): ?>
                                    <a class="view-course" href="admin-course-changes.php?course_id=<?php echo (int) $course['id']; ?>">View exact changes</a>
                                <?php endif; ?>

                                <?php if ($course['status'] === 'pending'): ?>
                                    <form method="post">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <input type="hidden" name="review_note" value="Approved after quality review.">
                                        <button class="approve-course" type="submit" data-confirm="Publish this course?">Approve & publish</button>
                                    </form>
                                    <button class="reject-toggle" type="button">Reject</button>
                                <?php endif; ?>
                            </div>

                            <?php if ($course['status'] === 'pending'): ?>
                                <form class="reject-form" method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
                                    <input type="hidden" name="decision" value="reject">
                                    <textarea name="review_note" maxlength="1000" required placeholder="Tell the instructor exactly what must be corrected."></textarea>
                                    <button class="reject-submit" type="submit">Reject and notify instructor</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
document.querySelectorAll('.reject-toggle').forEach(button => {
    button.addEventListener('click', () => {
        button.closest('.review-card').querySelector('.reject-form').classList.toggle('open');
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
