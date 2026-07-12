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
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function admin_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function admin_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$hasChangeLogs = admin_table_exists($conn, 'course_change_logs');
$hasSubmittedAt = admin_column_exists($conn, 'courses', 'submitted_at');
$hasReviewedAt = admin_column_exists($conn, 'courses', 'reviewed_at');
$hasReviewedBy = admin_column_exists($conn, 'courses', 'reviewed_by');
$hasReviewNote = admin_column_exists($conn, 'courses', 'review_note');
$workflowSchemaReady = $hasChangeLogs && $hasSubmittedAt && $hasReviewedAt && $hasReviewedBy && $hasReviewNote;

if (!$workflowSchemaReady) {
    $message = 'Course review database migration is missing. Import database/migrations/20260710_course_workflow.sql into the coursehub database.';
    $messageType = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requirePost();

    if (!$workflowSchemaReady) {
        $message = 'Approval and rejection are disabled until the course workflow migration is imported.';
        $messageType = 'error';
    } else {
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
            try {
                $conn->begin_transaction();

                $courseStmt = $conn->prepare("
                    SELECT id, instructor_id, title, status
                    FROM courses
                    WHERE id = ? AND status = 'pending'
                    LIMIT 1
                    FOR UPDATE
                ");
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

$submittedAtSelect = $hasSubmittedAt ? 'c.submitted_at' : 'NULL AS submitted_at';
$reviewNoteSelect = $hasReviewNote ? 'c.review_note' : 'NULL AS review_note';
$pendingChangeSelect = $hasChangeLogs
    ? "(SELECT COUNT(*) FROM course_change_logs cl WHERE cl.course_id = c.id AND cl.reviewed_at IS NULL) AS pending_change_count"
    : '0 AS pending_change_count';

$sql = "
    SELECT
        c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
        c.level, c.language, c.status, {$submittedAtSelect}, c.updated_at,
        {$reviewNoteSelect}, u.full_name AS instructor_name, u.email AS instructor_email,
        cat.name AS category_name,
        (SELECT COUNT(*) FROM course_sections s WHERE s.course_id = c.id) AS chapter_count,
        (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON s.id = l.section_id WHERE s.course_id = c.id) AS lesson_count,
        {$pendingChangeSelect}
    FROM courses c
    INNER JOIN users u ON u.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE {$where}
    ORDER BY
        CASE c.status WHEN 'pending' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END,
        c.updated_at DESC
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
.review-page{min-height:calc(100vh - 72px);padding:34px 18px 68px}.review-shell{width:min(1320px,100%);margin:auto}.review-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:27px;border:1px solid #e5eaf1;border-radius:24px;background:linear-gradient(135deg,#fff,#f7f8ff);box-shadow:0 16px 45px rgba(15,23,42,.07)}.review-head h1{margin:4px 0 6px;color:#101828;font-size:clamp(1.8rem,3vw,2.5rem);letter-spacing:-.04em}.review-head p{margin:0;color:#667085}.review-kicker{color:#4f46e5!important;font-size:.72rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.review-tabs{display:flex;gap:8px;margin-bottom:20px;overflow-x:auto}.review-tabs a{display:inline-flex;min-height:40px;align-items:center;gap:8px;padding:0 14px;border:1px solid #e2e8f0;border-radius:999px;color:#475467;background:#fff;text-decoration:none;font-size:.78rem;font-weight:850;white-space:nowrap}.review-tabs a.active{border-color:#4f46e5;color:#fff;background:#4f46e5}.review-tabs span{padding:3px 7px;border-radius:999px;background:rgba(148,163,184,.18)}.review-alert{margin-bottom:18px;padding:14px 16px;border-radius:14px}.review-alert.success{border:1px solid #a7f3d0;color:#065f46;background:#ecfdf5}.review-alert.error{border:1px solid #fecaca;color:#991b1b;background:#fef2f2}.review-alert.warning{border:1px solid #fde68a;color:#92400e;background:#fffbeb}.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.empty-review{padding:55px 22px;border:2px dashed #cbd5e1;border-radius:22px;background:#fff;text-align:center;color:#667085}.course-unit-feature--admin{margin-top:14px;padding-top:14px;border-top:1px solid #e9edf5}.course-admin-note{margin:0 0 12px;padding:11px 12px;border:1px solid #e5e7eb;border-radius:11px;background:#f8fafc;color:#475467;font-size:.78rem;line-height:1.5}.course-admin-review-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.course-admin-review-actions form{margin:0}.course-admin-approve,.course-admin-reject-toggle,.course-admin-reject-form button{min-height:39px;padding:0 13px;border:0;border-radius:10px;font:inherit;font-size:.74rem;font-weight:900;cursor:pointer}.course-admin-approve{color:#fff;background:#059669}.course-admin-reject-toggle{color:#b91c1c;background:#fee2e2}.course-admin-reject-form{display:none;gap:10px;margin-top:12px;padding:12px;border:1px solid #fecaca;border-radius:12px;background:#fef2f2}.course-admin-reject-form.open{display:grid}.course-admin-reject-form label{display:grid;gap:6px}.course-admin-reject-form label span{color:#991b1b;font-size:.72rem;font-weight:900}.course-admin-reject-form textarea{width:100%;min-height:90px;padding:10px;border:1px solid #fca5a5;border-radius:10px;background:#fff;resize:vertical;font:inherit}.course-admin-reject-form button{color:#fff;background:#dc2626}@media(max-width:950px){.review-grid{grid-template-columns:1fr}}@media(max-width:700px){.review-page{padding:20px 12px 48px}.review-head{align-items:flex-start;flex-direction:column;padding:21px}.course-admin-review-actions,.course-admin-review-actions form,.course-admin-approve,.course-admin-reject-toggle,.course-admin-reject-form button{width:100%}}
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
                <a href="admin-courses.php?status=<?php echo admin_course_h($filter); ?>" class="<?php echo $statusFilter === $filter ? 'active' : ''; ?>">
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

                    $courseId = (int) $course['id'];
                    $pendingChanges = (int) $course['pending_change_count'];
                    $isPending = $course['status'] === 'pending';
                    $reviewNote = trim((string) ($course['review_note'] ?? ''));

                    ob_start();
                    ?>
                    <?php if ($reviewNote !== ''): ?>
                        <p class="course-admin-note"><strong>Last admin note:</strong> <?php echo admin_course_h($reviewNote); ?></p>
                    <?php endif; ?>

                    <?php if ($isPending): ?>
                        <?php if ($workflowSchemaReady): ?>
                            <div class="course-admin-review-actions">
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <input type="hidden" name="review_note" value="Approved after quality review.">
                                    <button class="course-admin-approve" type="submit" data-confirm="Publish this course?">Approve & publish</button>
                                </form>
                                <button class="course-admin-reject-toggle" type="button">Reject</button>
                            </div>

                            <form class="course-admin-reject-form" method="post">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                <input type="hidden" name="decision" value="reject">
                                <label>
                                    <span>Required correction note</span>
                                    <textarea name="review_note" maxlength="1000" required placeholder="Tell the instructor exactly what must be corrected."></textarea>
                                </label>
                                <button type="submit">Reject and notify instructor</button>
                            </form>
                        <?php else: ?>
                            <p class="course-admin-note"><strong>Review disabled:</strong> import the course workflow migration first.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php
                    $adminFeatureHtml = (string) ob_get_clean();
                    $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $course['slug']);

                    $courseCard = [
                        'context' => 'admin',
                        'title' => $course['title'],
                        'summary' => $course['short_description'] ?: 'No course summary was supplied.',
                        'thumbnail' => $image,
                        'category' => $course['category_name'] ?: 'General',
                        'badge' => admin_course_label((string) $course['status']),
                        'status_class' => $course['status'],
                        'eyebrow' => 'Course #' . $courseId . ' · ' . $course['instructor_name'],
                        'language' => $course['language'],
                        'price' => 'Rs. ' . number_format((float) $course['price'], 2),
                        'href' => $detailsUrl,
                        'metrics' => [
                            ['label' => 'Curriculum', 'value' => (int) $course['chapter_count'] . ' chapters'],
                            ['label' => 'Lessons', 'value' => (string) (int) $course['lesson_count']],
                            ['label' => 'Instructor', 'value' => (string) $course['instructor_email']],
                            ['label' => 'Change logs', 'value' => (string) $pendingChanges],
                        ],
                        'actions' => array_values(array_filter([
                            ['label' => 'View full course', 'href' => $detailsUrl, 'style' => 'secondary'],
                            $pendingChanges > 0
                                ? ['label' => 'View exact changes', 'href' => 'admin-course-changes.php?course_id=' . $courseId, 'style' => 'secondary']
                                : null,
                        ])),
                        'feature_html' => $adminFeatureHtml,
                    ];

                    require __DIR__ . '/../components/course_card.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
document.querySelectorAll('.course-admin-reject-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const card = button.closest('.course-unit-card--admin');
        const form = card ? card.querySelector('.course-admin-reject-form') : null;
        if (form) {
            form.classList.toggle('open');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>