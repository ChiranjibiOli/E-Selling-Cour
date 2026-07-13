<?php

declare(strict_types=1);

require_once '../app/middleware/AdminMiddleware.php';
require_once '../app/config/database.php';

AdminMiddleware::handle();

/** @var mysqli $conn */
$conn = database_connection();

function admin_course_removal_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_course_removal_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    if ($safeTable === '') {
        return false;
    }

    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safeTable) . "'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requirePost();

    $courseId = (int) ($_POST['course_id'] ?? 0);
    $transactionStarted = false;

    try {
        if ($courseId <= 0) {
            throw new DomainException('Invalid course deletion request.');
        }

        $conn->begin_transaction();
        $transactionStarted = true;

        $courseStmt = $conn->prepare('SELECT id, title FROM courses WHERE id = ? LIMIT 1 FOR UPDATE');
        $courseStmt->bind_param('i', $courseId);
        $courseStmt->execute();
        $course = $courseStmt->get_result()->fetch_assoc() ?: null;
        $courseStmt->close();

        if (!$course) {
            throw new DomainException('The course no longer exists.');
        }

        $enrollmentStmt = $conn->prepare('SELECT COUNT(*) AS total FROM enrollments WHERE course_id = ?');
        $enrollmentStmt->bind_param('i', $courseId);
        $enrollmentStmt->execute();
        $enrollmentCount = (int) ($enrollmentStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $enrollmentStmt->close();

        $orderStmt = $conn->prepare('SELECT COUNT(*) AS total FROM order_items WHERE course_id = ?');
        $orderStmt->bind_param('i', $courseId);
        $orderStmt->execute();
        $orderItemCount = (int) ($orderStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $orderStmt->close();

        $earningCount = 0;
        if (admin_course_removal_table_exists($conn, 'instructor_earnings')) {
            $earningStmt = $conn->prepare('SELECT COUNT(*) AS total FROM instructor_earnings WHERE course_id = ?');
            $earningStmt->bind_param('i', $courseId);
            $earningStmt->execute();
            $earningCount = (int) ($earningStmt->get_result()->fetch_assoc()['total'] ?? 0);
            $earningStmt->close();
        }

        if ($enrollmentCount > 0 || $orderItemCount > 0 || $earningCount > 0) {
            throw new DomainException('This course has enrollment or purchase history and cannot be permanently deleted.');
        }

        $deleteCourseRows = static function (mysqli $conn, string $table, int $courseId): void {
            if (!admin_course_removal_table_exists($conn, $table)) {
                return;
            }

            $stmt = $conn->prepare("DELETE FROM `{$table}` WHERE course_id = ?");
            $stmt->bind_param('i', $courseId);
            $stmt->execute();
            $stmt->close();
        };

        foreach (['cart', 'coupon_courses', 'reviews', 'course_change_logs'] as $table) {
            $deleteCourseRows($conn, $table, $courseId);
        }

        if (admin_course_removal_table_exists($conn, 'course_lessons') && admin_course_removal_table_exists($conn, 'course_sections')) {
            $lessonStmt = $conn->prepare("
                DELETE lesson
                FROM course_lessons lesson
                INNER JOIN course_sections section_record ON section_record.id = lesson.section_id
                WHERE section_record.course_id = ?
            ");
            $lessonStmt->bind_param('i', $courseId);
            $lessonStmt->execute();
            $lessonStmt->close();
        }

        if (admin_course_removal_table_exists($conn, 'course_sections')) {
            $sectionStmt = $conn->prepare('DELETE FROM course_sections WHERE course_id = ?');
            $sectionStmt->bind_param('i', $courseId);
            $sectionStmt->execute();
            $sectionStmt->close();
        }

        $deleteStmt = $conn->prepare('DELETE FROM courses WHERE id = ?');
        $deleteStmt->bind_param('i', $courseId);
        $deleteStmt->execute();

        if ($deleteStmt->affected_rows !== 1) {
            $deleteStmt->close();
            throw new RuntimeException('The course could not be permanently deleted.');
        }
        $deleteStmt->close();

        $conn->commit();
        $transactionStarted = false;
        Auth::redirect('admin-course-removal.php?deleted=1');
    } catch (DomainException $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        Auth::redirect('admin-course-removal.php?blocked=1');
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        error_log('Admin course deletion failed: ' . $exception->getMessage());
        Auth::redirect('admin-course-removal.php?delete_error=1');
    }
}

$courses = [];
$result = $conn->query("
    SELECT
        c.id, c.title, c.slug, c.status, c.price, c.created_at,
        instructor.full_name AS instructor_name,
        category.name AS category_name,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrollment_count,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.course_id = c.id) AS order_item_count
    FROM courses c
    INNER JOIN users instructor ON instructor.id = c.instructor_id
    LEFT JOIN categories category ON category.id = c.category_id
    ORDER BY c.created_at DESC, c.id DESC
");

while ($result && $row = $result->fetch_assoc()) {
    $courses[] = $row;
}

$pageTitle = 'Course removal';
require_once '../app/views/layouts/header.php';
require_once '../app/views/layouts/admin_navbar.php';
?>

<style>
.course-removal-page{min-height:calc(100vh - 72px);padding:34px 0 68px}.course-removal-wrapper{margin:auto}.course-removal-head{display:flex;align-items:end;justify-content:space-between;gap:24px;margin-bottom:20px;padding:28px;border:1px solid rgba(72,58,39,.16);border-radius:24px;background:#fffdf8;box-shadow:0 14px 38px rgba(39,31,21,.07)}.course-removal-head h1{margin:6px 0 8px;font-family:Georgia,"Times New Roman",serif;font-size:clamp(2.2rem,4vw,4rem);font-weight:500;letter-spacing:-.05em}.course-removal-head p{max-width:780px;margin:0;color:#746b61;line-height:1.65}.course-removal-kicker{color:#8a5d16!important;font-size:.7rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.course-removal-alert{margin-bottom:18px;padding:14px 16px;border-radius:14px}.course-removal-alert.success{border:1px solid #a7f3d0;color:#065f46;background:#ecfdf5}.course-removal-alert.error{border:1px solid #fecaca;color:#991b1b;background:#fef2f2}.course-removal-list{display:grid;gap:12px}.course-removal-row{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(150px,.7fr) minmax(110px,.45fr) minmax(210px,.8fr);gap:18px;align-items:center;padding:18px;border:1px solid rgba(72,58,39,.14);border-radius:18px;background:#fffdf8;box-shadow:0 8px 24px rgba(39,31,21,.05)}.course-removal-row h2{margin:0 0 5px;font-size:1rem}.course-removal-row p,.course-removal-row small{margin:0;color:#7d746a}.course-removal-status{display:inline-flex;width:max-content;padding:6px 9px;border-radius:999px;background:#f0e6d7;font-size:.68rem;font-weight:900;text-transform:uppercase}.course-removal-counts{display:grid;gap:4px;color:#5f574e;font-size:.76rem}.course-removal-actions{display:flex;align-items:center;justify-content:flex-end}.course-removal-actions form{margin:0}.course-removal-delete,.course-removal-blocked{display:inline-flex;min-height:40px;align-items:center;justify-content:center;padding:0 14px;border:0;border-radius:11px;font:inherit;font-size:.74rem;font-weight:900}.course-removal-delete{cursor:pointer;color:#fff;background:#b42318}.course-removal-delete:hover{background:#8f1c13}.course-removal-blocked{color:#7b6f63;background:#eee8df}.course-removal-empty{padding:48px 20px;border:2px dashed rgba(72,58,39,.2);border-radius:22px;text-align:center;color:#746b61}@media(max-width:900px){.course-removal-row{grid-template-columns:1fr 1fr}.course-removal-actions{justify-content:flex-start}}@media(max-width:620px){.course-removal-page{padding:22px 0 48px}.course-removal-head{padding:22px}.course-removal-row{grid-template-columns:1fr}.course-removal-actions,.course-removal-actions form,.course-removal-delete,.course-removal-blocked{width:100%}}
</style>

<main class="course-removal-page">
    <section class="course-removal-wrapper">
        <header class="course-removal-head">
            <div>
                <p class="course-removal-kicker">Permanent database cleanup</p>
                <h1>Remove unused courses</h1>
                <p>A course can be permanently deleted only before any enrollment, order item, or earning exists. Courses with student or financial history remain protected.</p>
            </div>
        </header>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="course-removal-alert success">The course and its database content were permanently deleted.</div>
        <?php elseif (isset($_GET['blocked'])): ?>
            <div class="course-removal-alert error">Deletion was blocked because the course has enrollment or purchase history.</div>
        <?php elseif (isset($_GET['delete_error'])): ?>
            <div class="course-removal-alert error">The course could not be deleted. No partial deletion was committed.</div>
        <?php endif; ?>

        <?php if ($courses === []): ?>
            <div class="course-removal-empty">No courses are currently stored.</div>
        <?php else: ?>
            <div class="course-removal-list">
                <?php foreach ($courses as $course): ?>
                    <?php
                    $enrollmentCount = (int) $course['enrollment_count'];
                    $orderItemCount = (int) $course['order_item_count'];
                    $canDelete = $enrollmentCount === 0 && $orderItemCount === 0;
                    ?>
                    <article class="course-removal-row">
                        <div>
                            <h2><?php echo admin_course_removal_h($course['title']); ?></h2>
                            <p><?php echo admin_course_removal_h($course['instructor_name']); ?> · <?php echo admin_course_removal_h($course['category_name'] ?: 'General'); ?></p>
                        </div>
                        <div>
                            <span class="course-removal-status"><?php echo admin_course_removal_h($course['status']); ?></span>
                            <small>Rs. <?php echo number_format((float) $course['price'], 2); ?></small>
                        </div>
                        <div class="course-removal-counts">
                            <span><?php echo $enrollmentCount; ?> enrollment(s)</span>
                            <span><?php echo $orderItemCount; ?> order item(s)</span>
                        </div>
                        <div class="course-removal-actions">
                            <?php if ($canDelete): ?>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
                                    <button class="course-removal-delete" type="submit" data-confirm="Permanently delete this course and its curriculum from the database? This cannot be undone.">Delete permanently</button>
                                </form>
                            <?php else: ?>
                                <span class="course-removal-blocked">Protected by history</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once '../app/views/layouts/panel_end.php'; ?>
