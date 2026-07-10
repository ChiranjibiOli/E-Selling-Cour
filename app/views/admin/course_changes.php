<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

AdminMiddleware::handle();

$courseId = (int) ($_GET['course_id'] ?? 0);

function course_change_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare("
    SELECT c.id, c.title, c.slug, c.status, u.full_name AS instructor_name
    FROM courses c
    INNER JOIN users u ON u.id = c.instructor_id
    WHERE c.id = ? AND c.status <> 'draft'
    LIMIT 1
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$logs = [];
$stmt = $conn->prepare("
    SELECT cl.*, reviewer.full_name AS reviewer_name
    FROM course_change_logs cl
    LEFT JOIN users reviewer ON reviewer.id = cl.reviewed_by
    WHERE cl.course_id = ?
    ORDER BY cl.created_at DESC, cl.id DESC
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['before'] = json_decode((string) ($row['before_snapshot'] ?? '[]'), true) ?: [];
    $row['after'] = json_decode((string) $row['after_snapshot'], true) ?: [];
    $logs[] = $row;
}

$stmt->close();
$pageTitle = 'Course change history';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>

<style>
.change-page{min-height:calc(100vh - 72px);padding:34px 18px 68px}.change-shell{width:min(1280px,100%);margin:auto}.change-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:26px;border:1px solid #e5eaf1;border-radius:24px;background:linear-gradient(135deg,#fff,#f7f8ff);box-shadow:0 16px 45px rgba(15,23,42,.07)}.change-head h1{margin:5px 0;color:#101828;font-size:clamp(1.8rem,3vw,2.45rem);letter-spacing:-.04em}.change-head p{margin:0;color:#667085}.change-head-actions{display:flex;gap:9px}.change-head-actions a{display:inline-flex;min-height:41px;align-items:center;justify-content:center;padding:0 13px;border-radius:11px;color:#4338ca;background:#eef2ff;text-decoration:none;font-size:.75rem;font-weight:900}.change-list{display:grid;gap:17px}.change-card{overflow:hidden;border:1px solid #e5eaf1;border-radius:20px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.055)}.change-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:17px 19px;border-bottom:1px solid #edf0f5}.change-card-head strong{display:block;color:#101828}.change-card-head span{display:block;margin-top:4px;color:#98a2b3;font-size:.7rem}.review-state{padding:6px 9px;border-radius:999px;color:#92400e;background:#fef3c7;font-size:.67rem;font-weight:900}.review-state.reviewed{color:#065f46;background:#d1fae5}.snapshot-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}.snapshot{min-width:0;padding:18px}.snapshot:first-child{border-right:1px solid #edf0f5}.snapshot h2{margin:0 0 10px;color:#344054;font-size:.85rem;text-transform:uppercase}.snapshot pre{max-height:520px;margin:0;overflow:auto;padding:15px;border-radius:13px;color:#dbeafe;background:#0f172a;font:12px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;overflow-wrap:anywhere}.empty-change{padding:50px 20px;border:2px dashed #cbd5e1;border-radius:20px;background:#fff;color:#667085;text-align:center}@media(max-width:780px){.change-page{padding:20px 12px 48px}.change-head{align-items:flex-start;flex-direction:column}.change-head-actions{width:100%}.change-head-actions a{flex:1}.snapshot-grid{grid-template-columns:1fr}.snapshot:first-child{border-right:0;border-bottom:1px solid #edf0f5}}
</style>

<main class="change-page">
    <section class="change-shell">
        <header class="change-head">
            <div>
                <p class="page-label">Immutable content audit</p>
                <h1>Course #<?php echo (int) $course['id']; ?> changes</h1>
                <p><?php echo course_change_h($course['title']); ?> · <?php echo course_change_h($course['instructor_name']); ?></p>
            </div>
            <div class="change-head-actions">
                <a href="course-details.php?slug=<?php echo rawurlencode($course['slug']); ?>">Course preview</a>
                <a href="admin-courses.php?status=pending">Review queue</a>
            </div>
        </header>

        <?php if (!$logs): ?>
            <div class="empty-change">No course-content changes have been recorded.</div>
        <?php else: ?>
            <div class="change-list">
                <?php foreach ($logs as $log): ?>
                    <article class="change-card">
                        <header class="change-card-head">
                            <div>
                                <strong>Change #<?php echo (int) $log['id']; ?> · <?php echo course_change_h(str_replace('_', ' ', ucfirst($log['change_type']))); ?></strong>
                                <span><?php echo course_change_h($log['previous_status']); ?> → <?php echo course_change_h($log['new_status']); ?> · <?php echo course_change_h(date('M j, Y g:i A', strtotime($log['created_at']))); ?></span>
                            </div>
                            <span class="review-state <?php echo $log['reviewed_at'] ? 'reviewed' : ''; ?>">
                                <?php echo $log['reviewed_at'] ? 'Reviewed' : 'Awaiting review'; ?>
                            </span>
                        </header>
                        <div class="snapshot-grid">
                            <section class="snapshot">
                                <h2>Before</h2>
                                <pre><?php echo course_change_h(json_encode($log['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                            </section>
                            <section class="snapshot">
                                <h2>After</h2>
                                <pre><?php echo course_change_h(json_encode($log['after'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                            </section>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
